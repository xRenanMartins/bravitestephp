<?php

namespace App\Rules\CustomersJourney;

use Packk\Core\Models\CustomerJourney;
use Packk\Core\Models\User;
use Carbon\Carbon;
use Packk\Core\Scopes\DomainScope;

class ListCustomersJourney
{
    private $payload;

    public function execute($payload)
    {
        $this->payload = $payload;

        $query = CustomerJourney::withoutGlobalScope(DomainScope::class)
            ->join('lojas', 'lojas.id', '=', 'customers_journey.store_id')
            ->join('clientes', 'clientes.id', '=', 'customers_journey.client_id')
            ->join('users', 'users.id', '=', 'clientes.user_id')
            ->whereNull('customers_journey.entrance_type')
            ->whereNull('customers_journey.parent_id')
            ->where('customers_journey.domain_id', currentDomain())
            ->select([
                'customers_journey.id',
                'customers_journey.store_id',
                'customers_journey.description',
                'customers_journey.first_access',
                'lojas.nome as loja',
                'customers_journey.client_id',
                'users.foto_perfil_s3 as client_image',
                'users.email as client_mail',
                'customers_journey.camera_analysis',
                'customers_journey.camera_analysis_status',
                'clientes.e_funcionario as is_worker',
                'users.blacklist as blacklist',
            ])
            ->selectRaw("concat(users.nome, ' ', users.sobrenome) as client_name")
            ->selectRaw("(select m.created_at from customers_journey as m where m.parent_id = customers_journey.id 
                                    and m.entrance_type = 'entrance_door' order by m.id limit 1) as entrance_time")
            ->selectRaw("(select m.created_at from customers_journey as m where m.parent_id = customers_journey.id 
                                    and m.entrance_type = 'exit_door' order by m.id limit 1) as exit_time")
            ->selectRaw("(SELECT CAST(JSON_ARRAYAGG(JSON_OBJECT('id', p.id, 'status', estado_pagamento, 
                                    'valor', valor, 'is_manual', cj_o.entrance_type = 'manual_order')) AS JSON)
                                    FROM pedidos as p join customers_journey cj_o on cj_o.order_id = p.id 
                                    where cj_o.parent_id = customers_journey.id) as orders");

        $query = $this->filterCameraAnalysis($query);

        if (!empty($this->payload->client_search)) {
            $query = $this->filterClient($query);
        } else {
            $query = $this->filterPeriod($query);
            $query = $this->filterBlacklist($query);
            $query = $this->filterViewer($query);

            $query->identic('customers_journey.store_id', $payload->store_id)
                ->identic('customers_journey.camera_analysis_status', $payload->status_pay);
        }

        $result = $query->orderByDesc('id')->simplePaginate($payload->length);
        return self::makeArrReturn($result);
    }

    private static function makeArrReturn($result)
    {
        $response = $result->toArray();
        foreach ($result->items() as $key => $item) {
            $orders = !empty($item->orders) ? json_decode($item->orders) : [];
            $orders = array_sort($orders, 'id', SORT_DESC);
            $response['data'][$key]['orders'] = array_values($orders);
            $response['data'][$key]['status'] = self::defineStatus($item, $orders);
        }

        $quantityAnalysis = User::withoutGlobalScope(DomainScope::class)
            ->select('id')
            ->where('domain_id', currentDomain())
            ->where('tipo', '<>', 'L')
            ->where("status", "EM_ANALISE")
            ->count();

        $response['quantity_analysis'] = $quantityAnalysis;
        return $response;
    }

    public static function defineStatus($item, $orders)
    {
        if (!empty($item->camera_analysis) && !empty($item->camera_analysis_status)) {
            $status = $item->camera_analysis_status;
        } else if (!isset($orders[0])) {
            $status = 'Suspeito';
        } else if (in_array($orders[0]->status, ['CAPTURADO', 'PAGO', 'PRE_AUTORIZADO'])) {
            $status = 'Sucesso';
        } else if ($orders[0]->status === 'ESTORNO') {
            $status = 'Chargedback';
        } else {
            $status = 'Suspeito - Transação falhada';
        }
        return $status;
    }

    // Filtro se é funcionário ou não
    private function filterViewer($query)
    {
        if (!empty($this->payload->viewer) && $this->payload->viewer !== 'all') {
            if ($this->payload->viewer === 'customers') {
                $query->where('clientes.e_funcionario', 0);
            } else {
                $query->where('clientes.e_funcionario', 1);
            }
        }
        return $query;
    }


    private function filterCameraAnalysis($query)
    {
        switch ($this->payload->camera_identification) {
            case 'Suspeito':
                $query->whereNull('customers_journey.camera_analysis')->where(function ($q) {
                    $q->whereRaw("(SELECT count(y.id) FROM customers_journey as y where y.parent_id = customers_journey.id and y.order_id is not null) = 0")
                        ->orWhereRaw("(SELECT p2.estado_pagamento FROM pedidos as p2 join customers_journey cj_o2 on cj_o2.order_id = p2.id 
                                    and cj_o2.parent_id = customers_journey.id order by p2.id desc limit 1) 
                                    not in ('CAPTURADO', 'PAGO', 'PRE_AUTORIZADO', 'ESTORNO')");
                });
                break;
            case 'Não Analisado':
                $query->where(function ($q) {
                    $q->where('customers_journey.camera_analysis', $this->payload->camera_identification)
                        ->orWhereNull('customers_journey.camera_analysis');
                });
                break;
            default:
                $query->identic('customers_journey.camera_analysis', $this->payload->camera_identification);
        }

        return $query;
    }

    private function filterBlacklist($query)
    {
        $blacklist = (!empty($this->payload->blacklist) && ($this->payload->blacklist === true || $this->payload->blacklist === 'true'));
        if (!$blacklist) {
            $query->where('users.blacklist', 0);
        }
        return $query;
    }

    private function filterPeriod($query)
    {
        $startPeriod = Carbon::createFromFormat('d/m/Y', $this->payload->start_period);
        $startPeriod->startOfDay();
        $endPeriod = Carbon::createFromFormat('d/m/Y', $this->payload->end_period);
        $endPeriod->endOfDay();

        $query->whereBetween('customers_journey.created_at', [$startPeriod, $endPeriod]);
        return $query;
    }

    private function filterClient($query)
    {
        switch ($this->payload->client_search_type) {
            case 'name':
                $query->whereRaw("concat(users.nome, ' ', users.sobrenome) like '{$this->payload->client_search}%'");
                break;
            case 'email':
                $query->whereRaw("users.email like '{$this->payload->client_search}%'");
                break;
            case 'cpf':
                $query->where('users.cpf', $this->payload->client_search);
                break;
            case 'id':
                $query->where('customers_journey.id', $this->payload->client_search);
                break;
        }
        return $query;
    }
}