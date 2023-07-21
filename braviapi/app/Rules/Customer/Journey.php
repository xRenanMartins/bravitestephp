<?php

namespace App\Rules\Customer;

use Packk\Core\Models\Store;
use Packk\Core\Models\Order;
use Packk\Core\Models\CustomerJourney;
use Packk\Core\Models\Category;
use Packk\Core\Models\Customer;
use Packk\Core\Util\Formatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Journey
{
    protected $payload;
    protected $activities;
    protected $activityEvent;
    protected $total;
    protected $products;

    public function __construct()
    {
        $this->payload = collect([]);
    }

    public function execute($payload)
    {
        $this->initialize($payload);
        $this->payload->put("activities", $this->getActivities());

        return $this->payload;
    }

    private function initialize($payload)
    {
        if (isset($payload->blacklist) && $payload->blacklist == 'on') {
            $payload->blacklist = true;
        }
        $e_funcionario = null;
        if (isset($payload->e_funcionario) && $payload->e_funcionario == 'on') {
            $e_funcionario = false;
        }
        $stores = Store::where('habilitado', 1)->get();

        $endDate = Carbon::now();
        $startDate = Carbon::now()->addDays(-1);

        $store_id = isset($payload->store_id) ? $payload->store_id : -1;
        $hrinicio = isset($payload->hrinicio) ? $payload->hrinicio : null;
        $hrfim = isset($payload->hrfim) ? $payload->hrfim : null;

        if (isset($payload->dateL)) {
            $endDate = Carbon::parse($payload->dateL . " {$hrfim}");
        } elseif (!isset($payload->dateL) && isset($payload->criterio)) {
            $endDate = Carbon::now();
        }

        if (isset($payload->dateI)) {
            $startDate = Carbon::parse($payload->dateI . " {$hrinicio}");
        } elseif (!isset($payload->dateI) && isset($payload->criterio)) {
            $startDate = Carbon::createFromFormat("Y-m-d", "2020-08-01")->setTime(0, 0, 0);
        }

        $consultaJornadas = CustomerJourney::join('clientes', 'clientes.id', '=', 'customers_journey.client_id')
            ->join('users', 'clientes.user_id', '=', 'users.id')
            ->whereDate("customers_journey.created_at", '>=', $startDate)
            ->whereDate("customers_journey.created_at", "<=", $endDate)
            ->select("customers_journey.*", DB::raw("IFNULL(total_pedidos.valortotal, 0) as valortotal"));

        if ($store_id != -1) {
            $consultaJornadas->where("customers_journey.store_id", $store_id);
        }

        if (!empty($payload->criterio) && $payload->tipoCriterio == "nome") {
            $consultaJornadas->whereRaw("CONCAT(users.nome, ' ', users.sobrenome) LIKE '%{$payload->criterio}%'");
        } elseif (!empty($payload->criterio) && in_array($payload->tipoCriterio, ['email', 'cpf'])) {
            $consultaJornadas->where('users.' . $payload->tipoCriterio, "LIKE", "%{$payload->criterio}%");
        } elseif (!empty($payload->criterio) && $payload->tipoCriterio == 'activity_id') {
            $consultaJornadas->where("customers_journey.id", $payload->criterio);
        }

        if (!empty($payload->blacklist)) {
            $consultaJornadas->where('users.blacklist', $payload->blacklist);
        }

        if ($e_funcionario === false) {
            $consultaJornadas->where('clientes.e_funcionario', $e_funcionario);
        }

        if (!empty($payload->camera_analysis_status)) {
            $consultaJornadas->where('customers_journey.camera_analysis_status', $payload->camera_analysis_status);
        }

        if (!empty($payload->camera_analysis)) {
            if ($payload->camera_analysis == "Não Analisado") {
                $consultaJornadas->whereRaw(DB::raw("(customers_journey.camera_analysis IS NULL OR customers_journey.camera_analysis = 'Não Analisado')"));
            } else {
                $consultaJornadas->where('customers_journey.camera_analysis', $payload->camera_analysis);
            }
        }

        if (!empty($payload->contem_produto) || !empty($payload->contem_categoria)) {
            $categorias = "";
            if (!empty($payload->contem_categoria)) {
                foreach ($payload->contem_categoria as $categoria_id) {
                    $categorias .= $categoria_id . ",";
                }

                $getCategorias = Category::whereIn('id', $payload->contem_categoria)->get();
            }

            $consultaJornadas->join(
                DB::raw("
                    (
                        SELECT 
                            child.parent_id, child.entrance_type
                        FROM
                            customers_journey as child
                        INNER JOIN
                            customers_journey as parent ON parent.id = child.parent_id
                        INNER JOIN
                            pedidos as p ON p.id = child.order_id
                        INNER JOIN
                            produtos_vendidos as pv ON pv.pedido_id = p.id
                        INNER JOIN
                            produtos as prod ON prod.id = pv.produto_id
                        WHERE
                            child.entrance_type IN ('order', 'manual_order')
                            " . (!empty($payload->contem_produto) ? " AND pv.nome LIKE ?" : "") . "
                            " . (!empty($payload->contem_categoria) ? " AND prod.categoria_id IN (" . substr($categorias, 0, -1) . ")" : "") . "
                            AND parent.created_at >= ? AND parent.created_at <= ?
                            GROUP BY child.parent_id
                    ) as pv
                "), "pv.parent_id", "=", "customers_journey.id"
            );

            if (!empty($payload->contem_produto)) {
                $consultaJornadas->addBinding('%' . $payload->contem_produto . '%', 'join');
            }

            $consultaJornadas->addBinding($startDate, 'join')
                ->addBinding($endDate, 'join');
        }

        $consultaJornadas->leftJoin(DB::raw("(
            SELECT 
                child.parent_id, child.entrance_type, count(p.id) as qtdPedidos, SUM((p.valor/100) - p.gorjeta - p.comissao) as valortotal
            FROM
                customers_journey as child
            INNER JOIN
                customers_journey as parent ON parent.id = child.parent_id
            INNER JOIN
                pedidos as p ON p.id = child.order_id AND p.estado_pagamento IN ('CAPTURADO', 'PAGO', 'PRE_AUTORIZADO')
            WHERE
                child.entrance_type IN ('order', 'manual_order')
                AND parent.created_at >= ? AND parent.created_at <= ?
            GROUP BY parent.id
        ) as total_pedidos"), "total_pedidos.parent_id", "=", "customers_journey.id")
            ->addBinding($startDate, 'join')
            ->addBinding($endDate, 'join');

        if (!empty($payload->price_range)) {
            $price_range = !empty($payload->price_range) ? explode(';', $payload->price_range) : [];

            if (!empty($price_range) && isset($price_range[0]) && isset($price_range[1])) {
                $consultaJornadas->whereRaw("valortotal BETWEEN ? AND ?", [$price_range[0], $price_range[1]]);
            }
        }

        $this->activities = $consultaJornadas->get();
        $maxValueOrder = $this->activities->max('valortotal');

        $qtdAnalise = Customer::select(DB::raw("count(clientes.id) as qtdAnalise"))
            ->join('users', 'clientes.user_id', 'users.id')
            ->where('tipo', '<>', 'L')
            ->where("status", "EM_ANALISE")
            ->first();

        $qtdAnalise = !empty($qtdAnalise) ? $qtdAnalise->qtdAnalise : 0;

        $this->payload->put("dateI", $startDate->format("Y-m-d"));
        $this->payload->put("dateL", $endDate->format("Y-m-d"));
        $this->payload->put("hrinicio", $hrinicio);
        $this->payload->put("hrfim", $hrfim);
        $this->payload->put("stores", $stores);
        $this->payload->put("store_id", $store_id);
        $this->payload->put("price_range", isset($payload->price_range) ? $payload->price_range : null);
        $this->payload->put("camera_analysis_status", isset($payload->camera_analysis_status) ? $payload->camera_analysis_status : null);
        $this->payload->put("camera_analysis", isset($payload->camera_analysis) ? $payload->camera_analysis : null);
        $this->payload->put("contem_produto", isset($payload->contem_produto) ? $payload->contem_produto : null);
        $this->payload->put("contem_categoria", isset($payload->contem_categoria) ? $payload->contem_categoria : null);
        $this->payload->put("criterio", isset($payload->criterio) ? $payload->criterio : null);
        $this->payload->put("tipoCriterio", isset($payload->tipoCriterio) ? $payload->tipoCriterio : null);
        $this->payload->put("blacklist", isset($payload->blacklist) ? $payload->blacklist : null);
        $this->payload->put("e_funcionario", isset($payload->e_funcionario) ? $payload->e_funcionario : null);
        $this->payload->put("categorias", isset($getCategorias) ? $getCategorias : null);
        $this->payload->put("maxValueOrder", isset($maxValueOrder) ? $maxValueOrder : 0);
        $this->payload->put("qtdAnalise", $qtdAnalise);
    }

    private function getActivities()
    {
        $formattedActivities = collect([]);
        foreach ($this->activities as $activity) {
            $this->total = 0;
            if ($activity->parent_id == null) {
                $activity->first_entrance = $activity->first_entrance->first();
                $activity->last_exit = $activity->last_exit->first();

                $orders = $activity->events()->select(DB::raw("MAX(created_at) as created_at"), "order_id", "entrance_type", DB::raw("MAX(id) as id"))->groupBy("order_id")->get()->filter(function ($event) {
                    return $event->entrance_type == 'order';
                });
                $manual_orders = $activity->events()->select(DB::raw("MAX(created_at) as created_at"), "order_id", "entrance_type", DB::raw("MAX(id) as id"))->groupBy("order_id")->get()->filter(function ($event) {
                    return $event->entrance_type == 'manual_order';
                });

                $orders->each(function ($event) {
                    $this->calculateTotalOrder($event->order_id);
                });

                if ($orders->count() > 0) {
                    $order = Order::find($orders->first()->order_id);
                    $activity->status = isset($order->estado_pagamento) ? $this->getStatusMessage($order->estado_pagamento) : '';
                    $activity->order = $order;
                } elseif ($manual_orders->count() > 0) {
                    $manual_order = Order::find($manual_orders->first()->order_id);
                    $activity->status = isset($manual_order->estado_pagamento) ? $this->getStatusMessage($manual_order->estado_pagamento) : '';
                    $activity->order = $manual_order;
                } else {
                    $activity->order = null;
                    $activity->status = $activity->camera_analysis_status ? ucfirst(strtolower($activity->camera_analysis_status)) : 'Suspeito';
                }

                $manual_orders->each(function ($event) {
                    $this->calculateTotalOrder($event->order_id);
                });

                if ($manual_orders->count() > 0) {
                    $order = Order::find($manual_orders->first()->order_id);

                    $activity->manual_order_status = $this->getStatusMessage($order->estado_pagamento);
                    $activity->manual_order = $order;
                }

                $activity->orders_id = array_values(array_map(function ($order) {
                    $orderDB = Order::find($order['order_id']);
                    $productsSold = !empty($orderDB) ? $orderDB->produtos_vendidos()->select(["nome", "quantidade", "preco"])->get()->toArray() : [];
                    if (isset($orderDB->estado_pagamento) && in_array($orderDB->estado_pagamento, ['CAPTURADO', 'PAGO', 'PRE_AUTORIZADO'])) {
                        return ['id' => $orderDB->id ?? null, 'status' => true, "products_sold" => $this->formatterProductsSold($productsSold), "color" => "#00f000"];
                    } else {
                        return ['id' => $orderDB->id ?? null, 'status' => false, "products_sold" => $this->formatterProductsSold($productsSold), "failed_order_transfer" => $orderDB->transferencia ?? null, "color" => ($orderDB->estado_pagamento == "ESTORNO" ? "#f39c12" : "#f00a00")];
                    }
                }, $orders->toArray()));

                $activity->manual_orders_id = array_map(function ($order) {
                    $orderDB = Order::find($order['order_id']);
                    $productsSold = !empty($orderDB) ? $orderDB->produtos_vendidos()->select(["nome", "quantidade", "preco"])->get()->toArray() : [];
                    if (isset($orderDB->estado_pagamento) && in_array($orderDB->estado_pagamento, ['CAPTURADO', 'PAGO', 'PRE_AUTORIZADO'])) {
                        return ['id' => $orderDB->id ?? null, 'status' => true, "products_sold" => $this->formatterProductsSold($productsSold), "color" => "#00f000"];
                    } else {
                        return ['id' => $orderDB->id ?? null, 'status' => false, "products_sold" => $this->formatterProductsSold($productsSold), "failed_order_transfer" => $orderDB->transferencia ?? null, "color" => ($orderDB->estado_pagamento == "ESTORNO" ? "#f39c12" : "#f00a00")];
                    }
                }, $manual_orders->toArray());

                $activity->total = Formatter::currencyMoney($this->total);
                $formattedActivities->push($activity);
            }
        }
        return $formattedActivities->all();
    }

    private function getStatusMessage($estado_pagamento)
    {
        if (in_array($estado_pagamento, ['CAPTURADO', 'PAGO', 'PRE_AUTORIZADO'])) {
            return 'Sucesso';
        } elseif ($estado_pagamento == "ESTORNO") {
            return "Chargedback";
        }
        return 'Suspeito - Transação falhada';
    }

    private function calculateTotalOrder($id)
    {
        if ($id) {
            $order = Order::find($id);
            $value = 0;
            if (in_array($order->estado_pagamento, ['CAPTURADO', 'PAGO', 'PRE_AUTORIZADO'])) {
                foreach ($order->produtos_vendidos as $product) {
                    $value += $product->total_em_centavos();
                }
            }
            $this->total += intval(round($value));
        }
    }

    private function formatterProductsSold($products)
    {
        $formatted = "";
        foreach ($products as $product) {
            $formatted .= $product["quantidade"] . "X - " . $product["nome"] . " | ";
        }
        return count($products) > 0 ? substr($formatted, 0, -3) : "";
    }
}