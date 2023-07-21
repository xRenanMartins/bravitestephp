<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ResetUsersCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private $rolesId, private $fromMenu = false)
    {
        //
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        if (count($this->rolesId) > 0) {
            $users = DB::table('role_user')->whereIn('role_id', $this->rolesId)->get();

            foreach ($users as $user) {
                if ($this->fromMenu) {
                    Cache::forget("user.{$user->user_id}.menu");
                } else {
                    Cache::forget("user.{$user->user_id}.roles");
                    Cache::forget("user.{$user->user_id}.permission.key");
                    Cache::forget("user.{$user->user_id}.permission.items");
                }
            }
        }
    }
}
