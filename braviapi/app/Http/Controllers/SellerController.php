<?php

namespace App\Http\Controllers;

use App\Rules\Store\CreateSeller;
use App\Rules\Store\GetMcc;
use App\Rules\Store\UpdateSeller;
use App\Validation\SellerValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Packk\Core\Integration\Payment\BankAccount;
use Packk\Core\Integration\Payment\Seller;
use Packk\Core\Integration\Payment\Zoop;
use Packk\Core\Models\Franchise;
use Packk\Core\Models\Store;

class SellerController extends Controller
{
    public function store(Request $request)
    {
        $payload = $this->validate($request, SellerValidation::storeRules());

        if (isset($payload['franchise_id'])) {
            return (new \App\Rules\Franchise\CreateSeller())->execute($payload);
        } else {
            return (new CreateSeller())->execute($payload);
        }
    }

    public function show(Request $request, $zoopSellerId)
    {
        try {
            if (!empty($request->store_id)) {
                $store = Store::findOrFail($zoopSellerId);
                $zoopSellerId = $store->zoop_seller_id;
                if (empty($zoopSellerId)) {
                    return response()->json(["success" => false]);
                }
            }

            $seller = new Seller($zoopSellerId, true);
            if (empty($seller)) {
                throw new \Exception('Seller não encontrado!');
            }
            $bankAccount = new BankAccount($seller->default_credit, true);

            if (!isset($bankAccount->account_number)) {
                $internalBankAccount = \Packk\Core\Models\BankAccount::query()
                    ->when(!empty($request->store_id), function ($q) use ($request) {
                        $q->where('loja_id', $request->store_id);
                    })->when(!empty($request->franchise_id), function ($q) use ($request) {
                        $q->where('franchise_id', $request->franchise_id);
                    })->first();

                if (!empty($internalBankAccount)) {
                    $bankAccount = [
                        'bank_code' => $internalBankAccount->banco,
                        'routing_number' => $internalBankAccount->agencia,
                        'account_number' => $internalBankAccount->conta,
                        'holder_name' => $internalBankAccount->favorecido,
                        'type' => $internalBankAccount->tipo,
                    ];
                } else {
                    $bankAccount = [
                        'sem' => true
                    ];
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return response()->json([
            "bankAccount" => $bankAccount ?? null,
            "seller" => $seller ?? null,
            "seller_id" => $zoopSellerId,
            'is_market' => !empty($store) ? $store->isMarket() && !$store->getSetting('is_test') : false,
            'success' => true
        ]);
    }

    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, SellerValidation::updateRules());
        if (isset($payload['franchise_id'])) {
            return (new \App\Rules\Franchise\UpdateSeller())->execute($payload);
        } else {
            return (new UpdateSeller())->execute($payload);
        }
    }

    public function searchZoopSeller(Request $request, Seller $seller)
    {
        try {
            $domain = currentDomain(true);
            $payload = $this->validate($request, [
                "store_id" => "sometimes",
                "franchise_id" => "sometimes",
                "document" => "required_without:seller_id",
                "seller_id" => "sometimes"
            ]);

            if (isset($payload["seller_id"])) {
                try {
                    $seller = (new Seller($payload["seller_id"], true));
                    $payload["document"] = $seller->ein;
                } catch (\Throwable $th) {
                    if (isset($payload["store_id"])) {
                        $store = Store::select("cnpj")->where("zoop_seller_id", $payload["seller_id"])->first();
                        $payload["document"] = $store->cnpj;
                    } else {
                        $franchise = Franchise::select("cnpj")->where("zoop_seller_id", $payload["seller_id"])->first();
                        $payload["document"] = $franchise->cnpj;
                    }
                }
            }

            if (strlen($payload["document"]) <= 11) {
                if (isset($payload["store_id"])) {
                    $sendPayload = [
                        "taxpayer_id" => $payload["document"],
                        "reference_id" => $payload["store_id"],
                        "reference_provider" => "loja-{$domain->name}"
                    ];
                } else {
                    $sendPayload = [
                        "taxpayer_id" => $payload["document"],
                        "reference_provider" => "loja-{$domain->name}"
                    ];
                }

            } else {
                if (isset($payload["store_id"])) {
                    $sendPayload = [
                        "ein" => $payload["document"],
                        "reference_id" => $payload["store_id"],
                        "reference_provider" => "loja-{$domain->name}"
                    ];
                } else {
                    $sendPayload = [
                        "ein" => $payload["document"],
                        "reference_provider" => "loja-{$domain->name}"
                    ];
                }

            }

            return response()->json($seller->searchSellerZoop($sendPayload), 200);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function searchStores(Request $request)
    {
        $stores = Store::select("zoop_seller_id AS id", "nome")
            ->where("nome", "like", "{$request->term}%")
            ->whereNotNull("zoop_seller_id")
            ->get()->toArray();
        return response()->json($stores);
    }

    public function link(Request $request, $id)
    {
        $payload = $this->validate($request, ["zoop_seller_id" => "required"]);
        try {
            $store = Store::findOrFail($id);
            $store->zoop_seller_id = $payload["zoop_seller_id"];
            $store->save();
            $store->activeDefaultPaymentMethods(true);
            return response()->json(["success" => true]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getMcc()
    {
        try {
            return response()->json(Cache::remember('zoop.mcc', 7200, function () {
                return (new Zoop())->mcc();
            }));
        } catch (\Exception) {
            Cache::forget('zoop.mcc');
            return GetMcc::fallback();
        }
    }
}