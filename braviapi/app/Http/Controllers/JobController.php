<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Job;
use Packk\Core\Models\User;

class JobController extends Controller
{
    public function index(Request $request)
    {
        if(isset($request->start) and isset($request->length)){
            $total = $request->start/$request->length;
            $page = ($total+1) > 0 ? ceil($total) + 1 : 1;

            $request->merge([
                'page' => $page
            ]);
        }

        return Job::identic('id', $request->id)
        ->identic('class', $request->class)
        ->orderBy('created_at')
        ->paginate($request->length);
    }
    public function call(Request $request, $id)
    {
        $params = User::where('email', $request->user)->get('id');
        $payload = [
            'job_id'  => $id,
            'user_id' => $params[0]->id,
            'created_at' => now(), 
            'updated_at' => now()
        ];
        DB::connection('utils')->table('mslgc_user_jobs')->insert($payload);
        return Job::PrepareToCall($id);
    }
    public function create(Request $request){
        $job = new Job;
        $job->active = $request['active'];
        $job->type = $request['type'];
        $job->class = $request['class'];
        $job->description = $request['description'];
        $job->parameters  = $request['parameters'];
        return $job->save();      
    }
    public function edit(Request $request, $id){
    return Job::where('id', $id)
    ->update(['type' => $request['type']]);        
    }

}