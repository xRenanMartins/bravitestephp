<?php

namespace App\Rules\Customer;

use App\Utils\Files;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Packk\Core\Exceptions\RuleException;
use Packk\Core\Models\Activity;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Rekognition;
use Packk\Core\Models\User;
use Packk\Core\Models\UserActivity;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Util\Phones;

class UpdateClient
{
    private $customer;
    private $adminUser;
    private $request;

    public function execute(Request $request, $id, $adminUser)
    {
        $this->request = $request;
        $this->adminUser = $adminUser;

        try {
            DB::beginTransaction();
            $this->customer = Customer::withoutGlobalScope(DomainScope::class)->find($id);

            $this->customer->shipp_gold_at = !empty($request->shipp_gold_at) ? $request->shipp_gold_at : null;
            $this->customer->e_funcionario = ($request->e_funcionario || $request->e_funcionario == 1) ? 1 : 0;

            $this->customer->user->status = ($request->suspeito || $request->suspeito == 1) ? 'SUSPEITO' : 'ATIVO';
            $this->customer->user->nome = $request->nome;
            $this->customer->user->sobrenome = ($request->sobrenome ?? ' ');
            $this->customer->user->email = $request->email;
            $this->customer->user->telefone = Phones::format($request->telefone);
            $this->customer->user->borned_at = $request->borned_at;

            if (Auth::check() && Auth::user()->hasPermission('change_acom_id')) {
                $this->customer->user->acom_token = $request->acom_id;
            }

            $this->validateCpf();

            $this->updatePassword();

            $this->customer->add_credits($request->creditos - $this->customer->get_credits(), 'PAINEL_ADMIN');

            $this->updatePhoto();

            $this->setBlacklist();

            $this->customer->user->save();
            $this->customer->save();
            DB::commit();

            return response([
                'success' => true,
                'customer' => $this->customer
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function validateCpf()
    {
        if (isset($this->request->cpf)) {
            $exists = User::withoutGlobalScope(DomainScope::class)
                ->where('domain_id', $this->customer->domain_id)
                ->where('cpf', $this->request->cpf)
                ->where("tipo", 'C')
                ->where('id', '<>', $this->customer->user->id)
                ->exists();
            if (!$exists) {
                $this->customer->user->cpf = $this->request->cpf;
            } else {
                throw new RuleException("CPF Existente", "O Cpf Informado já está associado a outro usuário", 430);
            }
        }
    }

    private function updatePhoto()
    {
        if (isset($this->request->foto_perfil)) {
            // deleta a face escaneada entre os clientes ativos
            if (!empty($this->customer->user->active_face_id)) {
                $deletedFaces = (new Rekognition())->deleteFaces('ClientesAtivos', [$this->customer->user->active_face_id]);
                if (in_array($this->customer->user->active_face_id, $deletedFaces['DeletedFaces'])) {
                    $this->customer->user->active_face_id = null;
                }
            }

            $imageUrl = Files::saveFromBase64($this->request->foto_perfil, "users/{$this->customer->user->id}/profile/", $this->request->imagemName);

            $this->customer->user->foto_perfil_s3 = $imageUrl;
            $this->customer->user->foto_perfil = $imageUrl;
            $this->customer->user->save();
            $this->customer->save();

            $indexFaces = (new Rekognition)->indexFaces('ClientesAtivos', $this->customer);
            if (isset($indexFaces['result']['FaceRecords'][0]['Face']['FaceId'])) {
                $this->customer->user->active_face_id = $indexFaces['result']['FaceRecords'][0]['Face']['FaceId'];
            }
        }
    }

    private function updatePassword()
    {
        if (isset($this->request->senha)) {
            $this->customer->user->password = bcrypt($this->request->senha);
            $this->customer->user->password_temporario = false;
        }
    }

    private function setBlacklist()
    {
        $this->customer->user->blacklist = ($this->request->blacklist || $this->request->blacklist == 1);

        $activity = Activity::where('flag', "USER_BLACKLIST")->first();
        if (!empty($this->request->motivo_blacklist)) {
            if (isset($activity)) {
                $context = [
                    '[::operador]' => $this->adminUser->nome_completo,
                    '[::user]' => $this->customer->user->nome_completo,
                    '[::message_motive]' => $this->request->motivo_blacklist,
                ];

                $blacklistActivitie = UserActivity::where('user_id', $this->customer->user->id)
                    ->where('atividade_id', $activity->id)
                    ->firstOrNew();

                $blacklistActivitie->atividade_id = $activity->id;
                $blacklistActivitie->user_id = $this->customer->user->id;
                $blacklistActivitie->reference_id = $this->adminUser->id;
                $blacklistActivitie->reference_provider = "ADMIN";
                $blacklistActivitie->context = $activity->getMessage($context);
                $blacklistActivitie->save();
            }
        } else if (isset($activity->id)) {
            UserActivity::where('user_id', $this->customer->user->id)
                ->where('atividade_id', $activity->id)
                ->delete();
        }
    }
}