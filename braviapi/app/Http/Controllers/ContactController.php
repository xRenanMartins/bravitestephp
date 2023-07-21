<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contact\ContactIndexRequest;
use App\Response\ApiResponse;
use App\Rules\Contact\CreateContact;
use Exception;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(ContactIndexRequest $request){
        try{
            
            ApiResponse::sendResponse();
        }catch(Exception $exception) {
            ApiResponse::sendError($exception);
        }
    }
    
    public function store(ContactIndexRequest $request, CreateContact $createContact){
        try{
            
            ApiResponse::sendResponse();
        }catch(Exception $exception) {
            ApiResponse::sendError($exception);
        }
    }
}
