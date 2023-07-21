<?php

namespace App\Rules\Store\V2;

use App\Exceptions\GenericException;
use App\Utils\Files;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Packk\Core\Actions\AmericanasMarket\CreateStoreAmericanasMarket;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\Address;
use Packk\Core\Models\Shopkeeper;
use Packk\Core\Models\Store;
use Packk\Core\Models\StoreActivity;
use Packk\Core\Models\User;
use Packk\Core\Util\Phones;

class UpdateStoreRegistrationData
{
    private $store;
    private Shopkeeper $shopkeeper;
    private array $payload;
    private $updateFields;

    public function __construct()
    {
        $this->updateFields = collect([]);
    }

    public function execute($storeId, array $payload)
    {
        $this->store = Store::with(['address', 'shopkeeper.user'])->findOrFail($storeId);
        $this->payload = $payload;

        $this->updateImages();
        $this->updateShopkeeper();
        $this->updateUser();
        $this->updateAddress();
        $this->updateCategories();
        $this->updateSettings();
        $this->updateStore();
    }

    private function updateStore(): void
    {
        if (isset($this->payload['store_name'])) {
            $this->store->nome = $this->payload['store_name'];
            $this->updateFields->push('name');
        }
        if (isset($this->payload['phone'])) {
            $this->store->telefone = $this->payload['phone'];
        }
        if (isset($this->payload['type_store'])) {
            $this->store->type = $this->payload['type_store'];
            $this->updateFields->push('type');
        }
        if (isset($this->payload['corporate_name'])) {
            $this->store->corporate_name = $this->payload['corporate_name'];
        }
        if (isset($this->payload['commission'])) {
            $storeActivity              = new StoreActivity();
            $storeActivity->user_id     = Auth::id();
            $storeActivity->store_id    = $this->store->id;
            $storeActivity->description = "Comissão alterada de {$this->store->comissao} para {$this->payload['commission']}%";
            $storeActivity->activity    = 'ALTERAR_COMISSAO';
            $storeActivity->save();

            $this->store->comissao = $this->payload['commission'];
        }

        /// Mercado
        if (isset($this->payload['is_market'])) {
            if ($this->payload['is_market']) {
                $this->store->reference_provider = "AMERICANAS_MARKET";

                if (isset($this->payload['store_slug'])) {
                    $storeSlug = $this->payload['store_slug'] ?? Str::slug($this->store->nome);
                    if (str_contains($storeSlug, '/')) {
                        $slug = explode('/', $storeSlug)[0];
                        $this->store->setReferenceId('store_id', explode('/', $storeSlug)[1]);
                    }
                    $this->store->setReferenceId('slug', $slug ?? $storeSlug);

                    if($this->store->domain->hasFeature('capacity') && !isset($this->store->reference_id["store_id"])) {
                        (new CreateStoreAmericanasMarket())->execute($this->store);
                    }
                }
                if (!$this->store->getSetting('is_test')) {
                    $this->store->zoop_seller_id = $this->store->domain->getSetting('market_seller_id');
                }
            } else {
                if(!empty($this->store->reference_provider) && !$this->store->getSetting('is_test')) {
                  $this->store->zoop_seller_id = null;
                }
                $this->store->reference_provider = null;
            }
        } elseif (isset($this->payload['is_test']) && $this->store->isMarket()){
            $this->store->zoop_seller_id = !$this->payload['is_test']
              ? $this->store->domain->getSetting('market_seller_id')
              : null;
        }

        if (isset($this->payload['cnpj'])) {
            $this->store->cnpj = preg_replace('/[^0-9]/', '', $this->payload['cnpj']);
            if ($this->store->isMarket()) {
                $this->store->setReferenceId('document', $this->store->cnpj);
            }
        }

        if ($this->store->isDirty()) {
            $this->savePhoneInSetting();
            $this->store->save();
        }
    }

    private function updateUser(): void
    {
        $shopkeeperUser = $this->store->shopkeeper->user;
        if (empty($shopkeeperUser)) {
            $shopkeeperUser = new User();
            $this->shopkeeper->user()->associate($shopkeeperUser);
        }

        if (isset($this->payload['responsible_name'])) {
            $shopkeeperUser->nome = $this->payload['responsible_name'];
        }
        if (isset($this->payload['last_name'])) {
            $shopkeeperUser->sobrenome = $this->payload['last_name'];
        }
        if (isset($this->payload['login_mail'])) {
            $shopkeeperUser->email = $this->payload['login_mail'];
        }
        if (isset($this->payload['password'])) {
            $shopkeeperUser->password = bcrypt($this->payload['password']);
        }
        if (isset($this->payload['responsible_phone'])) {
            $shopkeeperUser->telefone = Phones::format($this->payload['responsible_phone']);
        }

        if ($shopkeeperUser->isDirty()) {
            $shopkeeperUser->save();
            $this->shopkeeper->generatePromotionalCode();

            if ($this->shopkeeper->isDirty()) {
                $this->shopkeeper->save();
            }
        }
    }

