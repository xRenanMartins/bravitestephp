<?php

namespace App\Rules\Store;

use Packk\Core\Exceptions\RuleException;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\BankAccount;
use Packk\Core\Models\Store;
use Packk\Core\Integration\Payment\ProcessPayment;
use Packk\Core\Integration\Payment\Seller;
use Illuminate\Support\Facades\DB;
use Packk\Core\Util\Formatter;
use Packk\Core\Util\Phones;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Packk\Core\Models\PaymentMethod;

class CreateSeller
{
    protected $payload;
    protected $store;

    public function execute($payload)
    {
        $this->payload = $payload;
        $this->store = Store::with(['shopkeeper', 'domain'])->findOrFail($payload["store_id"]);

        if (!$this->store->is_partner)
            throw new RuleException("Loja inválida", "Só é possível criar seller de lojas parceiras", 430);

        DB::beginTransaction();
        try {
            $this->store->tipo_checkout = 'PARCEIRO';

            try {
                if (strlen($this->payload["ein"]) <= 11) {
                    $search = $this->searchSellerZoop("taxpayer_id");
                    if ($search) {
                        $this->store->zoop_seller_id = $search->id;
                    } else {
                        $this->store->zoop_seller_id = Seller::createIndividualSellerToStore($this->formatIndividualSeller());
                    }
                } else {
                    $search = $this->searchSellerZoop("ein");
                    if ($search) {
                        $this->store->zoop_seller_id = $search->id;
                    } else {
                        $this->store->zoop_seller_id = Seller::createBusinessSeller($this->formatBusinessSeller());
                    }
                }
            } catch (\Exception $exception) {
                DB::rollBack();
                if ($exception->getMessage()) {
                    throw new RuleException("Erro ao cadastrar Seller", $exception->getMessage(), 430);
                }
                throw new \Exception('Ocorreu um erro durante a criação do seller na zoop. Verifique os dados informados', 1);
            }
            $this->storeDocuments();

            try {
                $zoop = new ProcessPayment();
                if ($this->payload["bank_code"] && $this->payload["routing_number"] && $this->payload["account_number"] && $this->payload["holder_name"]) {
                    $bank_code = str_pad($this->payload["bank_code"], 3, "0", STR_PAD_LEFT);

                    $zoop->associaContaLoja(
                        $this->store,
                        $this->payload["holder_name"],
                        $bank_code,
                        $this->payload["routing_number"],
                        $this->payload["account_number"],
                        $this->payload["ein"]
                    );
                    $bankAccount = BankAccount::firstOrNew(['loja_id' => $this->store->id]);
                    $bankAccount->favorecido = $this->payload["holder_name"];
                    $bankAccount->conta = $this->payload["account_number"];
                    $bankAccount->agencia = $this->payload["routing_number"];
                    $bankAccount->banco = $bank_code;
                    $bankAccount->save();
                }
                $this->store->save();
                $payments = PaymentMethod::whereIn("label", ['CARTAO_CREDITO', 'PIX', 'AME'])->get();
                foreach ($payments as $payment) {
                    $this->store->all_payment_methods()->syncWithoutDetaching([$payment->id => [
                        'deleted_at' => null, 'is_active' => true
                    ]]);
                }
                DB::commit();
                dispatch(new SendShopFeedEvent($this->store->id, 'rules:change', ['seller']));
            } catch (\Exception $exception) {
                DB::rollBack();
                if ($this->store->zoop_seller_id)
                    $this->rollBackZoopSeller($this->store->zoop_seller_id);

                throw $exception;
            }

            return ['success' => true, 'message' => 'Criado com sucesso'];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function rollBackZoopSeller($zid)
    {
        $bs = new Seller($zid);
        $bs->destroy();
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
            "seller_id" => $this->store->zoop_seller_id,
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
            'mcc' => (string)$this->payload["mcc"],
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

    private function searchSellerZoop($type)
    {
        $seller = new Seller();
        $payload = [
            $type => $this->payload["ein"],
            "reference_id" => $this->store->id,
            "reference_provider" => "loja-{$this->store->domain->name}"
        ];
        $search = $seller->searchSellerZoop($payload);
        if (isset($search->id)) {
            return $search;
        }
        return false;
    }

    private function storeDocuments()
    {
        try {
            $documents = $this->payloadDocuments();
            if (count($documents["files"]) > 0) {
                (new Seller())->storeDocuments($documents);
            }
        } catch (\Exception $e) {
            if ($this->store->zoop_seller_id)
                $this->rollBackZoopSeller($this->store->zoop_seller_id);
            throw $e;
        }
    }

    private function formatDate($date)
    {
        try {
            if (str_contains($date, "/")) {
                return Formatter::formatDate($date);
            }

            if (substr($date, 4, 1) == "-" && substr($date, 7, 1) == "-") {
                return Carbon::parse($date)->format("Y-m-d");
            }

            return substr($date, -4) . "-" . substr($date, 2, 2) . "-" . substr($date, 0, 2);
        } catch (\Throwable $th) {
            return now()->format("Y-m-d");
        }
    }
}
