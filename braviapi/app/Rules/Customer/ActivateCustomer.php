<?php

namespace App\Rules\Customer;

use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Rekognition;
use Packk\Core\Models\User;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Util\Formatter;

class ActivateCustomer
{
    public function execute($id, $adminUser)
    {
        try {
            DB::beginTransaction();

            $customer = Customer::withoutGlobalScope(DomainScope::class)->findOrFail($id);
            $clientUser = User::withoutGlobalScope(DomainScope::class)->findOrFail($customer->user_id);

            if ($clientUser->status == 'ATIVO') {
                throw new \Exception('O cliente já está ativo!');
            }

            $clientText = "Cliente: {$customer->id} - {$clientUser->nome_completo} - {$clientUser->email}";
            $message = ['[::text]' => "{$clientText}, ativado por usuário: {$adminUser->id} - {$adminUser->nome_completo}"];
            $clientUser->addAtividade('ACTIVE_USER', $message, $adminUser->id, 'ADMIN');

            $clientUser->status = 'ATIVO';

            $indexFaces = (new Rekognition())->indexFaces('ClientesAtivos', $customer);
            if (isset($indexFaces['result']['FaceRecords'][0]['Face']['FaceId'])) {
                $clientUser->active_face_id = $indexFaces['result']['FaceRecords'][0]['Face']['FaceId'];
            }

            $clientUser->save();
            DB::commit();
            return ['status' => $clientUser->status];
        } catch (\Exception $e) {
            DB::rollBack();
            return Formatter::exception($e);
        }
    }
}