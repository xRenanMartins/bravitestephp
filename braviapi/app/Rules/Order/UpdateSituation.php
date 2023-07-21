<?php

namespace App\Rules\Order;

use Packk\Core\Models\Activity;
use Packk\Core\Models\Order;

class UpdateSituation
{
    protected $user;
    protected $order;
    protected $rollback;

    /**
     *
     * Criação de atividade de ação manual do pedido pela plataforma
     *
     * @param int $id
     * @param string $type accept|approve|collect|finish|send_shopkeeper|change_deliveryman
     *
     * @return void
     *
     */
    public function execute($id, $type, $rollback = null)
    {
        try {
            $this->order = Order::find($id);
            $this->user = auth()->user();
            $this->rollback = $rollback;

            $context = match ($type) {
                "accept" => $this->acceptOrder(),
                "approve" => $this->approveOrder(),
                "collect" => $this->collectOrder(),
                "finish" => $this->finishOrder()
            };

            if ($context) {
                $this->order->add_atividade(Activity::ACTIVE_UPDATE_SITUATION, $context);
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     *
     * Ação manual de aceite de pedido via plataforma admin
     *
     */
    private function acceptOrder()
    {
        $message = isset($this->rollback) ? "Desfezar aceite do entregador" : "Aceite de pedido via plataforma";
        return $this->mountContext($message);
    }

    /**
     *
     * Ação manual de aprovação de pedido via plataforma admin
     *
     */
    private function approveOrder()
    {
        $message = isset($this->rollback) ? "Desfezar aprovação de lojista" : "Aprovação de pedido via plataforma";
        return $this->mountContext($message);
    }

    /**
     *
     * Ação manual de coleta de pedido via plataforma admin
     *
     */
    private function collectOrder()
    {
        $message = isset($this->rollback) ? "Desfezar a coleta na loja" : "Coleta de pedido via plataforma";
        return $this->mountContext($message);
    }

    /**
     *
     * Ação manual de finalização de pedido via plataforma admin
     *
     */
    private function finishOrder()
    {
        $message = "Finalização de pedido via plataforma";
        return $this->mountContext($message);
    }

    private function mountContext($text)
    {
        return [
            '[::order_id]' => $this->order->id,
            '[::situation]' => $text,
            '[::user]' => $this->user->nome_completo . " ({$this->user->id})",
        ];
    }
}