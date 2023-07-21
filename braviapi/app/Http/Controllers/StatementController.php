<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\Statement;

class StatementController extends Controller
{
    public function update($id, Request $request)
    {
        $payload = $this->validate($request, ["status" => "required|in:CANCELLED"]);

        try {
            Statement::where("id", $id)->update($payload);

            return response()->json(["success" => true]);
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}