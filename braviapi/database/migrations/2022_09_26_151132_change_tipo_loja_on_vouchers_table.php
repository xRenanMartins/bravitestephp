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
        DB::statement("ALTER TABLE `vouchers` CHANGE COLUMN `tipo_loja` `tipo_loja` VARCHAR(255) NULL DEFAULT 'TODAS';");
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `vouchers` CHANGE COLUMN `tipo_loja` `tipo_loja` ENUM('PARCEIRAS','NAOPARCEIRAS','TODAS','MARKETPLACE','LOCAL') NULL DEFAULT 'TODAS';");
    }
};
