<?php

namespace App\Rules\Franchise;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Packk\Core\Integration\Payment\ProcessPayment;
use Packk\Core\Integration\Payment\Seller;
use Packk\Core\Models\BankAccount;
use Packk\Core\Models\Franchise;
use Packk\Core\Models\UserFranchise;
use Packk\Core\Util\Formatter;
use Packk\Core\Util\Phones;

class UpdateSeller
{
    protected $payload;
    protected $franchise;
    protected $seller;

    public function execute($payload)
    {
        $this->payload = $payload;
        $this->franchise = Franchise::findOrFail($payload["franchise_id"]);

        try {
            DB::beginTransaction();
            if($this->checkCreateNewSeller()) {
                $response = (new CreateSeller())->execute($this->payload);
                DB::commit();
                return $response;
            }

            try {
                $seller = Seller::updateBusinessSeller($this->payload["seller_id"], $this->formatBusinessSeller());
                $this->franchise->zoop_seller_id = $seller->id;
                $this->franchise->save();
            } catch (\Exception $exception) {
                throw $exception;
            }

            $zoop = new ProcessPayment();
            $contasAntigas = $zoop->getContasAntigas($this->franchise->zoop_seller_id);
            $bankCode = $this->payload["bank_code"];
            $bankRoute = $this->payload["routing_number"];
            $bankNumber = $this->payload["account_number"];
            $bankName = $this->payload["holder_name"];
            $zoop->associaContaFranquia(
                $this->franchise,
                $this->payload["ein"],
                $bankName,
                $bankCode,
                $bankRoute,
                $bankNumber
            );
            $zoop->deleteContasBancarias($contasAntigas);
            $contaBancaria = BankAccount::firstOrNew(['franchise_id' => $this->franchise->id]);
            $contaBancaria->favorecido = $bankName;
            $contaBancaria->conta = $bankNumber;
            $contaBancaria->agencia = $bankRoute;
            $contaBancaria->banco = $bankCode;
            $contaBancaria->save();
            $this->storeDocuments();

            DB::commit();
            return ['success' => true, 'message' => 'Atualizado com sucesso'];
        } catch (\Exception) {
            DB::rollBack();

            throw new \Exception('Ocorreu um erro durante a criação da conta bancária na zoop. Verifique os dados informados', 1);
        }
    }

    private function rollBackZoopSeller($id)
    {
        $bs = new Seller($id);
        $bs->destroy();
    }

    private function formatBusinessSeller()
    {
        $domain = $this->franchise->domain;
        $userFranchise = UserFranchise::query()
            ->where('franchise_id', $this->franchise->id)
            ->whereHas('user', function ($q) {
                $q->whereHas('dbRoles', function ($q) {
                    $q->where('name', '=', 'admin-franchise');
                });
            })->first();
        $user = $userFranchise->user;

        return [
            'business_name' => $this->payload["business_name"] ?? $this->franchise->name,
            'business_phone' => Phones::format($user->telefone),
            'business_email' => $this->payload["owner_email"] ?? $user->email,
            'business_description' => $this->payload["business_description"] ?? $this->franchise->fantasy_name,
            'reference_id' => $this->franchise->id,
            'reference_provider' => "franquia-{$domain->name}",
            'ein' => $this->payload["ein"],
            'business_opening_date' => $this->formatDate($this->payload["business_opening_date"]),
            'mcc' => $this->payload["mcc"],
            'owner' => [
                'first_name' => $user->nome,
                'last_name' => $user->sobrenome,
                'email' => $user->email,
                'phone_number' => Phones::format($user->telefone),
                'birthdate' => isset($user->borned_at) ? Formatter::formatDate($user->borned_at) : Carbon::now()->format('Y-m-d'),
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

    private function storeDocuments() {
        try {
            $documents = $this->payloadDocuments();
            if(count($documents["files"]) > 0) {
                (new Seller())->storeDocuments($documents);
            }
        } catch (\Exception $e) {
            if($this->franchise->zoop_seller_id)
                $this->rollBackZoopSeller($this->franchise->zoop_seller_id);
            throw $e;
        }
    }

    private function payloadDocuments() {
        $files = collect([]);
        foreach ((array) $this->payload as $key => $value) {
            switch ($key) {
                case Str::contains($key, '_identificacao'):
                    if(!empty($value)) {
                        $files->push([
                            "file"        => $value,
                            "category"    => "identificação",
                            "description" => ""
                        ]);
                    }
                    break;
                case Str::contains($key, '_residencia'):
                    if(!empty($value)) {
                        $files->push([
                            "file"        => $value,
                            "category"    => "residencia",
                            "description" => ""
                        ]);
                    }
                    break;
                case Str::contains($key, '_atividade'):
                    if(!empty($value)) {
                        $files->push([
                            "file"        => $value,
                            "category"    => "atividade",
                            "description" => ""
                        ]);
                    }
                    break;
                case Str::contains($key, '_cnpj'):
                    if(!empty($value)) {
                        $files->push([
                            "file"        => $value,
                            "category"    => "cnpj",
                            "description" => ""
                        ]);
                    }
                    break;
                default:
                    break;
            }
        }
        return [
            "seller_id" => $this->franchise->zoop_seller_id,
            "files"     => $files->toArray()
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
}