<?php

namespace App\Rules\Franchise;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Packk\Core\Models\Address;
use Packk\Core\Models\Franchise;
use Packk\Core\Models\Role;
use Packk\Core\Models\RoleUser;
use Packk\Core\Models\User;
use Packk\Core\Models\UserFranchise;

class StoreFranchise
{
    protected $payload;

    public function execute($payload)
    {
        try {
            DB::beginTransaction();
            $name = explode(' ', $payload['name_franchisee']);

            //Criando o usuário
            $user = new User();
            $user->nome = $name[0];
            $user->sobrenome = array_key_exists(1, $name) ? $name[1] : '';
            $user->cpf = preg_replace('/[^0-9]/', '', $payload['cpf']);
            $user->telefone = preg_replace('/[^0-9]/', '', $payload['phone_franchisee']);
            $user->email = $payload['email'];
            $user->tipo = 'FRANCHISEE';
            $user->password = Hash::make($payload["password"]);
            $user->password_temporario = 1;
            $user->domain_id = currentDomain();
            $user->status = $payload['active'] ? 'ATIVO' : 'INATIVO';
            $user->save();

            //Criando o endereço
            $address = new Address();
            $address->endereco = $payload['address'];
            $address->numero = $payload['number'];
            $address->bairro = $payload['district'];
            $address->cidade = $payload['city'];
            $address->state = $payload['state'];
            $address->complemento = '';
            $address->domain_id = currentDomain();
            $address->country = $payload['country'];
            $address->cep = $payload['zip'];

            try {
                $latlng = Address::get_lat_lng("{$payload['city']}, {$payload['district']}, {$payload['zip']}, {$payload['address']}, {$payload['number']}");
            } catch (\Exception) {
                throw new \Exception('Não foi possível determinar a latitude/longitude do endereço informado', 1);
            }

            $address->latitude = $latlng['lat'] ?? 0;
            $address->longitude = $latlng['lng'] ?? 0;
            $address->save();

            //Criando a franquia
            $franchise = new Franchise();
            $franchise->name = $payload['name'];
            $franchise->fantasy_name = $payload['fantasy_name_franchisee'];
            $franchise->cnpj = preg_replace('/[^0-9]/', '', $payload['cnpj']);
            $franchise->active = filter_var($payload['active'], FILTER_VALIDATE_BOOLEAN);
            $franchise->domain_id = currentDomain();
            $franchise->address_id = $address['id'];

            if (isset($payload['documents'])) {
                $documents = [];
                foreach ($payload['documents'] as $document) {
                    $filename = $document->getClientOriginalName();
                    $path = "domains/{$franchise->domain_id}/franchises/{$franchise->id}/docs/{$filename}";

                    Storage::disk('s3_packkbucket')->put($path, $document, 'public');
                    $documents[] = Storage::disk('s3_packkbucket')->url($path);
                }
                $franchise->documents = json_encode($documents);
            }
            $franchise->save();

            //Criando a relação n/n
            $user_franchise = new UserFranchise();
            $user_franchise->user_id = $user['id'];
            $user_franchise->franchise_id = $franchise['id'];
            $user_franchise->save();

            //Criar roule
            $query_role = Role::query()->where('name', '=', 'admin-franchise')->first();

            $role = new RoleUser();
            $role->user_id = $user['id'];
            $role->role_id = $query_role['id'];
            $role->save();

            // Firebase Topic
            $franchise->saveFirebaseTopicToFranchise($payload);

            DB::commit();
            return response()->json([
                'status' => 200,
                'message' => 'Franquia criada com sucesso'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}