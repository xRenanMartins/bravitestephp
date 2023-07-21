<?php

namespace App\Rules\Password;

use Packk\Core\Exceptions\RuleException;
use Packk\Core\Models\User;
use Packk\Core\Models\PasswordReset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StoreResetPassword
{
    public function execute($payload, $hashlink)
    {
        try {
            $user = User::where('hashlink', $hashlink)->firstOrFail();
            if (!HistoricPassword::execute($user->email, $payload["senha"])) {
                throw new RuleException("Seu password ja foi usado", "Por favor, insira outro password");
            }
            DB::transaction(function () use ($hashlink, $user, $payload) {

                $user->password = bcrypt($payload["senha"]);
                $user->hashlink = null;
                $user->password_temporario = 0;
                $user->password_updated_at = Carbon::now()->addDays(90);
                $user->hashlink_deadline = null;
                $user->save();

                $reset = new PasswordReset();
                $reset->email = $user->email;
                $reset->token = bcrypt($payload["senha"]);
                $reset->type = "LOG";
                $reset->save();
            });
            return ['log' => 'works ' . $hashlink];
        } catch (\Exception $e) {
            throw  $e;
        }
    }
}