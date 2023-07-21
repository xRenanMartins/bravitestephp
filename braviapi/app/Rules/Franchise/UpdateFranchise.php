<?php

namespace App\Rules\Franchise;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Packk\Core\Models\Address;
use Packk\Core\Models\Franchise;
use Packk\Core\Models\User;

class UpdateFranchise
{
    protected $payload;

    public function execute($payload)
    {
        $user = Auth::user();
        try {
            DB::beginTransaction();

            //Atualizar o endereço
            $name = explode(' ', $payload['name_franchisee']);
            $address = Address::query()->where('id', '=', $payload['address_id'])->first();
            $latlng = Address::get_lat_lng("{$payload['city']}, {$payload['district']}, {$payload['zip']}, {$payload['address']}, {$payload['number']}");
            $address->update([
                'endereco' => $payload['address'],
                'numero' => $payload['number'],
                'bairro' => $payload['district'],
                'cidade' => $payload['city'],
                'state' => $payload['state'],
                'country' => $payload['country'],
                'cep' => $payload['zip'],
                'latitude' => $latlng['lat'] ?? 0,
                'longitude' => $latlng['lng'] ?? 0,
            ]);

            //Atualizar o franqueado
            User::query()->where('id', '=', $payload['user_id'])
                ->update([
                    'nome' => $name[0],
                    'sobrenome' => $name[1] ?? '',
                    'cpf' => preg_replace('/[^0-9]/', '', $payload['cpf']),
                    'telefone' => preg_replace('/[^0-9]/', '', $payload['phone_franchisee']),
                    'email' => $payload['email'],
                ]);
            if (isset($payload['password']) && $payload['password'] != '') {
                User::query()->where('id', '=', $payload['user_id'])
                    ->update([
                        'password' => Hash::make($payload["password"]),
                    ]);
            }

            //Atualizar a franquia
            $franchise = Franchise::find($payload['franchise_id']);
            if (isset($payload['documents'])) {
                $documents = !empty($franchise->documents) ? json_decode($franchise->documents) : [];

                foreach ($payload['documents'] as $document) {
                    $filename = $document->getClientOriginalName();
                    $path = "domains/{$franchise->domain_id}/franchises/{$franchise->id}/docs/{$filename}";

                    Storage::disk('s3_packkbucket')->put($path, $document, 'public');
                    $documents[] = Storage::disk('s3_packkbucket')->url($path);
                }
                $franchise->update(['documents' => json_encode($documents)]);
            }

            $franchise->update([
                'name' => $payload['name'],
                'fantasy_name' => $payload['fantasy_name_franchisee'],
                'active' => filter_var($payload['active'], FILTER_VALIDATE_BOOLEAN),
                'cnpj' => preg_replace('/[^0-9]/', '', $payload['cnpj']),
                'address_id' => $payload['address_id'],
            ]);

            // Firebase Topic
            $franchise->saveFirebaseTopicToFranchise($payload);

            DB::commit();
            return [
                'status' => 200,
                'message' => 'Atualização efetuada com sucesso',
                'data' => $franchise
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return $e;
        }
    }
}