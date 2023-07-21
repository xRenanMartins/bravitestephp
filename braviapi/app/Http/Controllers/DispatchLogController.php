<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\Postgres\DispatchLog;

class DispatchLogController extends Controller
{
    public function filtered( Request $request ){

        $filter = $request->input();

        $query = DispatchLog::select(['id','created_at']);
        
        if(!empty($filter['start_at'])){
            $query = $query->startAt($filter['start_at']);
        }

        if (!empty($filter['finish_at'])) {
            $query = $query->finishAt($filter['finish_at']);
        }

        if (!empty($filter['shipper_id'])) {
            $query = $query->filterByShipper($filter['shipper_id']);
        }

        if (!empty($filter['order_id'])) {
            $query = $query->filterByOrder($filter['order_id']);
        }

        return $query->get();
    }

    public function getLog(Request $request, $id){
        return DispatchLog::find($id);
    }
}
