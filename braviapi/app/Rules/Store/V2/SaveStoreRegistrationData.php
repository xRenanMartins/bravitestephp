<?php

namespace App\Rules\Store\V2;

use App\Exceptions\GenericException;
use App\Utils\Files;
use Illuminate\Support\Str;
use Packk\Core\Actions\AmericanasMarket\CreateStoreAmericanasMarket;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Jobs\Admin\SendStoreLegiti;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\Address;
use Packk\Core\Models\Shopkeeper;
use Packk\Core\Models\Store;
use Packk\Core\Models\User;
use Packk\Core\Util\Phones;

class SaveStoreRegistrationData
{
    private Store $store;
    private Shopkeeper $shopkeeper;
    private User $shopkeeperUser;
    private array $payload;

    public function execute(array $payload)
    {
        $this->payload = $payload;

        if (isset($payload['shopkeeper_id'])) {
            $this->shopkeeper = Shopkeeper::with('user')->find($payload['shopkeeper_id']);
            $this->shopkeeperUser = $this->shopkeeper->user;
        } else {
            $this->saveUser();
            $this->saveShopkeeper();
            $this->shopkeeper->setOwnerRole();
        }

        $this->saveStore();
        $this->saveAddress();
        $this->saveCategories();
        $this->saveSettings();
        $this->saveImages();
        $this->savePhoneInSetting();

        $this->store->linkUserStore($this->shopkeeperUser->id);
        $this->store->activeDefaultPaymentMethods();

        if($this->store->isMarket() && $this->store->domain->hasFeature('capacity') && !isset($this->store->reference_id["store_id"])) {
            (new CreateStoreAmericanasMarket())->execute($this->store);
        }

        return $this->store;
    }

    private function saveStore()
    {
        $this->store = new Store();
        $this->store->shopkeeper()->associate($this->shopkeeper);
        $this->store->domain_id = currentDomain();
        $this->store->nome = $this->payload['store_name'];
        $this->store->telefone = $this->payload['phone'];
        $this->store->cnpj = preg_replace('/[^0-9]/', '', $this->payload['cnpj']);
        $this->store->type = $this->payload['type_store'];
        $this->store->corporate_name = $this->payload['corporate_name'];
        $this->store->comissao = $this->payload['commission'];
        $this->store->franchise_id = $this->payload['franchise_id'] ?? null;
        $this->store->modo_exibicao = 'L';
        $this->store->tipo_exibicao = 'L';
        $this->store->descricao = '';
        $this->store->modo_funcionamento = 'A';
        $this->store->status = 'PRE_ACTIVATED';
        $this->store->ordem = 9999;

        if ($this->payload['is_market']) {
            $storeSlug = $this->payload['store_slug'] ?? Str::slug($this->store->nome);
            if (str_contains($storeSlug, '/')) {
                $slug = explode('/', $storeSlug)[0];
                $this->store->setReferenceId('store_id', explode('/', $storeSlug)[1]);
            }
            $this->store->setReferenceId('slug', $slug ?? $storeSlug);
            $this->store->reference_provider = "AMERICANAS_MARKET";
            if (!$this->payload['is_test']) {
              $this->store->zoop_seller_id = $this->store->domain->getSetting('market_seller_id');
            }
        }

        $this->store->save();
        $this->store->wallet; // cria a wallet
    }

    private function saveUser()
    {
        $existsUser = User::where('tipo', 'L')->where('email', $this->payload['login_mail'])->exists();
        throw_if($existsUser, new CustomException('Já existe uma loja utilizando esse login'));

        $this->shopkeeperUser = new User();
        $this->shopkeeperUser->nome = $this->payload['responsible_name'];
        $this->shopkeeperUser->sobrenome = $this->payload['last_name'];
        $this->shopkeeperUser->domain_id = currentDomain();
        $this->shopkeeperUser->email = $this->payload['login_mail'];
        $this->shopkeeperUser->password = bcrypt($this->payload['password']);
        $this->shopkeeperUser->telefone = Phones::format($this->payload['responsible_phone']);
        $this->shopkeeperUser->tipo = 'L';
        $this->shopkeeperUser->save();
    }

