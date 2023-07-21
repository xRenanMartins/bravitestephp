<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\FirebaseTopic;

class FirebaseTopicController extends Controller
{
    public function index(Request $request)
    {
        return FirebaseTopic::query()
            ->identic('type', $request->type)
            ->like('firebase_topics.name', $request->name)
            ->orderBy('firebase_topics.created_at', 'desc')
            ->simplePaginate($request->length);
    }

    public function store(Request $request)
    {
        $payload = $this->validate($request, [
            "name" => "required",
            "description" => "required",
            "domain_id" => "sometimes",
            "type" => "required"
        ]);

        if (FirebaseTopic::where('description', $payload['description'])->count() > 0) {
            throw new \Exception("A descrição já existe", 400);
        } else {
            try {
                $fire = FirebaseTopic::create($payload);

                return response([
                    'success' => true,
                    'data' => $fire
                ]);
            } catch (\Throwable $th) {
                throw $th;
            }
        }
    }

    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, [
            "name" => "required",
            "description" => "required",
            "domain_id" => "sometimes",
            "type" => "required"
        ]);

        if (FirebaseTopic::where('description', $payload['description'])->where('id', '<>', $id)->count() > 0) {
            throw new \Exception("A descrição já existe", 400);
        } else {
            try {
                $fire = FirebaseTopic::find($id);
                $fire->update($payload);

                return response([
                    'success' => true,
                    'data' => $fire
                ]);
            } catch (\Throwable $th) {
                throw $th;
            }
        }
    }

    public function destroy(Request $request, $id)
    {
        $firebase = FirebaseTopic::find($id);

        if ($firebase->franchise()->exists()) {
            throw new \Exception('Não é possível remover esse item pois está vinculado a uma franquia');
        }
        if ($firebase->news()->exists()) {
            throw new \Exception('Não é possível remover esse item pois está vinculado a uma novidade');
        }
        if ($firebase->voucher()->exists()) {
            throw new \Exception('Não é possível remover esse item pois está vinculado a um voucher');
        }
        if ($firebase->area_served()->exists()) {
            throw new \Exception('Não é possível remover esse item pois está vinculado a uma zona');
        }

        $firebase->delete();
        return response([
            'success' => true,
        ]);
    }
}