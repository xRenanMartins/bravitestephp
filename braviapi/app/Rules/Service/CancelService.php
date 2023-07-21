<?php

namespace App\Rules\Service;

use App\Http\Controllers\Responser;
use Packk\Core\Models\Service;
use Packk\Core\Models\Property;
use Packk\Core\Models\Retention;
use Packk\Core\Integration\Payment\Transaction;
use Packk\Core\Util\Firebase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CancelService
{
    public function execute($payload)
    {
        try {
            DB::beginTransaction();
            $emails = [];
            $entregador = null;

            try {
                $favor = Service::findOrFail($payload->favor_id);
            } catch (\Exception $e) {
                DB::rollBack();
                return Responser::response([], Responser::NOT_FOUND_ERROR);
            }

            if ($favor->estado == 'CANCELADO') {
                return Responser::response([], Responser::OK);
            }

            if (isset($favor->entregador)) {
                $entregador = $favor->entregador;
                $favor->entregador->push_reload();
                array_push($emails, $favor->deliveryman->user->email);
            }
            $favor->entregador_id = null;
            $favor->estado = 'CANCELADO';
            $favor->motivo_id = $payload->motivo_id ?? null;
            $favor->canceled_at = Carbon::now();
            $favor->save();

            if ($favor->motivo_id) {
                $message = "Pedido Cancelado. Motivo: " . $favor->reason->descricao;
            } else {
                $message = "Pedido Cancelado. Qualquer dúvida entre em contato com o suporte";
            }

            //envia um push para o cliente
            $firebase = new Firebase();

            $firebase->sendDirectMessage(
                $favor->customer->user->email,
                array(
                    'tipo' => 'order_cancel',
                    'log' => $message,
                    'pedido_id' => $favor->id,
                    'nome_loja' => "",
                    'tipo_checkout' => "SERVICO",
                    'estado' => $favor->estado,
                    'redirect_to' => config('globals.app.screens.tracking'),
                    'type' => 'services'
                )
            );

            if ($entregador && $payload->taxa_cancelamento_entregador) {
                $taxa_cancelamento_entregador = intval(Property::get("TAXA_CANCELAMENTO_ENTREGADOR", 300));
                Retention::gera_bonus($taxa_cancelamento_entregador, $entregador, "Bônus por cancelamento do favor #{$favor->id}", "BONUS_CANCELAMENTO", $favor);
            }

            DB::commit();

            try {
                $t = new Transaction($favor->zoop_transaction_id, true);
                $t->voidfull();
            } catch (\Exception $e) {
            }

            return Responser::response([], Responser::OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return Responser::response([], Responser::SERVER_ERROR);
        }
    }
}