    private function saveShopkeeper()
    {
        $this->shopkeeper = new Shopkeeper();
        $this->shopkeeper->user()->associate($this->shopkeeperUser);
        $this->shopkeeper->email_proprietario = $this->payload['responsible_email'];
        $this->shopkeeper->reference_provider = $this->payload['is_market'] ? $this->payload['network_slug']['slug'] : null;
        $this->shopkeeper->reference_id = $this->payload['is_market'] ? $this->payload['network_slug']['id'] : null;
        $this->shopkeeper->domain_id = currentDomain();
        $this->shopkeeper->generatePromotionalCode();
        $this->shopkeeper->save();
    }

    private function saveCategories()
    {
        $this->store->categories_store()->attach($this->payload['categories'], [
            'created_at' => now(),
            'updated_at' => now(),
            'domain_id' => currentDomain()
        ]);
    }

    private function saveSettings()
    {
        $this->store->setSetting('is_test', $this->payload['is_test']);
        $this->store->setSetting('external_link', $this->payload['external_link'] ?? null);
        if (isset($this->payload['offline_commission'])) {
            $this->store->setSetting('offline_commission', $this->payload['offline_commission']);
        }
        if($this->store->type == 'FULLCOMMERCE'){
            $this->store->setSetting("has_post_picking", true);
            $this->store->setSetting("has_weight_scale", true);
        }

        if (isset($this->payload['shopkeeper_id'])) {
            $this->store->setSetting('store_has_not_shopkeeper', 1);
        }

        $this->store->setDefaultSettings();
    }

    private function saveAddress()
    {
        $address = new Address();
        $address->store()->associate($this->store);
        $address->domain_id = currentDomain();
        $address->endereco = $this->payload['address'];
        $address->numero = $this->payload['number'] ?? 's/n';
        $address->bairro = $this->payload['district'];
        $address->cidade = $this->payload['city'];
        $address->cep = $this->payload['postal_code'];
        $address->complemento = $this->payload['complement'] ?? 's/c';
        $address->state = $this->payload['state'];
        $address->latitude = $this->payload['latitude'] ?? null;
        $address->longitude = $this->payload['longitude'] ?? null;

        if (empty($this->payload['latitude']) && empty($this->payload['longitude'])) {
            $latlng = Address::get_lat_lng("{$address->cidade}-{$address->state}, {$address->bairro}, {$address->cep}, {$address->endereco}, {$address->numero}");
            $address->latitude = $latlng['lat'];
            $address->longitude = $latlng['lng'];
        }

        $validateZone = $this->store->validateStoreInsideZone($address);
        if (!$validateZone['success']) {
            throw new GenericException($validateZone['message'], 400);
        }
        $address->zone_id = $validateZone['id'];
        $address->save();
        $this->store->updateLocation();
    }

    public function saveImages()
    {
        if (!empty($this->payload['image'])) {
            $this->store->imagem_s3 = Files::save($this->payload['image'], "stores/{$this->store->id}/", 'cover');
            $this->store->imagem = $this->store->imagem_s3;
        }

        if (!empty($this->payload['logo'])) {
            $this->store->logo_store = Files::save($this->payload['logo'], "stores/{$this->store->id}/", 'logo');
        }
    }

    private function savePhoneInSetting()
    {
        $setting = $this->store->getSetting('store_numbers');
        if (empty($setting)) {
            $setting = [];
        }

        $phone = Phones::format($this->store->telefone);
        $setting[] = [
            "type" => strlen($phone) > 11 ? "mobile" : "fixed",
            "value" => $phone,
            "whatsapp" => false
        ];
        $this->store->setSetting('store_numbers', $setting);
    }
}