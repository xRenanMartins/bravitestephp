<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\AppIdentifier;
use Packk\Core\Scopes\DomainScope;

class IdentifierController extends Controller
{
    public function index(Request $request){ 
        return AppIdentifier::withoutGlobalScope(DomainScope::class)
            ->identic('id', $request->id)
            ->like('identifier', $request->identifier)
            ->identic('user_id', $request->user_id) 
            ->identic('os', $request->os)
            ->identic('whitelist', $request->whitelist) 
            ->simplePaginate($request->length);
    }

    public function updateWhitelist(Request $request, $id){
        $payload = $this->validate($request, [
            'whitelist' => 'required'
        ]);
        
        $identifier = AppIdentifier::findOrFail($id);
        $identifier->update($payload);
        return response([
            'success' => true,
        ]);
    }
}
