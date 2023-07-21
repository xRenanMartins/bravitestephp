<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packk\Core\Models\PaymentMethod;

class PaymentController extends Controller
{
    public function __construct()
    {

    }

    /**
     * @OA\Get(
     *   path="/payments", operationId="index_payments",summary="list Payment",tags={"Payment"},
     *   @OA\Response(response=200,description="A list with Payment",
     *     @OA\JsonContent(
     *          type="array",@OA\Items(ref="#/components/schemas/StorePayment")
     *     ),
     *   )
     * )
     */
    public function index()
    {
        return PaymentMethod::all();
    }

    /**
     * @OA\Post(
     *   path="/payments",operationId="store_payments",summary="store Payment",tags={"Payment"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the Payment",required=true,@OA\JsonContent(ref="#/components/schemas/StorePayment")
     *   ),
     *   @OA\Response(response=200,description="A list with Payments",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StorePayment"
     *     ),
     *   )
     * )
     */
    public function store(Request $request)
    {
        $payload = $this->validate($request, PaymentMethod::storeRules());
        $payment = PaymentMethod::create($payload);
        return $payment;
    }

    /**
     * @OA\Put(
     *   path="/payments/{PaymentId}",operationId="update_payments",summary="update a Payment",tags={"Payment"},
     *   @OA\Parameter(
     *      name="PaymentId",in="path",description="Payment id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A Payment",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/UpdatePayment"
     *     ),
     *   )
     * )
     */
    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, PaymentMethod::updateRules());
        try {
            DB::beginTransaction();
            $payment = PaymentMethod::findOrFail($id);
            $payment->update($payload);
            $payment->refresh();

            if (!empty($payment->block_domains)) {
                $blockDomains = Str::replace('[', '', $payment->block_domains);
                $blockDomains = Str::replace(']', '', $blockDomains);
                DB::table('payment_method_store')
                    ->join('lojas', 'lojas.id', '=', 'payment_method_store.store_id')
                    ->where('payment_method_store.payment_method_id', $payment->id)
                    ->whereNull('payment_method_store.deleted_at')
                    ->whereRaw("lojas.domain_id in ({$blockDomains})")
                    ->update([
                        'payment_method_store.is_active' => false,
                        'payment_method_store.deleted_at' => Carbon::now()
                    ]);
            }

            DB::commit();
            return $payment;
        } catch (\Exception $e) {
            DB::rollBack();
            return $e;
        }
    }

    /**
     * @OA\Delete(
     *   path="/payments/{PaymentId}",operationId="destroy_payments",summary="destroy Payment",tags={"Payment"},
     *   @OA\Parameter(
     *      name="PaymentId",in="path",description="Payment ID",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="deleted Payment",
     *   )
     * )
     */
    public function destroy($id)
    {
        try {
            $payment = PaymentMethod::findOrFail($id);
            $payment->delete();
            return response(true);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}