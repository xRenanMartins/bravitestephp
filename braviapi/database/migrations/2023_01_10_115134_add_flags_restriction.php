<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('vouchers', function (Blueprint $table) {
            $table->enum('employee', ['YES', 'NO', 'ONLY'])->default('NO');
            $table->string('channels', 100)->default('TODOS');
            $table->string('device')->default('TODOS');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('employee');
            $table->dropColumn('channels');
            $table->dropColumn('device');
        });
    }
};
