<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE `vitrines` MODIFY COLUMN `visualizacao` enum('CARD','LISTA','BANNER', 'ICONE') NOT NULL");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `vitrines` MODIFY COLUMN `visualizacao` enum('CARD','LISTA') NOT NULL");
    }
};
