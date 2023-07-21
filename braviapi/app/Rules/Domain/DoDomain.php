<?php

namespace App\Rules\Domain;

use Packk\Core\Models\User;
use Packk\Core\Models\Domain;
use Packk\Core\Models\Feature;
use Packk\Core\Models\DomainFeature;
use Packk\Core\Models\Activity;
use Packk\Core\Models\Reason;
use Packk\Core\Models\RoleUser;
use Packk\Core\Models\Role;

class DoDomain
{
    private $domain;
    private $domainIdTarget;

    public function execute($email, $password, $domainId, $domainIdTarget = 6)
    {
        $domain = Domain::where("id", $domainId)->first();

        $this->domain = $domain;
        $this->domainIdTarget = $domainIdTarget;

        if ($domain) {
            $this->addUser($email, $password);
            $this->addSettings();
            $this->addFeatures();
            $this->addActivities();
            $this->addReasons();
        }
    }

    private function addUser($email, $password)
    {
        // Adiciona usuário como owner
        $user = User::create([
            'nome' => $this->domain->name,
            'sobrenome' => $this->domain->name,
            'telefone' => '000',
            'email' => $email,
            'password' => bcrypt($password),
            'tipo' => 'A', // admin
            'domain_id' => $this->domain->id
        ]);

        // Coleta o role de "dono"
        $roleOwner = Role::where('label', 'owner')->first();

        // Faz o vinculo do role com o usuário
        $newRoleUser = new RoleUser;
        $newRoleUser->user_id = $user->id;
        $newRoleUser->role_id = $roleOwner->id;
        $newRoleUser->save();
    }

    private function addSettings()
    {
        $settingsModel = \DB::table("setting_domain")->where("domain_id", $this->domainIdTarget)->get();

        foreach ($settingsModel as $setting) {
            $this->domain->dbSettings()->syncWithoutDetaching([
                $setting->setting_id => [
                    "is_active" => $setting->is_active,
                    "value" => $setting->value,
                    "created_at" => \Carbon\Carbon::now(),
                    "updated_at" => \Carbon\Carbon::now()
                ]
            ]);
        }
    }

    private function addFeatures()
    {
        // Ao iniciar um domínio, adiciona essas duas features como enabled
        $feature = Feature::withoutGlobalScope("App\Scopes\DomainScope")->where('name', 'newSideMenu')->get()->first();

        if ($feature) {
            $domainFeature = new DomainFeature();
            $domainFeature->domain_id = $this->domain->id;
            $domainFeature->feature_id = $feature->id;
            $domainFeature->enabled = 1;
            $domainFeature->save();
        }

        $featureDash = Feature::withoutGlobalScope("App\Scopes\DomainScope")->where('name', 'dashboard')->get()->first();

        if ($featureDash) {
            $domainFeatureDash = new DomainFeature();
            $domainFeatureDash->domain_id = $this->domain->id;
            $domainFeatureDash->feature_id = $featureDash->id;
            $domainFeatureDash->enabled = 1;
            $domainFeatureDash->save();
        }
    }

    private function addActivities()
    {
        $atividades = Activity::withoutGlobalScope("App\Scopes\DomainScope")->where('domain_id', $this->domainIdTarget)->get();

        foreach ($atividades as $atividade) {
            if (!Activity::where('flag', $atividade->flag)->where('domain_id', $this->domain->id)->exists()) {
                $nova_atividade = new Activity;
                $nova_atividade->nome = $atividade->nome;
                $nova_atividade->flag = $atividade->flag;
                $nova_atividade->type = $atividade->type;
                $nova_atividade->scope = $atividade->scope;
                $nova_atividade->repeat = $atividade->repeat;
                $nova_atividade->domain_id = $this->domain->id;
                $nova_atividade->save();
            }
        }
    }

    private function addReasons()
    {
        $reasons = Reason::withoutGlobalScope("App\Scopes\DomainScope")->where('domain_id', $this->domainIdTarget)->get();

        // Se não tiver nada cadastrado no domínio, então sincroniza
        if (!Reason::withoutGlobalScope("App\Scopes\DomainScope")
            ->where('domain_id', $this->domain->id)
            ->exists()) {
            foreach ($reasons as $reason) {
                $newReason = new Reason;
                $newReason->descricao = $reason->descricao;
                $newReason->ativo = $reason->ativo;
                $newReason->tipo = $reason->tipo;
                $newReason->feedback_message = $reason->feedback_message;
                $newReason->exige_escrita = $reason->exige_escrita;
                $newReason->domain_id = $this->domain->id;
                $newReason->ban_time = $reason->ban_time;
                $newReason->save();
            }
        }
    }
}