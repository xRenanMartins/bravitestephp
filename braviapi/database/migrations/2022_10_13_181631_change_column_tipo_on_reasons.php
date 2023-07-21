<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE `reasons` CHANGE COLUMN `tipo` `tipo` ENUM('CLIENTE', 'ADMIN', 'ENTREGADOR', 'LOJISTA', 'NONE', 'LOJISTA_MOTIVO', 'ADMIN_CLIENTE', 'REJEITAR_ENTREGADOR', 'ADMIN_STORES', 'REMAKE_ORDER') NULL DEFAULT 'NONE';");
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `reasons` CHANGE COLUMN `tipo` `tipo` ENUM('CLIENTE', 'ADMIN', 'ENTREGADOR', 'LOJISTA', 'NONE', 'LOJISTA_MOTIVO', 'ADMIN_CLIENTE', 'REJEITAR_ENTREGADOR', 'ADMIN_STORES') NULL DEFAULT 'NONE';");
    }
};
