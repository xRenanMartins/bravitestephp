<?php

namespace App\Rules\Franchise;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packk\Core\Exceptions\RuleException;
use Packk\Core\Integration\Payment\ProcessPayment;
use Packk\Core\Integration\Payment\Seller;
use Packk\Core\Models\BankAccount;
use Packk\Core\Models\Franchise;
use Packk\Core\Models\UserFranchise;
use Packk\Core\Util\Formatter;
use Packk\Core\Util\Phones;

class CreateSeller
{
    protected $payload;
    protected $franchise;

    public function execute($payload)
    {
        $this->payload = $payload;
        $this->franchise = Franchise::findOrFail($payload["franchise_id"]);

        try {
            DB::beginTransaction();
            try {
                $search = $this->searchSellerZoop();
                if ($search) {
                    $this->franchise->zoop_seller_id = $search->id;
                } else {
                    $this->franchise->zoop_seller_id = Seller::createBusinessSeller($this->formatBusinessSeller());
                }

            } catch (\Exception $exception) {
                if ($exception->getMessage()) {
                    throw new RuleException("Erro ao cadastrar Seller", $exception->getMessage(), 430);
                }
                throw new \Exception('Ocorreu um erro durante a criação do seller na zoop. Verifique os dados informados', 1);
            }

            try {
                $zoop = new ProcessPayment();
                if ($this->payload["bank_code"] && $this->payload["routing_number"] && $this->payload["account_number"] && $this->payload["holder_name"]) {
                    $zoop->associaContaFranquia(
                        $this->franchise,
                        $this->payload["ein"],
                        $this->payload["holder_name"],
                        str_pad($this->payload["bank_code"], 3, "0", STR_PAD_LEFT),
                        $this->payload["routing_number"],
                        $this->payload["account_number"]
                    );
                    BankAccount::create([
                        'favorecido' => $this->payload["holder_name"],
                        'conta' => $this->payload["account_number"],
                        'agencia' => $this->payload["routing_number"],
                        'banco' => str_pad($this->payload["bank_code"], 3, "0", STR_PAD_LEFT),
                        'franchise_id' => $this->franchise->id
                    ]);
                }
                $this->storeDocuments();
                $this->franchise->save();
            } catch (\Exception $exception) {
                if ($this->franchise->zoop_seller_id)
                    $this->rollBackZoopSeller($this->franchise->zoop_seller_id);

                throw $exception;
            }

            DB::commit();
            return ['success' => true, 'message' => 'Criado com sucesso'];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function rollBackZoopSeller($zid)
    {
        (new Seller($zid))->destroy();
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
        if (empty($userFranchise)) {
            throw new \Exception('Essa franquia não possui franqueado');
        }
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
                'email' => $this->payload["owner_email"] ?? $user->email,
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

    private function searchSellerZoop()
    {
        $search = (new Seller())->searchSellerZoop([
            "ein" => $this->payload["ein"],
            "reference_id" => $this->franchise->id,
            "reference_provider" => "franquia-{$this->franchise->domain->name}"
        ]);

        if (isset($search->id)) {
            return $search;
        }
        return false;
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
}