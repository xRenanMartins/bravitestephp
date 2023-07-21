<?php

namespace App\Rules\Order;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Packk\Core\Decorators\ClientDecorator;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Models\Activity;
use Packk\Core\Models\Order;
use Packk\Core\Models\Reason;

class ChangeOrderSchedule
{
    private $payload;
    private $order;
    private $n8nEndpoint = '';
    public function execute($orderId, $payload)
    {
        $this->n8nEndpoint = env('ENDPOINT_N8N');
        $this->payload = $payload;
        $this->order = Order::findOrFail($orderId);

        $scheduleAt = $this->changeMetricSchedule();
        $this->saveReason();
        $this->addActivity($scheduleAt);

        return $this->sendToN8N($scheduleAt);
    }

    private function changeMetricSchedule()
    {
        $scheduleAt = Carbon::createFromFormat('Y-m-d', $this->payload['date']);
        $scheduleAt->setHour((int)explode(' - ', $this->payload['interval'])[0]);
        $scheduleAt->setMinute(0);
        $scheduleAt->setSecond(0);
        $this->order->metric->scheduled_at = $scheduleAt;
        $this->order->metric->save();
        return $scheduleAt;
    }

    private function saveReason()
    {
        DB::table('order_reasons')->insert([
            'reason_id' => $this->payload['reason_id'],
            'order_id' => $this->order->id,
            'type' => 'CHANGED_SCHEDULED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addActivity($scheduleAt)
    {
        $date = $scheduleAt->format('d/m/Y');
        $hours = str_replace('-', 'às', $this->payload['interval']);
        $this->order->add_atividade(Activity::PEDIDO_ATIVIDADE_GENERICA,
            ['[::text]' => "Entrega reagendada para {$date} das {$hours} pelo usuário ". Auth::user()->full_name]);
        $this->order->add_atividade(Activity::PEDIDO_AGENDAMENTO_REAGENDADO, ['[::cliente_nome]' => $this->order->customer->user->nome]);
    }

    private function sendToN8N($scheduleAt)
    {
        $reason = Reason::find($this->payload['reason_id']);
        $body = [
            'type' => "orders.rescheduled",
            'created_at' => now(),
            'scheduled_at' => $scheduleAt,
            'data' => [
                'id' => $this->order->id,
                'store_id' => $this->order->loja_id,
                'source' => 'ADMIN',
                'reason' => $reason->descricao
            ]
        ];

        $gclient = new ClientDecorator();
        try {
            $result = $gclient->postRequest($this->n8nEndpoint.'/webhook/juvenal/reschedule', ['json' => $body]);
            return json_decode($result->getBody()->getContents());
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            throw new CustomException('Falha ao registrar sua alteração. Tente novamente');
        }
    }
}