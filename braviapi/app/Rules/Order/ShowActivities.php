<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-30
 * Time: 11:15
 */

namespace App\Rules\Order;

use Packk\Core\Models\Deliveryman;
use Packk\Core\Models\DelivererDismissed;
use Packk\Core\Models\OrderHistory;
use Packk\Core\Models\OrderPrinted;
use Packk\Core\Models\Order;
use Packk\Core\Models\AcceptedAttempt;
use Packk\Core\Models\User;
use Packk\Core\Models\RejectedOrder;
use Packk\Core\Models\DeliveryRequest;
use Packk\Core\Models\LogBacktoNetwork;
use Packk\Core\Util\Phones;
use Carbon\Carbon;

class ShowActivities
{
    public function execute($pedido_id)
    {
        try {
            $mensagens = collect([]);
            $mensagens_card = collect([]);
            $pedido = Order::with([
                'metrica:id,pedido_id,check_point_at,notification_at,canceled_at',
                'produtos_vendidos',
                'info',
                'motivo:id,descricao,tipo'
            ])->findOrFail($pedido_id);
            $entrega = $pedido->entrega;

            if (!empty($entrega->entregador_id)) {
                $entregador = Deliveryman::withoutGlobalScope('App\Scopes\DomainScope')->find($entrega->entregador_id)->user;
            }
            if (isset($entrega)) {
                $log_voltar_rede = LogBacktoNetwork::where('entrega_id', '=', $entrega->id)->get();
                $log_ent_disp = DelivererDismissed::where('pedido_id', '=', $pedido->id)->where('type', 'KICK')->get();
                $log_solic_entrega = RejectedOrder::where('entrega_id', '=', $entrega->id)->get();
                $log_tent_aceite = AcceptedAttempt::where('entrega_id', '=', $entrega->id)->get();
                $log_pedido_rejeitado = DelivererDismissed::where('pedido_id', '=', $pedido->id)->get();
                foreach ($log_voltar_rede as $item) {
                    if (isset($item->entregador_id)) {
                        $mes['body'] = $item->entregador->user->nome_completo . ' voltou para a rede';
                        $message = '<b>Motivo: </b>' . $item->motivo->descricao . '<br>';
                        $message .= '<b>E-mail: </b><a href="mailto:' . $item->entregador->user->email . '">' . $item->entregador->user->email . '</a><br>';
                        $message .= '<b>Telefone: </b>' . Phones::formatExibe($item->entregador->user->telefone) . '<br>';
                        $message .= '<a style="margin-top:10px;" class="btn btn-default form-group form-control" href="' . $item->entregador->user->foto_perfil . '" target="_blank">ver foto</a>';
                        $mes['message'] = $message;
                        $mes['created'] = $item->created_at;
                        $mes['remove'] = 0;
                        $mes['class'] = 'red';
                        $mensagens->push($mes);
                    };
                }
                foreach ($log_ent_disp as $item) {
                    $mes['body'] = "Entregador {$item->entregador->user->nome_completo} Dispensado";
                    $message = '<b>E-mail: </b><a href="mailto:' . $item->entregador->user->email . '">' . $item->entregador->user->email . '</a><br>';
                    $message .= '<b>Telefone: </b>' . Phones::formatExibe($item->entregador->user->telefone) . '<br>';
                    $message .= '<a style="margin-top:10px;" class="btn btn-default form-group form-control" href="' . $item->entregador->user->foto_perfil . '" target="_blank">ver foto</a>';
                    $mes['message'] = $message;
                    $mes['created'] = $item->created_at;
                    $mes['remove'] = 0;
                    $mes['class'] = 'red';
                    $mensagens->push($mes);
                }
                foreach ($log_tent_aceite as $item) {
                    $user = $item->entregadorWithoutScope->user;
                    if (!$this->mensagemEntregaNaoDisponivel($item->descricao)) {
                        $mes['body'] = 'Tentativa de aceite';
                        $message = $item->descricao . "<br />";
                        $message .= "<b>Nome:</b> " . $user->nome_completo . "<br />";
                        $message .= '<b>E-mail: </b><a href="mailto:' . $user->email . '">' . $user->email . '</a><br>';
                        $message .= '<b>Telefone: </b>' . Phones::formatExibe($user->telefone) . '<br>';
                        $message .= '<a style="margin-top:10px;" class="btn btn-default form-group form-control" href="' . $user->foto_perfil . '" target="_blank">ver foto</a>';
                        $mes['message'] = $message;
                        $mes['created'] = $item->created_at;
                        $mes['remove'] = 0;
                        $mes['class'] = 'yellow';
                        $mensagens->push($mes);
                    } else {
                        $mes['body'] = 'Tentativa de aceite';
                        $mes['message'] = $item->descricao . ' ' . $user->nome_completo . " - " . Phones::formatExibe($user->telefone);
                        $mes['created'] = $item->created_at;
                        $mensagens_card->push($mes);
                    }
                }
                foreach ($log_solic_entrega as $item) {
                    $mes['body'] = 'Solicitação de aceite';
                    $mes['message'] = 'o entregador: #' . $item->entregador->id . ' - ' . $item->entregador->user->nome_completo . " - " . Phones::formatExibe($item->entregador->user->telefone) . ' tentou aceitar a entrega';
                    $mes['created'] = $item->created_at;
                    $mensagens_card->push($mes);
                }
                foreach ($log_pedido_rejeitado as $item) {
                    $mes['body'] = 'Entrega rejeitado';
                    $mes['message'] = "O entregador: #" . $item->entregador->id . " - " . $item->entregador->user->nome_completo . " rejeitou a entrega. <br />";
                    $mes['created'] = $item->created_at;
                    $mensagens_card->push($mes);
                };
            }
            $log_atividades = $pedido->atividades_without_cache();
            $log_atividades_entrega = $pedido->entrega->atividades;
            $log_comments = $pedido->comments;
            $log_order_history = $pedido->histories;
            $logOrdersPrinted = OrderPrinted::where("order_id", $pedido->id)->get();
            foreach ($log_atividades as $item) {
                $mes['body'] = 'Atividade';
                if ($pedido->info->shopkeeper_motive_id) {
                    $message = explode("||", $item->pivot->context);
                    $mes['message'] = $message[0] . "<br />";
                    if (str_contains($item->pivot->context, "||") && str_contains($item->pivot->context, "Motivo:")) {
                        $mes['editShopkeeperMotive'] = true;
                        $mes['messageMotive'] = $message[0];
                        $mes['activityOrderId'] = $item->pivot->id;
                    }

                    if (!is_null($item->pivot->status)) {
                        $mes['atividadeStatus'] = $item->pivot->status;
                    } else {
                        $mes['atividadeStatus'] = false;
                    }

                    if (isset($message[1]) && trim($message[1]) != "") {
                        $mes['message'] .= "Descrição: " . $message[1];
                    }
                } else {
                    $mes['message'] = $item->pivot->context;
                }
                $mes['created'] = $item->pivot->created_at;
                $mes['remove'] = 0;
                $mes['class'] = $item->scope == "ADMIN" ? "yellow" : "green";
                if ($item->flag == 'ADMIN_CANCEL_ORDER') {
                    $mes['class'] = 'red';
                }
                $mensagens->push($mes);
            }
            foreach ($log_atividades_entrega as $item) {
                $mes['body'] = 'Atividade';
                if ($pedido->info->shopkeeper_motive_id) {
                    $message = explode("||", $item->pivot->context);
                    $mes['message'] = $message[0] . "<br />";
                    if (str_contains($item->pivot->context, "||") && str_contains($item->pivot->context, "Motivo:")) {
                        $mes['editShopkeeperMotive'] = true;
                        $mes['messageMotive'] = $message[0];
                        $mes['activityOrderId'] = $item->pivot->id;
                    }

                    if (isset($message[1]) && trim($message[1]) != "") {
                        $mes['message'] .= "Descrição: " . $message[1];
                    }
                } else {
                    $mes['message'] = $item->pivot->context;
                }
                $mes['created'] = $item->pivot->created_at;
                $mes['remove'] = 0;
                $mes['class'] = $item->scope == "ADMIN" ? "yellow" : "green";
                if ($item->flag == 'ADMIN_CANCEL_ORDER') {
                    $mes['class'] = 'red';
                }
                $mensagens->push($mes);
            }
            foreach ($log_comments as $item) {
                $user = User::find($item->user_id);
                $mes['body'] = "{$user->nome} {$user->sobrenome}";
                $mes['message'] = $item->comment;
                $mes['created'] = $item->created_at;
                $mes['remove'] = 1;
                $mes['class'] = 'red';
                $mes['id'] = $item->id;
                $mensagens->push($mes);
            }

            foreach ($log_order_history as $order_history) {
                $mes['body'] = 'Atividade';
                $message = "";
                if ($order_history->previous_value != $order_history->current_value) {
                    $message .= "O Pedido teve uma mudança de status de: <strong>{$order_history->previous_value}</strong> para: <strong>{$order_history->current_value}</strong>.";
                }
                $mes['created'] = $order_history->created_at;
                $mes['remove'] = 0;
                $mes['class'] = "yellow";
                if ($message != "") {
                    $mes["message"] = $message;
                    $mensagens->push($mes);
                }
            }

            foreach ($logOrdersPrinted as $printed) {
                $mes['body'] = 'Atividade';
                if (in_array($printed->type, ["PRINT", "REPRINT"])) {
                    $message = "Foi realizado a solicitação de " . ($printed->type == "PRINT" ? "impressão" : "reeimpressão") . " ao software de impressão pelo agent: " . $printed->agent;
                } else {
                    $message = "Foi realizado a confirmação do pedido de impressão pelo agent: " . $printed->agent;
                }

                $mes['created'] = $printed->created_at;
                $mes['remove'] = 0;
                $mes['class'] = "yellow";
                if ($message != "") {
                    $mes["message"] = $message;
                    $mensagens->push($mes);
                }
            }

            if (isset($pedido->metrica) && $pedido->metrica->notification_at) {
                $mes['body'] = 'Atividade';
                $mes['message'] = 'O Lojista marcou que o pedido está pronto.';
                $mes['created'] = $pedido->metrica->notification_at;
                $mes['remove'] = 0;
                $mes['class'] = 'yellow';
                $mensagens->push($mes);
            }

            if ($pedido->motivo && $pedido->info->canceled_by != 'ADMIN') {
                $mes['body'] = 'Cancelamento';
                $mes['message'] = 'Cancelado pelo ' . $pedido->info->canceled_by . '. <br />';
                $mes['message'] .= 'Motivo: ' . $pedido->motivo->descricao . '.<br />';
                if ($pedido->info->reason_refuse) {
                    $mes['message'] .= 'Descrição: ' . $pedido->info->reason_refuse;
                }
                $mes['created'] = isset($pedido->metrica->canceled_at) ? $pedido->metrica->canceled_at : Carbon::now();
                $mes['remove'] = 0;
                $mes['class'] = 'red';
                $mensagens->push($mes);
            }

            $mensagens = $mensagens->sortByDesc('created');
            $mensagens_card = $mensagens_card->sortByDesc('created');
            return \view('includes.get_atividades', ['pedido' => $pedido, 'mensagens' => $mensagens, 'message_card' => $mensagens_card]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function mensagemEntregaNaoDisponivel($message)
    {
        return $message == "A entrega não está mais disponível.";
    }
}