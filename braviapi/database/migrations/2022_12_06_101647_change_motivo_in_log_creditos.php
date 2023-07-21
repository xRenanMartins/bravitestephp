<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE log_creditos MODIFY motivo varchar(100) NULL");
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE log_creditos MODIFY motivo ENUM('LEGADO', 'COMPRA', 'COMPRA_CANCELADA', 'CASHBACK', 'PAINEL_ADMIN', 'CADASTRO', 'INDICACAO', 'INDICACAO_LOJA', 'MARKETING', 'TROCO', 'GORJETA') NULL");
    }
};