    private function updateShopkeeper(): void
    {
        $this->shopkeeper = $this->store->shopkeeper;
        if (isset($this->payload['responsible_email'])) {
            $this->shopkeeper->email_proprietario = $this->payload['responsible_email'];
        }
        if (isset($this->payload['network_slug'])) {
            $this->shopkeeper->reference_id = $this->payload['network_slug']['id'];
            $this->shopkeeper->reference_provider = $this->payload['network_slug']['slug'];
        }

        if ($this->shopkeeper->isDirty()) {
            $this->shopkeeper->save();
        }
    }

    private function updateCategories(): void
    {
        if (isset($this->payload['new_categories'])) {
            $this->store->categories_store()->attach($this->payload['new_categories'], [
                'created_at' => now(),
                'updated_at' => now(),
                'domain_id' => currentDomain()
            ]);
            $this->updateFields->push('category');
        }

        if (isset($this->payload['remove_categories'])) {
            $this->store->categories_store()->detach($this->payload['remove_categories']);
            $this->updateFields->push('category');
        }
    }

    private function updateSettings(): void
    {
        if (isset($this->payload['is_test'])) {
            $this->store->setSetting('is_test', $this->payload['is_test']);
        }
        if (isset($this->payload['offline_commission'])) {
            $this->store->setSetting('offline_commission', $this->payload['offline_commission']);
        }
        if (isset($this->payload['external_link'])) {
            $this->store->setSetting('external_link', $this->payload['external_link']);
        }

        if($this->store->type == 'FULLCOMMERCE'){
            $this->store->setSetting("has_post_picking", true);
            $this->store->setSetting("has_weight_scale", true);
        }
      Cache::forget("store.{$this->store->id}.settings");

    }

    private function updateAddress(): void
    {
        $address = $this->store->address;
        if (empty($address)) {
            $address = new Address();
            $address->store()->associate($this->store);
        }

        if (isset($this->payload['address'])) {
            $address->endereco = $this->payload['address'];
        }
        if (isset($this->payload['number'])) {
            $address->numero = $this->payload['number'] ?? 's/n';
        }
        if (isset($this->payload['district'])) {
            $address->bairro = $this->payload['district'];
        }
        if (isset($this->payload['city'])) {
            $address->cidade = $this->payload['city'];
        }
        if (isset($this->payload['postal_code'])) {
            $address->cep = $this->payload['postal_code'];
        }
        if (isset($this->payload['complement'])) {
            $address->complemento = $this->payload['complement'] ?? 's/c';
        }
        if (isset($this->payload['state'])) {
            $address->state = $this->payload['state'];
        }
        if (isset($this->payload['latitude']) || $address->isDirty()) {
            $address->latitude = $this->payload['latitude'] ?? '';
        }
        if (isset($this->payload['longitude']) || $address->isDirty()) {
            $address->longitude = $this->payload['longitude'] ?? '';
        }

        if (empty($address->latitude) && empty($address->longitude)) {
            $latlng = Address::get_lat_lng("{$address->cidade}-{$address->state}, {$address->bairro}, {$address->cep}, {$address->endereco}, {$address->numero}");
            $address->latitude = $latlng['lat'];
            $address->longitude = $latlng['lng'];
        }

        if ($address->isDirty()) {
            $validateZone = $this->store->validateStoreInsideZone($address);
            if (!$validateZone['success']) {
                throw new GenericException($validateZone['message'], 400);
            }

            $address->zone_id = $validateZone['id'];
            $address->save();
            $this->store->updateLocation();
            $this->updateFields->push('service_area');
        }
    }

    public function updateImages(): void
    {
        if (isset($this->payload['image'])) {
            $this->store->imagem_s3 = Files::save($this->payload['image'], "stores/{$this->store->id}/", 'cover');
            $this->store->imagem = $this->store->imagem_s3;
            $this->updateFields->push('images');
        }

        if (isset($this->payload['logo'])) {
            $this->store->logo_store = Files::save($this->payload['logo'], "stores/{$this->store->id}/", 'logo');
            $this->updateFields->push('images');
        }
    }

    public function getChanges(): array
    {
        return $this->updateFields->toArray();
    }

    private function savePhoneInSetting()
    {
        if ($this->store->isDirty('telefone')) {
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
}