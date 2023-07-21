<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Logger;
use Packk\Core\Actions\Admin\Loggers\GetLogOperation;

class LoggerController extends Controller
{
    public function index(Request $request)
    {
        $date = null;
        if (isset($request->start_date) && isset($request->end_date)) {
            $date = [$request->start_date, $request->end_date];
        }

        return Logger::query()
            ->leftJoin('users', 'users.id', '=', 'loggers.user_id')
            ->identic('type', $request->type)
            ->identic('reference_id', $request->reference_id)
            ->identic('reference_provider', $request->reference_provider)
            ->identic('status_code', $request->status_code)
            ->when($date, function ($query, $date) {
                return $query->whereBetween("loggers.created_at", $date);
            })
            ->when(!Auth::user()->hasAdminPrivileges(), function ($query) {
                return $query->identic('loggers.domain_id', currentDomain());
            })
            ->orderByDesc('loggers.created_at')
            ->selectRaw('loggers.id, loggers.created_at, loggers.resource, 
                        loggers.status_code, loggers.uri, loggers.time, loggers.type, loggers.source, 
                        loggers.reference_id, loggers.reference_provider, loggers.domain_id,
                        CONCAT(users.nome, " ", users.sobrenome) AS full_name')
            ->simplePaginate($request->length);
    }

    public function detail(Request $request, $id, $type)
    {
        return Logger::select('id', 'user_id', $type)->find($id);
    }

    public function types(Request $request)
    {
        $hasAdmin = Auth::user()->hasAdminPrivileges();
        $domain = $hasAdmin ? 'all' : currentDomain();
        return Cache::remember('types-loggers-' . $domain, 600, function () use ($domain, $hasAdmin) {
            return Logger::withTrashed()->select('type')
                ->when(!$hasAdmin, function ($query) use ($domain) {
                    return $query->identic('domain_id', $domain);
                })->distinct('type')->get();
        });
    }

    public function operation(Request $request, $type, GetLogOperation $getlog)
    {
        $validate = in_array($type, ['disable-stores', 'closed-stores', 'closed-all-stores', 'mode-rain-active', 'active-high-demand', 'region-recess-active']);
        if (!$validate) {
            throw new \Exception("O Tipo enviado é inválido.", 422);
        }

        $payload = $this->validate($request, ["type" => "sometimes|in:FECHAMENTO,ALTA_DEMANDA,ALTA_DEMANDA_TEMPORARIA", "store_name" => "sometimes"]);

        return $getlog->execute($type, $request->get('length', 20), $payload);
    }
}