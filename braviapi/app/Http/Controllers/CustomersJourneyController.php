<?php

namespace App\Http\Controllers;

use App\Rules\CustomersJourney\ListCustomersJourney;
use App\Rules\CustomersJourney\SetCameraAnalysis;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Store;
use Packk\Core\Models\Product;
use Packk\Core\Models\Order;
use Packk\Core\Models\StolenStore;
use Packk\Core\Models\CustomerJourney;
use Packk\Core\Integration\Payment\Card;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Packk\Core\Actions\Customer\Orders\StoreOrder;

class CustomersJourneyController extends Controller
{
    public function index(Request $request, ListCustomersJourney $customersJourney)
    {
        $result = $this->getUserAuth($request);
        if (is_null($result)) {
            return response()->json(['message' => 'Token inválido'], 426);
        }
        $payload = $this->payload($request);
        return $customersJourney->execute($payload);
    }

    public function getOrderDetails(Request $request)
    {
        try {
            $result = $this->getUserAuth($request);
            if (is_null($result)) {
                return response()->json(['message' => 'Token inválido'], 426);
            }
            $order = Order::query()->findOrFail($request->order_id);
            $products = $order->products_sold()->select(["nome", "quantidade", "preco"])->get()->toArray();
            $customer = $order->customer->user;

            return response()->json([
                'id' => $order->id,
                'payment_method' => $order->payment_method->name,
                'total' => $order->valor,
                'customer_name' => $customer->nome_completo,
                'customer_phone' => $customer->telefone,
                'products' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json(["success" => false, "message" => $e->getMessage()], 400);
        }
    }

    public function getStolenStore(Request $request, $id)
    {
        try {
            $result = $this->getUserAuth($request);
            if (is_null($result)) {
                return response()->json(['message' => 'Token inválido'], 426);
            }
            $stolenProducts = StolenStore::query()
                ->select(['id', 'name', 'quantity', 'value', 'payment_method', 'payed', 'created_at'])
                ->where('customer_journey_id', $id)
                ->get()->toArray();

            return response()->json($stolenProducts);
        } catch (\Exception $e) {
            return response()->json(["success" => false, "message" => $e->getMessage()], 400);
        }
    }

    public function productSearch(Request $request)
    {
        return Product::query()->select('id', 'preco', 'nome')
            ->where('nome', 'like', "%{$request->term}%")
            ->where('store_id', $request->store_id)
            ->where('domain_id', currentDomain())
            ->paginate(10);
    }

    public function storeSearch(Request $request)
    {
        return Store::query()->select('id', 'nome')
            ->where('domain_id', currentDomain())
            ->where('nome', 'like', "%{$request->term}%")
            ->paginate(10);
    }

    public function updateStolenProduct(Request $request, $id)
    {
        try {
            $payload = $this->validate($request, [
                'payment_method' => 'sometimes',
                'payed' => 'sometimes',
            ]);
            $result = $this->getUserAuth($request);
            if (is_null($result)) {
                return response()->json(['message' => 'Token inválido'], 426);
            }
            StolenStore::where("id", $id)->update($payload);
            return response()->json(["success" => true, "message" => "Atualizado com sucesso"]);
        } catch (\Exception $e) {
            return response()->json(["success" => false, "message" => $e->getMessage()], 400);
        }
    }

    public function removeStolenProduct(Request $request, $id)
    {
        try {
            $result = $this->getUserAuth($request);
            if (is_null($result)) {
                return response()->json(['message' => 'Token inválido'], 426);
            }
            StolenStore::where("id", $id)->delete();
            return response()->json(["success" => true, "message" => "Excluído com sucesso"]);
        } catch (\Exception $e) {
            return response()->json(["success" => false, "message" => $e->getMessage()], 400);
        }
    }

    public function setCameraAnalysis(Request $request, $id, SetCameraAnalysis $setCameraAnalysis)
    {
        try {
            DB::beginTransaction();
            $result = $this->getUserAuth($request);
            if (is_null($result)) {
                return response()->json(['message' => 'Token inválido'], 426);
            }
            $response = $setCameraAnalysis->execute($id, $this->payload($request), $result);
            DB::commit();
            return response()->json(['success' => true, 'message' => "Salvo com sucesso", 'data' => $response]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function makeOrder(Request $request, StoreOrder $storeOrder)
    {
        try {
            DB::beginTransaction();
            $result = $this->getUserAuth($request);
            if (is_null($result)) {
                return response()->json(['message' => 'Token inválido'], 426);
            }
            $journey = CustomerJourney::query()->findOrFail($request->customers_journey_id);
            $customer = Customer::query()->findOrFail($journey->client_id);

            $cards = (new Card)->customerCards($customer->id);
            if (isset($cards) && count($cards) > 0) {
                $buyerId = $cards[0]->id;
            } else {
                throw new \Exception('Nenhum cartão de crédito cadastrado para o cliente');
            }

            $data = collect([]);
            $data->zoop_buyer_id = $buyerId ?? null;
            $data->store_id = $journey->store_id;
            $data->customer_id = $customer->id;
            $data->products = json_decode(json_encode($request->products));
            $data->cpf_card = $customer->user->cpf;
            $data->card_last_number = null;
            $data->card_bin = null;
            $data->token = null;
            $data->journey_id = $journey->id;
            $data->payment_method = 'CARTAO_CREDITO';
            $data->event_type = 'manual_order';
            $data->transf_aut_lojista = false;

            $orderNew = $storeOrder->execute($data);

            if (isset($orderNew['status']) && $orderNew['status'] == 'ok') {
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Pagamento do pedido feito com sucesso', 'order' => $orderNew]);
            } else {
                throw new \Exception('Pagamento do novo pedido falhado');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function getUserAuth(Request $request)
    {
        $setting = DB::table('setting_user')
            ->selectRaw('setting_user.user_id')
            ->join('settings', 'settings.id', '=', 'setting_user.setting_id')
            ->where('settings.label', 'customers_journey_token')
            ->where('setting_user.value', $request->token)
            ->first();
        if (empty($setting)) {
            return null;
        }
        return $setting->user_id;
    }
}