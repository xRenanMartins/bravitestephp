<?php

use Illuminate\Database\Migrations\Migration;
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
        DB::statement("ALTER TABLE lojas CHANGE source source ENUM('ADMIN', 'AMERICANAS_DELIVERY', 'MAV', 'HUBSTER', 'GO2GO', 'LETS_DELIVERY', 'LINKSELLER') DEFAULT 'ADMIN' NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE lojas CHANGE source source ENUM('ADMIN', 'AMERICANAS_DELIVERY', 'MAV', 'HUBSTER', 'GO2GO', 'LETS_DELIVERY') DEFAULT 'ADMIN' NOT NULL");
    }
};
