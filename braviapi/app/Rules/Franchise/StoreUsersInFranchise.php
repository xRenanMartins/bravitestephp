<?php

namespace App\Rules\Franchise;

use Illuminate\Support\Facades\Hash;
use Packk\Core\Models\User;
use Packk\Core\Models\RoleUser;
use Packk\Core\Models\UserFranchise;

class StoreUsersInFranchise
{
    protected $payload;
    public function __construct() {
    }

    public function execute($payload, $user)
    {
        if (isset($user)) {
            if ($user['tipo'] == 'M') {
                $name = explode(' ', $payload['name_franchisee']);

                $user_insert = new User();
                $user_insert->nome = $name[0];
                $user_insert->sobrenome = array_key_exists(1, $name) ? $name[1] : '';
                $user_insert->cpf = preg_replace('/[^0-9]/', '', $payload['cpf']);
                $user_insert->telefone = preg_replace('/[^0-9]/', '', $payload['phone_franchisee']);
                $user_insert->email = $payload['email'];
                $user_insert->tipo = 'FRANCHISEE';
                $user_insert->password = Hash::make($payload["password"]);
                $user_insert->password_temporario = 1;
                $user_insert->active = $payload['active'];
                $user_insert->save();

                $role = RoleUser::query()
                    ->where('name', '=', 'admin-franchise')
                    ->first();

                $query = new RoleUser();
                $query->user_id = $user_insert['id'];
                $query->role_id = $role['id'];
                $query->save();

                $user_franchise = new UserFranchise();
                $user_franchise->user_id = $user_insert['id'];
                $user_franchise->franchise_id = $payload['franchise_id'];
                $user_franchise->save();

                return [
                    'status' => 200,
                    'message' => 'Usuário criado com sucesso',
                    'data' => $user_insert
                ];
            } else {
                //usuário sem permissão para criar novos franqueados
                return [
                    'status' => 503,
                    'message' => 'Erro, usuário logado não possui permissão para realizar essa ação',
                    'data' => null
                ];
            }
        } else {
            //usuário logado não encontrado no banco de dados
            return [
                'status' => 503,
                'message' => 'Erro, o usuário não se encontra logado no sistema',
                'data' => null
            ];
        }


    }
}