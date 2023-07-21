<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-08-28
 * Time: 10:41
 */

namespace App\Rules\Password;

use Packk\Core\Models\PasswordReset;

class HistoricPassword
{
    public static function execute($email, $password)
    {
        $historics = PasswordReset::where("email", $email)->where("type", "LOG")->orderBy("created_at", "desc")->get();
        $count = 0;
        foreach ($historics as $historic) {
            if (password_verify($password, $historic->token)) {
                return false;
            }
            $count += 1;
            if ($count > 7) {
                return true;
            }
        }
        return true;
    }
}