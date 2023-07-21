<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        Schema::table('infos_pedidos', function (Blueprint $table) {
            $table->string('card_brand', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        Schema::table('infos_pedidos', function (Blueprint $table) {
            $table->dropColumn('card_brand');
        });
    }
};
