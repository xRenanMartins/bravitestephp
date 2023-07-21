<?php

namespace App\Rules\Store\V2;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Models\Category;
use Packk\Core\Models\Domain;
use Packk\Core\Models\Franchise;
use Packk\Core\Models\Store;
use Packk\Core\Models\User;

class DataEditStore
{
    private array $response;
    private $user;
    private $store;

    public function execute($storeId = null)
    {
        $this->user = Auth::user();
        $domain = currentDomain(true);
        $this->response = [];
        Cache::forget("domain.{$domain->id}.settings");
        Cache::forget("store.{$storeId}.settings");

        if (!empty($storeId)) {
            $this->store = Store::with(['address', 'categories_store', 'shopkeeper.user'])->findOrFail($storeId);
            $domain = $this->store->domain;
            $this->toEdit();
        }

        $this->response['dynamic_mdr'] = $domain->getSetting("mdr_percentage", 0);
        $this->response['categories'] = Category::selectRaw('categorias.id, CONCAT(categorias.nome," | ",vitrines.id," - ", vitrines.identifier) as name' )
            ->where('categorias.is_primary', 1)->where('categorias.ativo', 1)->where('categorias.tipo', 'L')->join('vitrines', 'vitrines.id', '=', 'categorias.vitrine_id')->get();

        $this->response['type_store_options'] = $this->getTypeStoreOptions($domain);

        $this->getDomains();
        $this->getFranchises();
        return $this->response;
    }

    public function toEdit()
    {
        $user = $this->store->shopkeeper->user;
        $address = $this->store->address;

        $this->response['store'] = [
            "responsible_name" => $user->nome ?? '',
            "last_name" => $user->sobrenome ?? '',
            "login_mail" => $user->email ?? '',
            "responsible_phone" => $user->telefone ?? '',
            "phone" => $this->store->telefone,
            "domain_id" => $this->store->domain_id,
            "franchise_id" => $this->store->franchise_id,
            "responsible_email" => $this->store->shopkeeper->email_proprietario,
            "store_name" => $this->store->nome,
            "cnpj" => $this->store->cnpj,
            "type_store" => $this->store->type,
            "corporate_name" => $this->store->corporate_name,
            "commission" => $this->store->comissao,
            "categories" => $this->store->categories_store()->where('is_primary', 1)->pluck('categoria_id'),
            "is_test" => $this->store->getSetting('is_test', 0),
            "offline_commission" => $this->store->getSetting('offline_commission', 0),
            "external_link" => $this->store->getSetting('external_link'),
            "address" => $address->endereco ?? '',
            "number" => $address->numero ?? '',
            "district" => $address->bairro ?? '',
            "city" => $address->cidade ?? '',
            "postal_code" => $address->cep ?? '',
            "complement" => $address->complemento ?? '',
            "state" => $address->state ?? '',
            "latitude" => $address->latitude ?? '',
            "longitude" => $address->longitude ?? '',
            "image" => $this->store->imagem_s3,
            "logo" => $this->store->logo_store,
            "slug" => $this->store->reference_id["slug"] ?? null,
            "network_id" => $this->store->shopkeeper->reference_id,
            "network_slug" => $this->store->shopkeeper->reference_provider,
            "is_market" => $this->store->isMarket(),
            'deeplink' => $this->store->link()
        ];

        $inRegister = in_array($this->store->status, ['PRE_ACTIVATED', 'PRE_ACTIVATED_ANALYZE', 'PRE_ACTIVATED_ADMIN']);
        $this->response['can_modify_commission'] = $inRegister || $this->user->hasPermission('can_modify_commission');
    }

    private function getDomains()
    {
        $domains = Cache::remember("store.domains", 86400, function () {
            return Domain::query()->selectRaw('id, title')->get();
        });

        if (!empty($this->store) || !$this->user->hasAdminPrivileges()) {
            $domains = $domains->where('id', $this->store->domain_id ?? currentDomain());
        }
        $this->response['domains'] = array_values($domains->toArray());
    }

    private function getFranchises()
    {
        if ($this->user->isFranchiseOperator()) {
            $franchises = Franchise::query();

            if (isset($this->user->getFranchise()->id)) {
                $franchises->where('id', $this->user->getFranchise()->id);
            }

            $this->response['franchises'] = $franchises->selectRaw('id, name')->get()->toArray();
        }
    }

    private function getTypeStoreOptions($domain)
    {
        $defaultOptions = collect([
            ["value" => "PARCEIRO_NORMAL", "text" => "Parceiro"],
            ["value" => "PARCEIRO_EXCLUSIVO", "text" => "Parceiro Exclusivo", 'exclusive' => true],
            ["value" => "PARCEIRO_MARKETPLACE_NORMAL", "text" => "Marketplace"],
            ["value" => "PARCEIRO_MARKETPLACE_EXCLUSIVO", "text" => "Marketplace Exclusivo", 'exclusive' => true],
            ["value" => "PARCEIRO_LOCAL_NORMAL", "text" => "Local"],
            ["value" => "PARCEIRO_LOCAL_EXCLUSIVO", "text" => "Local Exclusivo", 'exclusive' => true],
            ["value" => "FULLCOMMERCE", "text" => "Full Commerce"],
        ]);

        if (empty($this->store) || !$this->store->is_exclusive) {
            $defaultOptions = $defaultOptions->whereNull('exclusive');
        }

        return $domain->getSetting("type_store_options", $defaultOptions->values()->toArray());
    }
}