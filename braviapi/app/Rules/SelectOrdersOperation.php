<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-18
 * Time: 21:53
 */

namespace App\Rules;

use Packk\Core\Models\Order;
use Packk\Core\Models\Property;
use Packk\Core\Models\Domain;
use Packk\Core\Models\Service;
use Carbon\Carbon;

class SelectOrdersOperation
{

    protected $queryBuilder;


    public function execute($type, $filter, $qtd, $query, $domain, $regions = [])
    {

        $cut = Carbon::now()->addHours(-12);
        $now = Carbon::now();
        $now->addHours(1);
        $data = collect([]);

        $this->queryBuilder = (object)[
            "finder" => $filter,
            "order" => (object)[
                "builder" => Order::query(),
                "featch" => false,
                "date" => true,
            ],
            "service" => (object)[
                "builder" => Service::query(),
                "featch" => false,
                "date" => true,
            ]
        ];
        if (!empty($query)) {
            if ($this->queryBuilder->finder == 'N') {
                $this->queryBuilder->order->builder->whereIn($query[0], $query[1]);
                $this->queryBuilder->finder = 'ALL';
                $this->queryBuilder->order->date = false;
            }
            if ($this->queryBuilder->finder == 'P') {
                $this->queryBuilder->order->builder->whereIn('pedidos.' . $query[0], $query[1]);
                $domain = null;
                $this->queryBuilder->order->date = false;
            }
            if ($this->queryBuilder->finder == 'L') {
                $this->queryBuilder->order->builder->whereIn($query[0], $query[1]);
                $this->queryBuilder->finder = 'P';
                $this->queryBuilder->order->date = false;
            }
            if ($this->queryBuilder->finder == 'F') {
                $this->queryBuilder->service->builder->whereIn('favores.' . $query[0], $query[1]);
                $domain = null;
                $this->queryBuilder->service->date = false;
            }
            if ($this->queryBuilder->finder == 'ALL') {
                $this->queryBuilder->order->builder->whereIn('pedidos.' . $query[0], $query[1]);
                $this->queryBuilder->service->builder->whereIn('favores.' . $query[0], $query[1]);

                $domain = $query[0] != "id" ? $domain : null;

                $this->queryBuilder->order->date = false;
                $this->queryBuilder->service->date = false;
            }
            if ($this->queryBuilder->finder == 'FESTIVAL') {
                $this->queryBuilder->order->builder->whereIn($query[0], $query[1]);
                $this->queryBuilder->finder = 'P';
                $this->queryBuilder->order->date = false;
            }
        }
        switch ($type) {
            case 'agendamento':
                $this->queryBuilder->order->builder->whereNotIn('pedidos.estado', ['C', 'F'])->where("tipo", "AGENDAMENTO");;
                $this->orderQuery();
                return $this->queryBuilder->order->builder->paginate($qtd)->concat($data);
            case 'andamento':
                if ($this->queryBuilder->finder == 'P' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->order->builder->whereNotIn('pedidos.estado', ['C', 'F']);
                    $this->orderQuery($regions);
                    $this->queryBuilder->order->featch = true;
                }
                if ($this->queryBuilder->finder == 'F' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->service->builder->whereIn('estado', ['ANDAMENTO', 'PENDENTE']);
                    $this->serviceQuery();
                    $this->queryBuilder->service->featch = true;
                }
                break;
            case 'sem-entregador':
                if ($this->queryBuilder->finder == 'P' || $this->queryBuilder->finder == 'ALL') {
                    $this->orderQuery($regions);
                    $this->queryBuilder->order->builder = $this->queryBuilder->order->builder->whereNotIn('pedidos.estado', ['C', 'F'])
                        ->where('pedidos.modo_entrega', 'DELIVERY')
                        ->whereHas('entregas', function ($q) {
                            $q->whereNull('entregador_id');
                        });
                    $this->queryBuilder->order->featch = true;
                }
                if ($this->queryBuilder->finder == 'F' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->service->builder->whereNotIn('estado', ['CONCLUIDO', 'CANCELADO'])->whereNull('entregador_id');
                    $this->serviceQuery();
                    $this->queryBuilder->service->featch = true;
                }
                break;
            case 'com-entregador':
                if ($this->queryBuilder->finder == 'P' || $this->queryBuilder->finder == 'ALL') {
                    $this->orderQuery($regions);
                    $this->queryBuilder->order->builder->whereNotIn('pedidos.estado', ['C', 'F'])
                        ->whereHas('entregas', function ($q) {
                            $q->whereNotNull('entregador_id');
                        });
                    $this->queryBuilder->order->featch = true;
                }
                if ($this->queryBuilder->finder == 'F' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->service->builder->whereNotNull('entregador_id')
                        ->whereNotIn('estado', ['CONCLUIDO', 'CANCELADO']);
                    $this->serviceQuery();
                    $this->queryBuilder->service->featch = true;
                }
                break;
            case 'coletado':
                if ($this->queryBuilder->finder == 'P' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->order->builder->where('pedidos.estado', '=', 'T');
                    $this->orderQuery($regions);
                    $this->queryBuilder->order->featch = true;
                }
                if ($this->queryBuilder->finder == 'F' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->service->builder
                        ->whereNotNull('entregador_id')
                        ->whereIn('estado', ['PENDENTE', 'ANDAMENTO']);
                    $this->serviceQuery();
                    $this->queryBuilder->service->featch = true;
                }
                break;
            case 'cancelados':
                if ($this->queryBuilder->finder == 'P' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->order->builder->where('estado', 'C');
                    $this->orderQuery($regions);
                    $this->queryBuilder->order->featch = true;
                }
                if ($this->queryBuilder->finder == 'F' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->service->builder->where('estado', 'CANCELADO');
                    $this->serviceQuery();
                    $this->queryBuilder->service->featch = true;
                }
                break;
            case 'suspeito':
                if ($this->queryBuilder->finder == 'P' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->order->builder->where('estado', 'S');
                    $this->orderQuery($regions);
                    $this->queryBuilder->order->featch = true;
                }
                break;
            case 'pendente':
                if ($this->queryBuilder->finder == 'P' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->order->builder->where('estado', 'P');
                    $this->orderQuery($regions);
                    $this->queryBuilder->order->featch = true;
                }
                break;
            case 'finalizados':
                if ($this->queryBuilder->finder == 'P' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->order->builder->where('estado', 'F');
                    $this->orderQuery($regions);
                    $this->queryBuilder->order->featch = true;
                }
                if ($this->queryBuilder->finder == 'F' || $this->queryBuilder->finder == 'ALL') {
                    $this->queryBuilder->service->builder->where('estado', 'CONCLUIDO');
                    $this->serviceQuery();
                    $this->queryBuilder->service->featch = true;
                }
                break;
            case 'todos':
                if ($this->queryBuilder->finder == 'P' || $this->queryBuilder->finder == 'ALL') {
                    $this->orderQuery($regions);
                    $this->queryBuilder->order->featch = true;
                }
                if ($this->queryBuilder->finder == 'F' || $this->queryBuilder->finder == 'ALL') {
                    $this->serviceQuery();
                    $this->queryBuilder->service->featch = true;
                }
                break;
            case 'pronto-sem-entregador':
                $this->orderQuery($regions);
                return $this->orderReadyDeliverymanLess($this->queryBuilder->order->builder->paginate($qtd)->concat($data));
        }
        $normalOrder = clone $this->queryBuilder->order->builder;
        $scheduleOrder = clone $this->queryBuilder->order->builder;
        $service = $this->queryBuilder->service->builder;

        if (isset($domain) && $domain != -1) {
            $scheduleOrder->where("pedidos.domain_id", $domain);
            $normalOrder->where("pedidos.domain_id", $domain);
            $service->where("favores.domain_id", $domain);
        }
        $scheduleOrder->where("tipo", "AGENDAMENTO")->orderBy('metricas_pedidos.scheduled_at', 'desc');
        $normalOrder->where("tipo", "!=", "AGENDAMENTO")->orderBy('pedidos.created_at', 'desc');
        $service->orderBy('favores.created_at', 'desc');


        if ($this->queryBuilder->order->date) {
            $normalOrder
                ->where('pedidos.created_at', '>', $cut);
            $scheduleOrder
                ->where('metricas_pedidos.scheduled_at', '>', $cut)
                ->where('metricas_pedidos.scheduled_at', '<', $now);
        }
        if ($this->queryBuilder->service->date) {
            $service->where('favores.created_at', '>', $cut);
        }
        if ($this->queryBuilder->order->featch) {
            $data = $normalOrder->paginate($qtd)->concat($data)->each(function ($order) {
                $order->operation_user = $this->operationUser();
                $order->from_connect_dez = $this->connectDez($order);
                if (isset($order->entrega->entregador))
                    $order->entrega->entregador->entregasCount = $order->entrega->entregador->entregasCount;
            });
            $data = $scheduleOrder->paginate($qtd)->concat($data)->each(function ($order) {
                $order->operation_user = $this->operationUser();
                $order->from_connect_dez = $this->connectDez($order);
                if (isset($order->entrega->entregador))
                    $order->entrega->entregador->entregasCount = $order->entrega->entregador->entregasCount;
            });
        }
        if ($this->queryBuilder->service->featch) {
            $data = $service->paginate($qtd)->concat($data)->each(function ($order) {
                $order->operation_user = $this->operationUser();
                if (isset($order->entrega->entregador))
                    $order->entrega->entregador->entregasCount = $order->entrega->entregador->entregasCount;
            });
        }
        return $data;
    }

