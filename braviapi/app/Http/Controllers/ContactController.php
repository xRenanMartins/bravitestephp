<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contact\ContactCreateRequest;
use App\Http\Requests\Contact\ContactUpdateRequest;
use App\Models\Contact;
use App\Response\ApiResponse;
use App\Rules\Contact\CreateContact;
use App\Rules\Contact\UpdateContact;
use Exception;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request){
        try{
            return ApiResponse::sendResponse(Contact::select('*')->where('person_id', $request->id)->simplePaginate());
        }catch(Exception $exception) {
            return ApiResponse::sendError($exception);
        }
    }
    
    public function store(ContactCreateRequest $request, CreateContact $createContact){
        try{
            $payload = $request->validated();
            return ApiResponse::sendResponse($createContact->execute($payload));
        }catch(Exception $exception) {
            return ApiResponse::sendError($exception);
        }
    }

    public function show(Request $request, $id){
        try{
            return ApiResponse::sendResponse(Contact::findOrFail($id)->first());
        }catch(Exception $exception) {
            return ApiResponse::sendError($exception);
        }
    }

    public function update(ContactUpdateRequest $request, UpdateContact $updateContact, $id){
        try{
            $payload = $request->validated();
            return ApiResponse::sendResponse($updateContact->execute($payload, $id));
        }catch(Exception $exception) {
            return ApiResponse::sendError($exception);
        }
    }

    public function delete(Request $request, $id){
        try{
            $person = Contact::findOrFail($id);
            return ApiResponse::sendResponse($person->delete());
        }catch(Exception $exception) {
            return ApiResponse::sendError($exception);
        }
    }
}
