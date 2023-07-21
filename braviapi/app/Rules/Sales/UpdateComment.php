<?php

namespace App\Rules\Sales;

use Illuminate\Support\Facades\Auth;
use Packk\Core\Models\LogTable;
use Packk\Core\Models\PedidoComments;

class UpdateComment
{
    public function execute($id, $newComment)
    {
        $comment = PedidoComments::findOrFail($id);
        LogTable::log('UPDATE', "pedido_comments", $id, 'comment', $comment->comment, $newComment);
        $comment->comment = $newComment;
        $comment->save();

        $item = $comment->toArray();
        $item['user'] = Auth::user()->full_name;
        return [
            'success' => true,
            'item' => $item
        ];
    }
}