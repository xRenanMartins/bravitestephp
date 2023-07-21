<?php

namespace App\Rules\Customer;

use Packk\Core\Models\Customer;
use Packk\Core\Models\CustomerJourney;
use Packk\Core\Models\Order;
use Packk\Core\Models\UserActivity;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Util\Phones;

class GetEditCustomer
{
    public function execute($id, $isUnBanForm)
    {
        try {
            $data = Customer::withoutGlobalScope(DomainScope::class)->findOrFail($id);

            $response = $data->toArray();
            $user = $data->user;
            throw_if(empty($user), new \Exception('O usuário vinculado a este cliente está excluído. Por isso não é possível realizar a edição.'));

            $resp = array_merge($response, [
                "nome" => $user->nome,
                "sobrenome" => $user->sobrenome,
                "acom_id" => $user->acom_token,
                "borned_at" => $user->borned_at,
                "foto_perfil" => $user->foto_perfil,
                "cpf" => $user->cpf,
                "blacklist" => $user->blacklist,
                "email" => $user->email,
                "telefone" => empty($user->telefone) ? null : Phones::format($user->telefone),
                "status" => $user->status,
                "creditos" => $data->get_credits()
            ]);

            if ($isUnBanForm) {
                // Formulário para desbanir cliente
                try {
                    $resp['ban_desc'] = $data->getBanReason->descricao;
                } catch (\Throwable $th) {
                    $resp['ban_desc'] = "";
                }
                $resp['created_at'] = $data->created_at->format('d/m/Y H:i');

                $banActivities = UserActivity::select('user_activities.*', 'atividades.flag')
                    ->join('atividades', 'atividades.id', 'user_activities.atividade_id')
                    ->where('user_id', $user->id)
                    ->whereIn('flag', ['BANNED_USER', 'UNBANNED_USER'])
                    ->orderByDesc('user_activities.created_at')
                    ->limit(4)
                    ->get();

                $bans = [];
                foreach ($banActivities as $item) {
                    $temp = $item->toArray();
                    $temp['created_at'] = $item->created_at->format('d/m/Y H:i');
                    $bans[] = $temp;
                }

                $resp = [
                    'client' => $resp,
                    'fraudulentData' => $data->getFraudulentOrders(),
                    'banActivities' => $bans,
                ];
            }

            if ($data->domain_id !== 1) {
                if ($user->status == "EM_ANALISE") {
                    $suspect_user_activities = $user->activities()->select('user_activities.context', 'user_activities.created_at')
                        ->where("atividades.flag", '=', 'SUSPECT_USER')->get();
                }

                $resp['suspect_user_activities'] = $suspect_user_activities ?? [];
                $resp['orders_finished'] = Order::query()
                    ->where("domain_id", $data->domain_id)
                    ->where("cliente_id", $data->id)
                    ->where('estado', 'F')
                    ->count();

                $reasonBlacklist = $user->activities()->select("user_activities.context")->where("atividades.flag", "USER_BLACKLIST")->first();
                $resp['motivo_blacklist'] = $reasonBlacklist->context ?? null;
            }

            if ($data->domain->getSetting("check_datavalid", false)) {
                $datavalid = UserActivity::select('user_activities.context as context_datavalid', 'atividades.flag as flag_datavalid')
                    ->join("atividades", "atividades.id", "=", "user_activities.atividade_id")
                    ->where("user_activities.user_id", $user->id)
                    ->whereIn("atividades.flag", ['USER_DATAVALID', 'USER_NOT_FOUND_DATAVALID'])
                    ->groupBy("user_activities.user_id", "atividades.flag")
                    ->orderByDesc("user_activities.created_at")
                    ->first();

                $resp['context_datavalid'] = $datavalid->context_datavalid ?? null;
                $resp['flag_datavalid'] = $datavalid->flag_datavalid ?? null;
            }

            if ($data->domain->hasFeature('zaittStores')) {
                $accessQuantity = CustomerJourney::query()->whereNull("parent_id")
                    ->where("client_id", $data->id)->count();
                $resp['quantity_access'] = $accessQuantity;
            }

            return $resp;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}