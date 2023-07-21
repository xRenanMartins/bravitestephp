<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateOrder;
use Packk\Core\Scopes\DomainScope;
use Illuminate\Http\Request;
use Packk\Core\Models\Signature;
use Packk\Core\Util\Formatter;

class SignatureController extends Controller
{
    public function domain(Request $request)
    {
        $data = Signature::query()
            ->join('clientes', 'clientes.id', '=', 'signatures.customer_id')
            ->join('users', 'users.id', '=', 'clientes.user_id')
            ->join('lojas', 'lojas.id', '=', 'signatures.store_id')
            ->select([
                'signatures.id',
                'signatures.customer_id',
                'signatures.recurrence_type',
                'signatures.recurrence_value',
                'signatures.delivery_hour',
                'signatures.next_recurrence',
                'signatures.status',
                'lojas.nome as store_name',
            ])->when(!empty($request->domain_id), function ($query) use ($request) {
                $query->identic('signatures.domain_id', $request->domain_id);
            })->when(!empty($request->lojas), function ($query) use ($request) {
                $query->like('lojas.nome', $request->lojas);
            })->when(!empty($request->clientes), function ($query) use ($request) {
                $query->like('users.nome', $request->clientes);
            })->when(!empty($request->date), function ($query) use ($request) {
                $query->whereDate('signatures.next_recurrence', $request->date);
            })->when(!empty($request->status), function ($query) use ($request) {
                $query->whereIn('signatures.status', explode(',', $request->status));
            })->orderByDesc('signatures.next_recurrence')
            ->selectRaw('CONCAT(users.nome, " ", users.sobrenome) AS full_name')
            ->simplePaginate($request->length);

        $response = $data->toArray();
        foreach ($data->items() as $key => $value) {
            if ($value->recurrence_type == "WEEKLY") {
                $add = [];
                if (substr($value->recurrence_value, -7, 1) == '1') {
                    $add [] = ' Dom';
                }
                if (substr($value->recurrence_value, -6, 1) == '1') {
                    $add [] = ' Seg';
                }
                if (substr($value->recurrence_value, -5, 1) == '1') {
                    $add [] = ' Ter';
                }
                if (substr($value->recurrence_value, -4, 1) == '1') {
                    $add [] = ' Qua';
                }
                if (substr($value->recurrence_value, -3, 1) == '1') {
                    $add [] = ' Qui';
                }
                if (substr($value->recurrence_value, -2, 1) == '1') {
                    $add [] = ' Sex';
                }
                if (substr($value->recurrence_value, -1, 1) == '1') {
                    $add [] = ' Sáb';
                }
                $value['recurrence_value'] = $add;
            }
            $response['data'][$key] = $value;
        }

        return $response;
    }

    public function products(Request $request, $id)
    {
        $signatures = Signature::query()
            ->join('clientes', 'clientes.id', '=', 'signatures.customer_id')
            ->join('users', 'users.id', '=', 'clientes.user_id')
            ->join('lojas', 'lojas.id', '=', 'signatures.store_id')
            ->select('signatures.id')
            ->identic('signatures.id', $id)
            ->identic("signatures.status", "ACTIVE")
            ->orderByDesc('signatures.next_recurrence')
            ->get();

        $signatures->each(function ($signature) {
            $products = $signature->products()->get();
            $value = [];
            $n = 0;
            foreach ($products as $product) {
                $v = strval($n);
                $price = Formatter::currencyMoney($product->pivot->price);
                $value[$v] ["id"] = $product->id;
                $value[$v] ["product"] = $product->nome;
                $value[$v] ["price"] = $price;
                $n = $n + 1;
            }
            $signature->products = $value;
            return $signature;
        });

        return $signatures;
    }

    public function generateOrder(Request $request)
    {
        $payload = $this->validate($request, ["id" => "required|exists:signatures,id"]);
        $data = Signature::find($request->id);

        dispatch(new GenerateOrder($payload["id"], $data->domain_id));

        return response()->json([
            'success' => true,
            'message' => 'Aguarde... O pedido está sendo gerado.',
        ]);
    }

}