    private function orderReadyDeliverymanLess($pedidos)
    {
        $pedidosFormatados = [];
        foreach ($pedidos as $pedido) {
            $tempoAtual = Carbon::now();
            $dateAproved = $pedido->metrica->aproved_at ?? $pedido->created_at;
            $tempoPreparo = $pedido->metrica->preparation_time !== null ? $pedido->metrica->preparation_time - 15 : 5;
            $tempoPedidoCriado = Carbon::parse($dateAproved)->addMinutes($tempoPreparo);
            $dispatchBlock = $pedido->loja->getSetting('dispatch_block');
            if (
                $pedido->modo_entrega != "TAKEOUT"
                && $pedido->modo_entrega != "DELIVERY_MARKETPLACE"
                && $pedido->estado != 'C'
                && $pedido->entrega->entregador_id == null
                && (($tempoAtual->gt($tempoPedidoCriado) && !$dispatchBlock) || $pedido->metrica->notification_at != null)
            ) {
                $pedidosFormatados[] = $pedido;
            }
        }
        return $pedidosFormatados;
    }

    private function orderQuery($regions = [])
    {
        $domain = currentDomain(true);
        $limitHours = $domain->getSetting("minimum_time_checkout_admin");
        $sqlTimeFinishOrder = "IF(TIMESTAMPDIFF(HOUR, pedidos.created_at, now()) >= {$limitHours} AND pedidos.estado NOT IN ('C', 'F'), TRUE, FALSE) AS tempo_finalizar_pedido";

        if (COUNT($regions) > 0) {
            $this->queryBuilder->order->builder->with('entrega.accepted_at_geolog')
                ->with('voucher:id,chave')
                ->with('entrega.started_at_geolog')
                ->with('entrega.finished_at_geolog')
                ->with('cliente.user')
                ->with('enderecoCliente')
                ->with(['produtos_vendidos' => function ($query) {
                    $query->with('personalizacoes:id,modificador,produto_vendido_id,quantidade,descricao,personalizado_type');
                }])
                ->with('entrega.entregador.user')
                ->with('addressConcierge')
                ->with('loja.enderecos')
                ->with('rejeitado_por')
                ->with('info:id,pedido_id,cpf_card,approval_hash,concierge_store')
                ->with(['transferencias' => function ($query) {
                    $query->select('id', 'pedido_id', 'valor', 'expected_on', 'tag')->where('estado', 'P');
                }])
                ->with('gorjetas:id,pedido_id,valor')
                ->with('antifraud')
                ->select('pedidos.*', 'metricas_pedidos.distance', 'metricas_pedidos.aproved_at', 'metricas_pedidos.scheduled_at')
                ->selectRaw("concat(DATE_FORMAT(metricas_pedidos.scheduled_at, '%H:00'), ' até ',DATE_FORMAT(DATE_ADD(metricas_pedidos.scheduled_at, INTERVAL 1 HOUR),'%H:00')) as intervalo")
                ->selectRaw($sqlTimeFinishOrder)
                ->whereIn('infos_pedidos.region', $regions);
        }

        $this->queryBuilder->order->builder->with('entrega.accepted_at_geolog')
            ->with('voucher:id,chave')
            ->with('entrega.started_at_geolog')
            ->with('entrega.finished_at_geolog')
            ->with('cliente.user')
            ->with('enderecoCliente')
            ->with(['produtos_vendidos' => function ($query) {
                $query->with('personalizacoes:id,modificador,produto_vendido_id,quantidade,descricao,personalizado_type');
            }])
            ->with('entrega.entregador.user')
            ->with('addressConcierge')
            ->with('loja.enderecos')
            ->with('rejeitado_por')
            ->with('info:id,pedido_id,cpf_card,approval_hash,concierge_store')
            ->with(['transferencias' => function ($query) {
                $query->select('id', 'pedido_id', 'valor', 'expected_on', 'tag')->where('estado', 'P');
            }])
            ->with('gorjetas:id,pedido_id,valor')
            ->with('antifraud')
            ->leftJoin('metricas_pedidos', 'metricas_pedidos.pedido_id', '=', 'pedidos.id')
            ->leftJoin('infos_pedidos', 'infos_pedidos.pedido_id', '=', 'pedidos.id')
            ->leftJoin('domains', 'domains.id', '=', 'pedidos.domain_id')
            ->leftJoin('mappings', function ($left) {
                $left->on('mappings.local_id', '=', 'pedidos.id')
                    ->where('mappings.local_type', "App\Models\Pedido")
                    ->where('mappings.ext_type', "App\Rules\Integration\Mobne\PurchaseOrder");
            })
            ->select('pedidos.*', 'metricas_pedidos.distance', 'metricas_pedidos.aproved_at', 'metricas_pedidos.scheduled_at', 'mappings.ext_id as pedido_id_ext')
            ->selectRaw('0 as distancia_entregador, domains.title as domain_name')
            ->selectRaw($sqlTimeFinishOrder)
            ->selectRaw("concat(DATE_FORMAT(metricas_pedidos.scheduled_at, '%H:00'), ' até ',DATE_FORMAT(DATE_ADD(metricas_pedidos.scheduled_at, INTERVAL 1 HOUR),'%H:00')) as intervalo");
    }

    private function serviceQuery()
    {
        return $this->queryBuilder->service->builder->with('accepted_at_geolog')
            ->with('finished_at_geolog')
            ->with('cliente.user')
            ->with('entregador.user')
            ->with('paradas.endereco')
            ->with('paradas.checked_at_geolog')
            ->leftJoin('domains', 'domains.id', '=', 'favores.domain_id')
            ->select('favores.*')
            ->selectRaw('0 as distancia_entregador, domains.title as domain_name');
    }

    private function operationUser()
    {

        $domain = currentDomain(true);
        return $domain->getSetting("order_management") ? $domain->getSetting("order_management") : true;

    }

    private function connectDez($order)
    {
        return Domain::find($order->domain_id)->hasFeature('connect_dez');
    }
}