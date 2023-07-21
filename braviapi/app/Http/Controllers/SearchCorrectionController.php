<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Actions\Admin\Soundex\SearchCorrection as Soundex;
use Packk\Core\Models\SearchCorrection;

class SearchCorrectionController extends Controller
{
    private $payload;
    public function index(Request $request)
    {
        if(isset($request->start) && isset($request->length)){
            $total = $request->start/$request->length;
            $page = ($total+1) > 0 ? ceil($total) + 1 : 1;

            $request->merge([
                'page' => $page
            ]);
        }

        $date = null;
        if(isset($request->start_date) && isset($request->end_date)) {
            $date = [$request->start_date, $request->end_date];
        }
        
        return SearchCorrection::where('word', 'like', '%'.$request->word.'%')
        ->where('scope', 'like', '%'.$request->scope.'%')
        ->when($date, function($query, $date) {
            return $query->whereBetween("created_at", $date);
        })
        ->orderBy('created_at', 'desc')
        ->paginate($request->length);
    }

    public function create(Request $request){
        $payload = Soundex::createRules($request);
        return response([
            'success' => true,
        ]);
    }

    public function delete(Request $request, $id){
        $post = SearchCorrection::where([
            'id' => $id
        ])->first();
        $post->forceDelete();
        return response([
            'success' => true,
        ]);
    }

    public function update(Request $request, $id){
        $edit = Soundex::updateRules($request, $id);
        return response([
            'success' => true,
        ]);
    }
}