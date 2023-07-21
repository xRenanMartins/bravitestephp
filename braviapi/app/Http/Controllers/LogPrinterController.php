<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\LogPrinter;
use Packk\Core\Actions\Admin\Shopkeeper\GetLogPrinter;
use Packk\Core\Actions\Admin\Shopkeeper\Printer\LogPrinter as Log;

class LogPrinterController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type;

        if (isset($request->start) and isset($request->length)) {
            $total = $request->start / $request->length;
            $page = ($total + 1) > 0 ? ceil($total) + 1 : 1;

            $request->merge([
                'page' => $page
            ]);
        }

        $date = null;
        if (isset($request->start_date) && isset($request->end_date)) {
            $date = [$request->start_date, $request->end_date];
        }

        return LogPrinter::where('type', 'like', '%' . $type . '%')
            ->when($date, function ($query, $date) {
                return $query->whereBetween("created_at", $date);
            })
            ->select(
                "shopkeeper_id",
                "type",
                "version",
                "os",
                "trace",
                "message",
                "class",
                "method",
                "created_at as date"
            )
            ->orderBy('created_at', 'desc')
            ->paginate($request->length);
    }
    public function logPrinter(Request $request, $id, Log $logPrinter)
    {
        $payload = ["id" => $id];
        $logs = $logPrinter->execute($payload, $request);

        return $logs;
    }
    public function getLogPrinter($id, GetLogPrinter $logPrinter){
        return $logPrinter->execute($id);
    }
}
