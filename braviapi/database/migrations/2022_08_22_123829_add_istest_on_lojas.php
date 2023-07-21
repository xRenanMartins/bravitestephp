<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        Schema::table('lojas', function ($table) {
            $table->boolean('is_test')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     * @return void)
     */
    public function down()
    {
        Schema::table('lojas', function ($table) {
            $table->dropColumn('is_test');
        });
    }
}

;
