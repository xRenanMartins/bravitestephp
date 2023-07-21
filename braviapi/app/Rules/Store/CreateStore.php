<?php

namespace App\Rules\Store;

use App\Jobs\SyncCategoryFreeShipp;
use App\Utils\Files;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Address;
use Packk\Core\Models\Role;
use Packk\Core\Models\Store;
use Packk\Core\Models\Segment;
use Packk\Core\Models\Retention;
use Packk\Core\Models\User;
use Packk\Core\Models\AreaServed;
use Packk\Core\Models\Shopkeeper;
use Packk\Core\Models\Domain;
use Packk\Core\Jobs\ZoopMailSender;
use App\Rules\Mail\NewStore;
use Packk\Core\Util\Formatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Packk\Core\Integration\Legiti\Delivery\SellerLegiti;
use Packk\Core\Integration\Mobne\Company as MobneCompany;
use App\Rules\Store\SavePaymentMethod;
use Carbon\Carbon;
use Packk\Core\Util\Phones;

class CreateStore
{
    protected $store;
    protected $payload;
    protected $domain;

    public function execute($payload)
    {
        $this->payload = $payload;
        try {
            DB::beginTransaction();
            $this->domain = currentDomain(true);

            if (isset($payload->shopkeeper_id)) {
                $shopkeeper = Shopkeeper::with('user')->find($payload->shopkeeper_id);
            }
            $shopkeeperIsNew = !isset($shopkeeper->user);

            if($shopkeeperIsNew) {
                // cria usuario
                $user = new User();
                $user->nome = $this->payload->nomelojista;
                $user->sobrenome = $this->payload->sobrenome;
                $user->telefone = Phones::format($this->payload->telefone_lojista);
                $user->email = $this->payload->email;
                $user->password = bcrypt($this->payload->senha);
                $user->domain_id = $this->domain->id;
                $user->tipo = 'L';
                $user->cpf = $this->payload->cpf ?? null;

                try {
                    $user->save();

                    $role = Role::where("name", "owner")->first();
                    $user->roles()->syncWithoutDetaching([$role->id => [
                        'created_at' => now(),
                        'updated_at' => now()
                    ]]);
                } catch (\Exception $e) {
                    Log::error($e);
                    throw new \Exception('Falha ao criar usuário verifique se o login já esta em uso', 1, $e);
                }

                if (isset($this->payload->imagemlojista)) {
                    $user->foto_perfil_s3 = Files::saveFromBase64($this->payload->imagemlojista, "user/{$user->id}/profile/");
                    $user->foto_perfil = $user->foto_perfil_s3;
                    $user->save();
                }

                // cria lojista
                $shopkeeper = new Shopkeeper();
                $shopkeeper->user()->associate($user);
                $shopkeeper->email_proprietario = $this->payload->email_proprietario ?? '';
                $shopkeeper->aceite_sem_entregador = $this->payload->aceite_sem_entregador ?? false;
                $shopkeeper->senha_extrato = isset($this->payload->senha_extrato) ? Hash::make($this->payload->senha_extrato) : null;
                $shopkeeper->generatePromotionalCode();
                $shopkeeper->save();
            } else {
                $user = $shopkeeper->user;
            }

            // cria loja
            $this->store = new Store();
            $this->store->modo_exibicao = $this->payload->modo_exibicao ?? "L";
            $this->store->nome = trim($this->payload->nomeloja);
            $this->store->descricao = $this->payload->descricao ?? "";
            $this->store->dinheiro_ativo = isset($this->payload->aceita_dinheiro);
            $this->store->ordem = Auth::user()->hasRole('master|manager-strategy') ? $this->payload->ordem : 999;
            $this->store->ativo = false;
            $this->store->tipo_exibicao = $this->payload->tipo_exibicao;
            $this->store->telefone = $this->payload->telefone_loja;
            $this->store->modo_funcionamento = 'A';
            $this->store->comissao = $this->payload->comissao;
            $this->store->parceiro = true;
            $this->store->type = $this->payload->type_store ?? "PARCEIRO_NORMAL";
            $this->store->tax_model = $this->payload->regime_tributario ?? "NONE";
            $this->store->raio_min = !empty($this->payload->raio_min) && !Str::contains($this->store->type, 'MARKETPLACE') ? $this->payload->raio_min : null;
            $this->store->user_code_indicator = !empty($this->payload->codigo_indicacao) ? $this->payload->codigo_indicacao : null;
            $this->store->created_by = auth()->id();
            $this->store->franchise_id = $this->payload->franchise_id ?? null;
            $this->store->corporate_name = $this->payload->corporate_name;

            $this->store->shopkeeper()->associate($shopkeeper);
            $this->store->save();
            $this->store->wallet;
            $cnpj = $this->limpaEin($this->payload->cnpj);

            if ($this->store->cnpj != $cnpj && !empty($cnpj)) {
                $this->store->cnpj = $cnpj;
                $this->store->zoop_seller_id = null;
            }

            if (isset($this->payload->imagemloja) && !empty($this->payload->imagemloja)) {
                $this->store->imagem_s3 = Files::saveFromBase64($this->payload->imagemloja, "stores/{$this->store->id}/");
                $this->store->imagem = $this->store->imagem_s3;
            }

            if (isset($this->payload->imagemlogo) && !empty($this->payload->imagemlogo)) {
                $this->store->logo_store = Files::saveFromBase64($this->payload->imagemlogo, "stores/{$this->store->id}/logo/");
            }

            if (isset($this->payload->segmento_loja)) {
                $this->store->segment()->associate(Segment::find($this->payload->segmento_loja));
            }

            $this->store->domain_id = $this->domain->id;
            $this->store->save();
            $this->store->categories_store()->attach($this->payload->categoria_loja, [
                'created_at' => now(),
                'updated_at' => now(),
                'domain_id' => $this->store->domain_id
            ]);

            $this->storeSettings();
//            UpdateSettingsStore::execute($this->payload, $this->store);

            try {
                if (isset($this->payload->codigo_indicacao)) {
                    $c = Customer::where('codigo_indicacao', $this->payload->codigo_indicacao)->firstOrFail();
                    $this->store->customer_indicador()->associate($c);
                }
            } catch (\Exception $exception) {
            }

            // cria endereço
            $address = new Address();
            $address->endereco = $this->payload->estabelecimento_endereco;
            $address->numero = $this->payload->estabelecimento_numero;
            $address->bairro = $this->payload->estabelecimento_bairro;
            $address->cidade = $this->payload->estabelecimento_cidade;
            $address->cep = $this->payload->estabelecimento_cep;
            $address->complemento = $this->payload->estabelecimento_complemento;
            $address->state = $this->payload->estabelecimento_estado ?? null;
            $address->latitude = !empty($this->payload->estabelecimento_latitude) ? str_replace(",", ".", $this->payload->estabelecimento_latitude) : null;
            $address->longitude = !empty($this->payload->estabelecimento_longitude) ? str_replace(",", ".", $this->payload->estabelecimento_longitude) : null;
            $address->store()->associate($this->store);
            $address = $this->getLatLng($address);
            $address->save();
            $this->updateLocation();

            $isMarketplace = in_array($this->store->type, ['PARCEIRO_MARKETPLACE_NORMAL', 'PARCEIRO_MARKETPLACE_EXCLUSIVO', 'NAO_PARCEIRO']);
            if (!isLocalEnv() && empty($this->payload->franchise_id) && !$isMarketplace && !AreaServed::checkAttendanceZone($address->latitude, $address->longitude, 'COLETA')) {
                throw new \Exception('O endereço está fora de uma zona de atendimento', 1);
            }

            if (!empty($this->payload->franchise_id) && !AreaServed::checkFranchiseZone($this->payload->franchise_id, $address->latitude, $address->longitude)) {
                throw new \Exception('Endereço fora da zona de atendimento da franquia', 1);
            }

            $this->store->save();
            if (!$shopkeeperIsNew) {
                $this->store->setSetting('store_has_not_shopkeeper', 1);
            }

            // faz o mapping da loja com a mobne
            if (isset($this->payload->mobne_external_id)) {
                $mobneCompany = new MobneCompany($this->payload->mobne_external_id);
                $this->store->linkExt($mobneCompany);
            }

            // Habilita wallet
            $this->store->setSetting('has_wallet', true);

            $this->store->users()->syncWithoutDetaching([$user->id => [
                "domain_id" => $this->store->domain_id,
                'created_at' => now(),
                'updated_at' => now()
            ]]);

            // Salvar formas de pagamento
            try {
                SavePaymentMethod::execute((array)$this->payload->form_payment, $this->store, false, $this->payload->type_store);
            } catch (\Exception $e) {
                throw new \Exception('Houve um erro ao tentar salvar as formas de pagamento', 1);
            }

            DB::commit();

            $this->sendEmail();
            $this->dispatchNearEvents();

            if ($this->store->membershipp_fee) {
                $taxa_de_adesao = 9990;
                Retention::lancar_retencao_para_loja($this->store, $taxa_de_adesao, 'Retenção por taxa de adesão', null, "ADESAO", $this->store);
            }

            $domain = Domain::find($this->store->domain_id);
            if ($domain->hasFeature('legiti')) {
                $sellerLegiti = new SellerLegiti();
                $sellerLegiti->setData($this->store, $this->payload->categoria_loja);
                $sellerLegiti->setAddress($address);
                $sellerLegiti->setToken($domain);
                $sellerLegiti->create();
            }

            return ['message' => 'Loja Criada Com Sucesso'];
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    private function storeSettings()
    {
        $this->store->setSetting("is_test", $this->payload->is_test);

        if (isset($this->payload->recebe_apos)) {
            $this->store->setSetting("receive_after", $this->payload->recebe_apos);
        }

        if (isset($this->payload->comissao_offline)) {
            $this->store->setSetting('offline_commission', $this->payload->comissao_offline);
        }

        // Settings da zaitt
        // if ($this->domain->hasFeature('zaittStores')) {
        //     $store_type_id = $this->payload->store_type_zaitt ?? "honest_market";
        //     $this->store->setSetting('type_zaitt', $store_type_id);
        //     $this->store->setSetting('is_physical_open', 1);
        //     $this->store->setSetting('temperature', 0);
        //     $this->store->setSetting('siren', 0);

        //     if (
        //         $this->payload->gerar_etiqueta == 'paper_label' ||
        //         !isset($this->payload->gerar_etiqueta) ||
        //         ($this->payload->gerar_etiqueta == 'endpoint_eletronic_label' &&
        //             empty($this->payload->endpoint_eletronic_label)
        //         )
        //     ) {
        //         $this->store->setSetting("paper_label", 1);
        //         $this->store->setSetting("endpoint_eletronic_label", null);
        //     } elseif ($this->payload->gerar_etiqueta == 'endpoint_eletronic_label') {
        //         $this->store->setSetting("paper_label", 0);
        //         $this->store->setSetting("endpoint_eletronic_label", $this->payload->endpoint_eletronic_label ?? null);
        //     }

        //     if (isset($this->payload->metabase)) {
        //         $this->store->setSetting("metabase", $this->payload->metabase);
        //     }
        //
        //     if (isset($this->payload->allow_edit_price)) {
        //         $this->store->setSetting("allow_edit_price", $this->payload->allow_edit_price);
        //     }
        //     $this->store->setSetting("takeout_local_automatic_complete", 1);
        //     $this->store->setSetting("store_products_qrcode", 1);
        //     $this->store->setSetting("store_qrcode", 1);
        //     $this->store->setSetting("store_door_id", $this->store->id);
        // }

    }


    private function sendEmail()
    {
        if ($this->store->is_partner) {
            try {
                if (env('PROD_EC', '0') == '1') {
                    dispatch(new ZoopMailSender(new NewStore($this->store)));
                }
            } catch (\Exception $exception) {
            }
        }
    }

    private function updateLocation()
    {
        try {
            DB::update('update enderecos set localizacao = POINT(latitude,longitude) where loja_id = ? and domain_id = ?', [$this->store->id, $this->store->domain_id]);
        } catch (\Exception) {
        }
    }

    private function limpaEin($addressin)
    {
        $doc = str_replace("-", "", $addressin);
        $doc = str_replace(".", "", $doc);
        $doc = str_replace("/", "", $doc);
        $doc = str_replace(" ", "", $doc);
        return $doc;
    }

    private function formatValueMoney($value)
    {
        $value = str_replace('R$ ', '', $value);
        $value = str_replace('.', '', $value);
        return str_replace(',', '.', $value);
    }

    private function getLatLng($address)
    {
        try {
            if (empty($address->latitude) || empty($address->longitude)) {
//                $latlng = Address::get_lat_lng("{$address->cidade}, {$address->bairro}, {$address->cep}, {$address->endereco}, {$address->numero}");
                $latlng = Address::get_lat_lng("{$address->endereco}, {$address->numero}, {$address->bairro}, {$address->cidade}, {$address->state}, {$address->cep}");
                $address->latitude = $latlng['lat'];
                $address->longitude = $latlng['lng'];
                if (empty($address->latitude) || empty($address->latitude)) {
                    throw new \Exception('Não conseguimos obter as coordenadas desta loja');
                }
            }
            return $address;
        } catch (\Exception) {
            throw new \Exception('Tivemos um problema durante a busca das coordenadas da loja');
        }
    }

    private function formatCents($value)
    {
        return Formatter::value($value) * 100;
    }

    private function dispatchNearEvents()
    {
        dispatch(new SendShopFeedEvent($this->store->id, 'store.create'));
    }
}
