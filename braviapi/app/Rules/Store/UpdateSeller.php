<?php

namespace App\Rules\Store;

use Packk\Core\Integration\Payment\ProcessPayment;
use Packk\Core\Integration\Payment\Seller;
use Illuminate\Support\Facades\DB;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\Store;
use Packk\Core\Models\BankAccount;
use Packk\Core\Util\Formatter;
use Packk\Core\Util\Phones;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Packk\Core\Models\PaymentMethod;

class UpdateSeller
{
    protected $payload;
    protected $store;
    protected $seller;

    public function __construct()
    {
    }

    public function execute($payload)
    {
        $this->payload = $payload;
        $this->store = Store::with(['domain', 'shopkeeper'])->findOrFail($payload["store_id"]);

        DB::beginTransaction();
        try {
            if ($this->checkCreateNewSeller()) {
                $response = (new CreateSeller())->execute($this->payload);
                DB::commit();
                return $response;
            }

            if (strlen($this->payload["ein"]) <= 11) {
                $seller = Seller::updateindividualseller($this->payload["seller_id"], $this->formatIndividualSeller());
                $this->store->zoop_seller_id = $seller->id;
            } else {
                $seller = Seller::updateBusinessSeller($this->payload["seller_id"], $this->formatBusinessSeller());
                $this->store->zoop_seller_id = $seller->id;
            }

            $this->store->save();
            $payments = PaymentMethod::whereIn("label", ['CARTAO_CREDITO', 'PIX', 'AME'])->get();
            foreach ($payments as $payment) {
                $this->store->all_payment_methods()->syncWithoutDetaching([$payment->id => [
                    'deleted_at' => null, 'is_active' => true
                ]]);
            }
        } catch (\Exception $exception) {
            if ($exception->getCode() == 404) { // Seller existe no nosso banco, mas não existe na Zoop
                $response = (new CreateSeller())->execute($this->payload);
                DB::commit();
                return $response;
            }
            DB::rollBack();
            throw $exception;
        }
        $this->storeDocuments();

        try {
            $zoop = new ProcessPayment();
            $contas_antigas = $zoop->getContasAntigas($this->store->zoop_seller_id);
            $bank_code = str_pad($this->payload["bank_code"], 3, "0", STR_PAD_LEFT);
            $bank_route = $this->payload["routing_number"];
            $bank_number = $this->payload["account_number"];
            $bank_name = $this->payload["holder_name"];
            $zoop->associaContaLoja($this->store, $bank_name, $bank_code, $bank_route, $bank_number, $this->payload["ein"]);
            $zoop->deleteContasBancarias($contas_antigas);
            $conta_bancaria = BankAccount::firstOrNew(['loja_id' => $this->store->id]);
            $conta_bancaria->favorecido = $bank_name;
            $conta_bancaria->conta = $bank_number;
            $conta_bancaria->agencia = $bank_route;
            $conta_bancaria->banco = $bank_code;
            $conta_bancaria->save();

            DB::commit();
            dispatch(new SendShopFeedEvent($this->store->id, 'rules:change', ['seller']));
            return ['success' => true, 'message' => 'Atualizado com sucesso'];
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    private function storeDocuments()
    {
        try {
            $documents = $this->payloadDocuments();
            if (count($documents["files"]) > 0) {
                (new Seller())->storeDocuments($documents);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function payloadDocuments()
    {
        $files = collect([]);

        foreach ((array)$this->payload as $key => $value) {
            switch ($key) {
                case Str::contains($key, '_identificacao'):
                    if (!empty($value)) {
                        $files->push([
                            "file" => $value,
                            "category" => "identificação",
                            "description" => ""
                        ]);
                    }
                    break;
                case Str::contains($key, '_residencia'):
                    if (!empty($value)) {
                        $files->push([
                            "file" => $value,
                            "category" => "residencia",
                            "description" => ""
                        ]);
                    }
                    break;
                case Str::contains($key, '_atividade'):
                    if (!empty($value)) {
                        $files->push([
                            "file" => $value,
                            "category" => "atividade",
                            "description" => ""
                        ]);
                    }
                    break;
                case Str::contains($key, '_cnpj'):
                    if (!empty($value)) {
                        $files->push([
                            "file" => $value,
                            "category" => "cnpj",
                            "description" => ""
                        ]);
                    }
                    break;
                default:
                    break;
            }
        }

        return [
            "seller_id" => $this->payload["seller_id"],
            "files" => $files->toArray()
        ];
    }

    private function formatIndividualSeller()
    {

        return [
            'first_name' => $this->payload["owner_first_name"],
            'last_name' => $this->payload["owner_last_name"],
            'email' => $this->payload["owner_email"],
            'phone_number' => Phones::format($this->store->telefone),
            'taxpayer_id' => $this->payload["ein"],
            'birthdate' => $this->formatDate($this->payload["owner_birthdate"]),
            'reference_id' => $this->store->id,
            'reference_provider' => "loja-{$this->store->domain->name}",
            'mcc' => $this->payload["mcc"],
            'address' => [
                'line1' => $this->payload["business_address_line1"],
                'line2' => $this->payload["business_address_line2"],
                'line3' => $this->payload["business_address_line3"],
                'city' => $this->payload["business_address_city"],
                'neighborhood' => $this->payload["business_address_neighborhood"],
                'state' => $this->payload["business_address_state"],
                'postal_code' => $this->payload["business_address_postal_code"],
                'country_code' => $this->payload["business_address_country_code"]
            ]
        ];
    }

    private function formatBusinessSeller()
    {
        $user = $this->store->shopkeeper->user;

        return [
            'business_name' => $this->payload["business_name"] ?? $this->store->name,
            'business_phone' => Phones::format($this->store->telefone),
            'business_email' => $this->payload["owner_email"] ?? $user->email,
            'business_description' => $this->payload["business_description"] ?? $this->store->descricao,
            'reference_id' => $this->store->id,
            'reference_provider' => "loja-{$this->store->domain->name}",
            'ein' => $this->payload["ein"],
            'business_opening_date' => $this->formatDate($this->payload["business_opening_date"]),
            'mcc' => $this->payload["mcc"],
            'owner' => [
                'first_name' => $user->nome,
                'last_name' => $user->sobrenome,
                'email' => $this->payload["owner_email"] ?? $user->email,
                'phone_number' => Phones::format($user->telefone),
                'birthdate' => !empty($user->borned_at) ? Carbon::parse($user->borned_at)->format('Y-m-d') : Carbon::now()->format('Y-m-d'),
            ],
            'business_address' => [
                'line1' => $this->payload["business_address_line1"],
                'line2' => $this->payload["business_address_line2"],
                'line3' => $this->payload["business_address_line3"],
                'city' => $this->payload["business_address_city"],
                'neighborhood' => $this->payload["business_address_neighborhood"],
                'state' => $this->payload["business_address_state"],
                'postal_code' => $this->payload["business_address_postal_code"],
                'country_code' => $this->payload["business_address_country_code"]
            ]
        ];
    }

    private function checkCreateNewSeller()
    {
        try {
            $sellerId = $this->store->zoop_seller_id ?? $this->payload["seller_id"];
            $this->seller = new Seller($sellerId, true);
            return $this->seller->ein !== $this->payload["ein"];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function formatDate($date)
    {
        try {
            if (str_contains($date, "/")) {
                return Formatter::formatDate($date);
            }

            return substr($date, -4) . "-" . substr($date, 2, 2) . "-" . substr($date, 0, 2);
        } catch (\Throwable $th) {
            return now()->format("Y-m-d");
        }
    }
}
