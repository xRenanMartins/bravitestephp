<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        Schema::table('vouchers', function ($table) {
            $table->string('description')->nullable();
            $table->string('observation')->nullable();
            $table->string('broadcast')->nullable();
            $table->string('recurrence_promotion')->default('0');
            $table->string('time', 336)->default('111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111');
        });
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        Schema::table('vouchers', function ($table) {
            $table->dropColumn('description');
            $table->dropColumn('observation');
            $table->dropColumn('broadcast');
            $table->dropColumn('time');
            $table->dropColumn('recurrence_promotion');
        });
    }
};
