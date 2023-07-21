<?php

namespace App\Rules\Order;

use Packk\Core\Models\Order;
use Packk\Core\Models\User;

class ShowComments
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

            $log_comments = $pedido->comments;

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

            $mensagens = $mensagens->sortByDesc('created');
            $mensagens_card = $mensagens_card->sortByDesc('created');

            return view('includes.get_comentarios', [
                'pedido' => $pedido,
                'mensagens' => $mensagens,
                'message_card' => $mensagens_card,
            ]);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}