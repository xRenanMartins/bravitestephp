<?php

namespace App\Rules\Voucher;

use Carbon\Carbon;
use Packk\Core\Models\Order;
use Packk\Core\Models\Voucher;

class VerifyVoucher
{
    public static function verifyByKey($key, $voucher_id = null)
    {
        $query = Voucher::where("chave", $key)->where("validade", ">", Carbon::now());
        if (isset($voucher_id)) {
            $query->where("id", "!=", $voucher_id);
        }
        $vouchers = $query->get();
        foreach ($vouchers as $voucher) {
            $qty_uses = Order::where("estado", "F")->where("voucher_id", $voucher->id)->count();
            if ($qty_uses < $voucher->quantidade_total) {
                return false;
            }
        }
        return true;
    }
}
