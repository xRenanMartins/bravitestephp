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
        Schema::create('group_settings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('group_id')->unsigned();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->string('type')->nullable();//['BIRTHDAY', 'NOT_RECEIVE_ORDER', 'REGISTER_DATE']
            $table->date('date_min')->nullable();
            $table->date('date_max')->nullable();
            $table->integer('quantity_min')->nullable();
            $table->integer('quantity_max')->nullable();
            $table->enum('comparator',['MAIOR','MENOR','IGUAL','ENTRE']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_settings');
    }
};
