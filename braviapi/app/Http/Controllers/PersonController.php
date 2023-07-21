<?php

namespace App\Http\Controllers;

use App\Http\Requests\Person\PersonCreateRequest;
use App\Http\Requests\Person\PersonUpdateRequest;
use App\Models\Person;
use App\Response\ApiResponse;
use App\Rules\Person\CreatePerson;
use App\Rules\Person\UpdatePerson;
use Exception;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function index(Request $request){
        try{
            return ApiResponse::sendResponse(Person::with('contacts')->get());
        }catch(Exception $exception) {
            return ApiResponse::sendError($exception);
        }
    }
    
    public function store(PersonCreateRequest $request, CreatePerson $createPerson){
        try{
            $payload = $request->validated();
            return ApiResponse::sendResponse($createPerson->execute($payload));
        }catch(Exception $exception) {
            return ApiResponse::sendError($exception);
        }
    }

    public function show(Request $request, $id){
        try{
            return ApiResponse::sendResponse(Person::findOrFail($id)->with('contacts')->get());
        }catch(Exception $exception) {
            return ApiResponse::sendError($exception);
        }
    }

    public function update(PersonUpdateRequest $request, UpdatePerson $updatePerson, $id){
        try{
            $payload = $request->validated();
            return ApiResponse::sendResponse($updatePerson->execute($payload, $id));
        }catch(Exception $exception) {
            return ApiResponse::sendError($exception);
        }
    }

    public function delete(Request $request, $id){
        try{
            $person = Person::findOrFail($id);
            return ApiResponse::sendResponse($person->delete());
        }catch(Exception $exception) {
            return ApiResponse::sendError($exception);
        }
    }
}
