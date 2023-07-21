<?php

namespace App\Rules\Store;

use App\Jobs\SyncCategoryFreeShipp;
use App\Utils\Files;
use Illuminate\Support\Facades\Auth;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\AreaServed;
use Packk\Core\Models\Schedule;
use Packk\Core\Models\Shopkeeper;
use Packk\Core\Models\Store;
use Packk\Core\Models\Address;
use Packk\Core\Models\Segment;
use Packk\Core\Integration\Payment\Seller;
use Packk\Core\Models\User;
use Packk\Core\Util\Formatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Rules\Store\SavePaymentMethod;
use Packk\Core\Integration\Legiti\Delivery\SellerLegiti;
use Packk\Core\Integration\Mobne\Company as MobneCompany;
use Carbon\Carbon;
use Packk\Core\Util\Phones;

class UpdateStore
{
    protected $store;
    protected $payload;
    protected $domain;

    public function execute($payload)
    {
        $this->payload = $payload;
        try {
            DB::beginTransaction();
            $this->store = Store::with('domain')->find($this->payload->id);
            $shopkeeper = Shopkeeper::query()->find($this->store->lojista_id);
            $user = User::query()->find($shopkeeper->user_id);
            $address = $this->store->addresses->first();

            // atualiza usuario
            $user->nome = $this->payload->nomelojista;
            $user->sobrenome = $this->payload->sobrenome;
            $user->telefone = Phones::format($this->payload->telefone_lojista);
            $user->email = $this->payload->email;
            $user->cpf = $this->payload->cpf ?? null;

            if (isset($this->payload->senha) && !empty($this->payload->senha)) {
                $user->password = bcrypt($this->payload->senha);
            }
            if (isset($this->payload->senha_extrato) && $this->payload->senha_extrato != '') {
                $shopkeeper->senha_extrato = Hash::make($this->payload->senha_extrato);
                $this->store->setSetting("extract_block", true);
            }
            $user->save();

            if (isset($this->payload->imagemlojista) && !empty($this->payload->imagemlojista)) {
                $user->foto_perfil_s3 = Files::saveFromBase64($this->payload->imagemlojista, "users/{$user->id}/profile");
                $user->foto_perfil = $user->foto_perfil_s3;
                $user->save();
            }
            $userAuth = Auth::user();

            // cria loja
            $this->store->nome = trim($this->payload->nomeloja);
            $this->store->descricao = $this->payload->descricao ?? $this->store->descricao;
            $this->store->dinheiro_ativo = isset($this->payload->aceita_dinheiro);
            $this->store->telefone = $this->payload->telefone_loja;

            $inRegister = in_array($this->store->status, ['PRE_ACTIVATED', 'PRE_ACTIVATED_ANALYZE', 'PRE_ACTIVATED_ADMIN']);
            if ($inRegister || $userAuth->isFranchiseOperator() || $userAuth->hasRole($this->store->domain->getSetting('can_modify_commission', 'master'))) {
                $this->store->comissao = $this->payload->comissao;
            }
            $this->store->type = $this->payload->type_store ?? $this->store->type;
            $this->store->user_code_indicator = $this->payload->codigo_indicacao ?? null;
            $this->store->tipo_checkout = isset($this->payload->parceiro) ? "PARCEIRO" : "NAO_PARCEIRO";
            $this->store->save();
            $this->store->wallet;

            if (isset($this->store->schedule)) {
                $this->store->schedule->is_scheduling = $this->payload->is_scheduling ?? false;
                $this->store->schedule->save();
            } else {
                Schedule::create([
                    "store_id" => $this->store->id,
                    "is_scheduling" => $this->payload->is_scheduling ?? false,
                ]);
            }

            $this->store->corporate_name = $this->payload->corporate_name;
            $this->domain = $this->store->domain;

            $this->updateSettings();
            UpdateSettingsStore::execute($this->payload, $this->store);

            // Salvar formas de pagamento
            try {
                SavePaymentMethod::execute((array)$this->payload->form_payment, $this->store, true, $this->payload->type_store);
            } catch (\Exception $e) {
                throw new \Exception('Houve um erro ao tentar salvar as formas de pagamento');
            }

            if (isset($this->payload->segmento_loja)) {
                $this->store->segment()->associate(Segment::find($this->payload->segmento_loja));
            }
            if (isset($this->payload->imagemloja) && !empty($this->payload->imagemlojaName)) {
                $this->store->imagem_s3 = Files::saveFromBase64($this->payload->imagemloja, "stores/{$this->store->id}/");
                $this->store->imagem = $this->store->imagem_s3;
            }

            if (isset($this->payload->imagemlogo) && !empty($this->payload->imagemlogoName)) {
                $this->store->logo_store = Files::saveFromBase64($this->payload->imagemlogo, "stores/{$this->store->id}/logo/");
            }

            $cnpj = $this->limpaEin($this->payload->cnpj);
            if ($this->store->cnpj != $cnpj && !empty($cnpj)) {
                $this->store->cnpj = $cnpj;
            }
            $this->store->save();

            DB::transaction(function () {
                DB::table('categoria_loja')
                    ->join('categorias', 'categoria_loja.categoria_id', '=', 'categorias.id')
                    ->where('categoria_loja.loja_id', $this->store->id)
                    ->where('categorias.is_primary', 1)->delete();

                $this->store->categories_store()->attach($this->payload->categoria_loja, [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'domain_id' => $this->store->domain_id
                ]);
            }, 3);

            // faz o mapping da loja com a mobne
            $externalCompany = $this->store->ext(MobneCompany::class);

            if (isset($externalCompany->id) && $externalCompany->id != ($this->payload->mobne_external_id ?? null)) {
                $this->store->unlinkExt($externalCompany);
            }

            if (isset($this->payload->mobne_external_id)) {
                if ((isset($externalCompany->id) && $externalCompany->id != $this->payload->mobne_external_id) || empty($externalCompany->id)) {
                    $mobneCompany = new MobneCompany($this->payload->mobne_external_id);
                    $this->store->linkExt($mobneCompany);
                }
            }

            $address->endereco = $this->payload->estabelecimento_endereco;
            $address->numero = $this->payload->estabelecimento_numero;
            $address->bairro = $this->payload->estabelecimento_bairro;
            $address->cidade = $this->payload->estabelecimento_cidade;
            $address->cep = $this->payload->estabelecimento_cep;
            $address->complemento = $this->payload->estabelecimento_complemento;
            $address->state = $this->payload->estabelecimento_estado ?? null;
            $address->latitude = !empty($this->payload->estabelecimento_latitude) ? str_replace(",", ".", $this->payload->estabelecimento_latitude) : null;
            $address->longitude = !empty($this->payload->estabelecimento_longitude) ? str_replace(",", ".", $this->payload->estabelecimento_longitude) : null;
            $address = $this->getLatLng($address);
            $address->save();

            $isMarketplace = in_array($this->store->type, ['PARCEIRO_MARKETPLACE_NORMAL', 'PARCEIRO_MARKETPLACE_EXCLUSIVO', 'NAO_PARCEIRO']);
            if (empty($this->payload->franchise_id) && !$isMarketplace && !AreaServed::checkAttendanceZone($address->latitude, $address->longitude, 'COLETA')) {
                throw new \Exception('O endereço está fora de uma zona de atendimento', 1);
            }

            if (!empty($this->payload->franchise_id) && !AreaServed::checkFranchiseZone($this->payload->franchise_id, $address->latitude, $address->longitude)) {
                throw new \Exception('Endereço fora da zona de atendimento da franquia', 1);
            }

            $this->updateLocation();
            DB::commit();

            $this->dispatchNearEvents();

            if ($this->domain->hasFeature('legiti')) {
                $sellerLegiti = new SellerLegiti();
                $sellerLegiti->setData($this->store);
                $sellerLegiti->setAddress($address);
                $sellerLegiti->setToken($this->domain);
                $sellerLegiti->update();
            }

            return ['message' => 'Loja Atualizada Com Sucesso'];
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    private function updateSettings()
    {
//        $this->store->setSetting("early_access", 1);
        $this->store->setSetting("is_test", $this->payload->is_test);

        if (isset($this->payload->comissao_offline)) {
            $this->store->setSetting('offline_commission', $this->payload->comissao_offline);
        }

        if (isset($this->payload->recebe_apos)) {
            $this->store->setSetting("receive_after", $this->payload->recebe_apos);
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

        //     if (isset($this->payload->allow_edit_price)) {
        //         $this->store->setSetting("allow_edit_price", $this->payload->allow_edit_price);
        //     }
        //     $this->store->setSetting("takeout_local_automatic_complete", 1);
        //     $this->store->setSetting("store_products_qrcode", 1);
        //     $this->store->setSetting("store_qrcode", 1);
        //     $this->store->setSetting("store_door_id", $this->store->id);
        // }

    }


    private function updateLocation()
    {
        try {
            DB::update('update enderecos set localizacao = POINT(latitude,longitude) where loja_id = ? and domain_id = ?', [$this->store->id, $this->store->domain_id]);
        } catch (\Exception) {
        }
    }

    private function rollBackZoopSeller($id)
    {
        $bs = new Seller($id);
        $bs->destroy();
    }

    private function limpaEin($addressin)
    {
        $doc = str_replace("-", "", $addressin);
        $doc = str_replace(".", "", $doc);
        $doc = str_replace("/", "", $doc);
        $doc = str_replace(" ", "", $doc);
        return $doc;
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
        dispatch(new SendShopFeedEvent($this->store->id, 'store.update'));
    }
}
