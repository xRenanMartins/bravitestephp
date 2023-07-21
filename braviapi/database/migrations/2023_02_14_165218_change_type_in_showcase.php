<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE vitrines MODIFY `type` ENUM('SERVICO_CONCIERGE', 'PRODUTO_CONCIERGE', 'NORMAL', 'NORMAL_WHITELIST', 'NORMAL_LINK_EXTERN') NOT NULL DEFAULT 'NORMAL';");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE vitrines MODIFY `type` ENUM('SERVICO_CONCIERGE', 'PRODUTO_CONCIERGE', 'NORMAL', 'NORMAL_WHITELIST') NOT NULL DEFAULT 'NORMAL';");
    }
};
