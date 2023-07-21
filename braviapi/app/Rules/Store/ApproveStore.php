<?php

namespace App\Rules\Store;

use Illuminate\Support\Facades\DB;
use Packk\Core\Exceptions\RuleException;
use Packk\Core\Models\BankAccount;
use Packk\Core\Models\Presenter\ExceptionPresenter;
use Packk\Core\Models\Store;
use Packk\Core\Models\StoreActivity;
use Packk\Core\Models\UserStore;

class ApproveStore
{
    protected $store;
    protected $payload;

    /**
     * Aprovação e habilitação de loja pré aprováda
     */
    public function execute($id)
    {
        try {
            DB::beginTransaction();
            $this->store = Store::findOrFail($id);
            $userStore = UserStore::query()->where('store_id', $this->store->id)->first();
            $this->validate($userStore);

            if (empty($this->store->zoop_seller_id)) {
                (new \App\Rules\Store\CreateSeller())->execute($this->payload($userStore->user_id));
            }

            $this->store->status = "ACTIVE";
            $this->store->ativo = 1;
            $this->store->habilitado = 1;
            $this->store->save();

            $this->store->users->each(function ($user) {
                $user->status = "ATIVO";
                $user->save();
            });

            $this->storeActivity();

            DB::commit();

            return ["id" => $this->store->id, "status" => $this->store->status];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    private function validate($userStore)
    {
        if (in_array($this->store->status, ["ACTIVE"])) {
            throw new RuleException(
                ExceptionPresenter::getTitle("STORE_HAS_APPROVED"),
                ExceptionPresenter::getMessage("STORE_HAS_APPROVED"),
                430
            );
        }

        if (!isset($userStore->user_id)) {
            throw new RuleException(
                ExceptionPresenter::getTitle("STORE_NOTFOUND_USER"),
                ExceptionPresenter::getMessage("STORE_NOTFOUND_USER"),
                430
            );
        }
    }

    private function payload($userId)
    {
        $data = $this->dataSelfOnboarding($userId);
        $bankAccount = $this->bankAccount();
        $address = $this->getAddress(json_decode($data->address));
        $dataBusiness = $data->cnpj_info ? json_decode($data->cnpj_info) : null;
        $payload = new \stdClass;

        $payload->store_id = $this->store->id;
        $payload->ein = $data->cnpj;
        $payload->holder_name = $bankAccount->favorecido;
        $payload->bank_code = $bankAccount->banco;
        $payload->routing_number = $bankAccount->agencia;
        $payload->account_number = $bankAccount->conta;
        $payload->business_name = $data->corporate_name;
        $payload->owner_email = $data->email;
        $payload->business_description = $data->fantasy_name;
        $payload->business_opening_date = $dataBusiness->estabelecimento->data_inicio_atividade ?? now()->format("Y-m-d");
        $payload->mcc = $data->mcc ?? 27;
        $payload->business_address_line1 = $address->business_address_line1;
        $payload->business_address_line2 = $address->business_address_line2 ?? "s/n";
        $payload->business_address_line3 = "n/a";
        $payload->business_address_city = $address->business_address_city;
        $payload->business_address_neighborhood = $address->business_address_neighborhood;
        $payload->business_address_state = $address->business_address_state;
        $payload->business_address_postal_code = $address->business_address_postal_code;
        $payload->business_address_country_code = $address->business_address_country_code ?? "BR";

        return (array)$payload;
    }

    private function dataSelfOnboarding($id)
    {
        $selfOnboarding = DB::connection("utils_2")
            ->table("self_onboarding")
            ->where("user_id", $id)
            ->first();

        if (is_null($selfOnboarding)) {
            throw new RuleException(
                ExceptionPresenter::getTitle("SELFONBOARD_NOT_FOUND"),
                ExceptionPresenter::getMessage("SELFONBOARD_NOT_FOUND"),
                430
            );
        }

        return $selfOnboarding;
    }

    private function bankAccount()
    {
        $bankAccount = BankAccount::where("loja_id", $this->store->id)
            ->first();

        if (is_null($bankAccount)) {
            throw new RuleException(
                ExceptionPresenter::getTitle("STORE_WITHOUT_BANKACCOUNT"),
                ExceptionPresenter::getMessage("STORE_WITHOUT_BANKACCOUNT"),
                430
            );
        }
        return $bankAccount;
    }

    private function getAddress($address)
    {
        $data = new \stdClass;

        foreach ($address->address_components as $key => $component) {
            if (in_array("route", $component->types)) {
                $data->business_address_line1 = $component->long_name;
            }
            if (in_array("stree_number", $component->types)) {
                $data->business_address_line2 = $component->long_name;
            }
            if (in_array("postal_code", $component->types)) {
                $data->business_address_postal_code = $component->long_name;
            }
            if (in_array("administrative_area_level_1", $component->types)) {
                $data->business_address_state = $component->short_name;
            }
            if (in_array("administrative_area_level_2", $component->types)) {
                $data->business_address_city = $component->long_name;
            }
            if (in_array("sublocality_level_1", $component->types)) {
                $data->business_address_neighborhood = $component->long_name;
            }
            if (in_array("country", $component->types)) {
                $data->business_address_country_code = $component->short_name;
            }
        }

        if (!isset($data->business_address_city) || !isset($data->business_address_postal_code) ||
            !isset($data->business_address_neighborhood) || !isset($data->business_address_state)) {
            throw new RuleException('Ops!', 'As informações de endereço da loja estão incompletas', 430);
        }

        return $data;
    }

    private function storeActivity()
    {
        $storeActivity = new StoreActivity();
        $storeActivity->user_id = auth()->user()->id;
        $storeActivity->store_id = $this->store->id;
        $storeActivity->description = "Aprovação de loja";
        $storeActivity->activity = 'HABILITAR';

        $storeActivity->save();
    }
}