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
        DB::statement("ALTER TABLE `vouchers` CHANGE COLUMN `tipo_desconto` `tipo_desconto` ENUM('A', 'P', 'CB', 'FRETE_FIXO', 'FRETE_PERCENTUAL', 'VISUAL', 'VALOR_MAXIMO', 'FRETE_GRATIS') NOT NULL DEFAULT 'A';");
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `vouchers` CHANGE COLUMN `tipo_desconto` `tipo_desconto` ENUM('A', 'P', 'CB', 'FRETE_FIXO', 'FRETE_PERCENTUAL', 'VISUAL', 'VALOR_MAXIMO') NOT NULL DEFAULT 'A';");
    }
};
