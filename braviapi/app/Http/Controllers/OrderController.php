<?php

namespace App\Http\Controllers;

use App\Response\ApiResponse;
use App\Rules\Order\ChangeOrderSchedule;
use App\Rules\Order\GetScheduleStore;
use App\Rules\Order\ListOrders;
use App\Rules\Sales\ReverseTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Packk\Core\Actions\Admin\Customer\VerifyCrediCard;
use Packk\Core\Actions\Admin\Order\AcceptOrder;
use Packk\Core\Actions\Admin\Order\Antifraud\AntifraudLog;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Integration\BigID\People;
use Packk\Core\Models\Reason;
use Illuminate\Support\Facades\Cache;
use Packk\Core\Models\Schedule;
use Packk\Core\Models\Store;
use Packk\Core\Models\Domain;
use Packk\Core\Models\Franchise;
use Packk\Core\Models\Category;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Models\Order;
use Packk\Core\Actions\Admin\Order\RemakeOrder;
use Packk\Core\Actions\Admin\Order\StopOrder;

class OrderController extends Controller
{
    public function index(Request $request, ListOrders $listOrders)
    {
        return $listOrders->execute($request);
    }

    public function selectFilters(Request $request)
    {
        $franchise = $request->get('franchise_id', 'all');
        $domainId = currentDomain();

        $stores = Cache::remember("orderStoreFilter.{$domainId}.{$franchise}", 600, function () use ($request) {
            $user = Auth::user();
            return Store::query()
                ->when(!empty($request->get('franchise_id')), function ($query) use ($request) {
                    $query->where('franchise_id', $request->franchise_id);
                })->when($user->isFranchiseOperator(), function ($query) {
                    $query->whereNotNull('franchise_id');
                })->select('id', 'nome')->get();
        });

        $categories = Cache::remember("orderCategoryFilter.{$domainId}", 600, function () {
            return Category::query()->select('id', 'nome')->where('tipo', 'L')->get()->toArray();
        });

        $franchises = !$request->franchise_id ? Franchise::query()->select('id', 'name')->get()->toArray() : [];
        $domains = !$request->franchise_id ?
            Cache::remember("orderDomainFilter", 1200, function () {
                return Domain::query()->select('id', 'title as name')->get()->toArray();
            }) : [];

        return [
            'stores' => $stores,
            'categories' => $categories,
            'franchises' => $franchises,
            'domains' => $domains,
        ];
    }

    public function export(Request $request, ListOrders $listOrders)
    {
        return $listOrders->export($request);
    }

    public function reasonsToRemakeOrder()
    {
        return Reason::withoutGlobalScope(DomainScope::class)
            ->where('tipo', 'REMAKE_ORDER')->where('ativo', 1)
            ->selectRaw('id, descricao, feedback_message IS NULL as require_description')->orderBy('descricao')->get();
    }

    public function remakeOrder(Request $request, RemakeOrder $remakeOrder)
    {
        $payload = $request->validate([
            'order_id' => 'required',
            'value' => 'required',
            'description' => 'sometimes',
            'reason_id' => 'required',
            'send_deliveryman' => 'nullable',
            'type' => 'nullable',
            'product' => 'nullable',
        ]);

        return $remakeOrder->execute($payload);
    }

    public function canceledReason($orderId)
    {
        $order = Order::find($orderId);

        if ($order->estado == 'C') {
            if (empty($order->motivo_id)) {
                return response()->json(["descricao" => $order->info->reason_refuse]);
            }
            return response()->json(["descricao" => $order->motive->descricao]);
        }

        throw new \Exception('O pedido não está cancelado');
    }

    public function antifraudlog(int $orderId, AntifraudLog $antifraudLog)
    {
        try {
            return response()->json($antifraudLog->execute($orderId));
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function consultCpf(Request $request)
    {
        try {
            $people = new People(currentDomain(true));
            return response()->json($people->get($request));
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function validateCard(Request $request)
    {
        try {
            $amount = str_replace(',', '.', $request->value);
            $request->merge(['value' => floatval($amount) * 100]);

            $rule = new VerifyCrediCard();
            return response()->json($rule->execute($request));
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function reverseTransaction(Request $request, ReverseTransaction $reverseTransaction)
    {
        try {
            $amount = str_replace(',', '.', $request->value);
            $request->merge(['value' => floatval($amount) * 100]);

            $result = $reverseTransaction->execute($request);
            if (is_null($result)) {
                return response()->json(['success' => false, 'message' => 'Dados não conferem']);
            }
            return response()->json(['success' => true, 'message' => 'Valor estornado e cartão validado', 'resp' => $result]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function acceptAntifraud(Request $request)
    {
        try {
            $request->merge(['antifraud' => true]);
            $accept = new AcceptOrder();
            return $accept->execute($request);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function stopOrder(Request $request)
    {
        try {
            $payload = $request->validate(['order_id' => 'required']);
            $stop = new StopOrder();
            $stop->execute($payload['order_id']);

            return response()->json(['success' => true, 'message' => 'Pedido alterado com sucesso!!']);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getEditSchedule(Request $request, $orderId)
    {
        try {
            $order = Order::findOrFail($orderId);
            $reasons = Reason::query()
                ->where('tipo', 'CHANGE_SCHEDULE')->where('ativo', 1)
                ->selectRaw('id, descricao as description')->orderBy('descricao')->get();

            $schedule = GetScheduleStore::execute($order->loja_id);
            return ApiResponse::sendResponse([
                'reasons' => $reasons,
                'schedule' => $schedule
            ]);
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function editSchedule(Request $request, $orderId, ChangeOrderSchedule $changeOrderSchedule)
    {
        try {
            DB::beginTransaction();
            $result = $changeOrderSchedule->execute($orderId, $request->all());
            DB::commit();
            return ApiResponse::sendResponse($result);
        } catch (CustomException $e) {
            DB::rollBack();
            return ApiResponse::sendError($e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($e);
        }
    }
}
