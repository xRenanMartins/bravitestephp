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
        Schema::create('showcase_groups_showcases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('showcase_group_id');
            $table->foreign('showcase_group_id')->references('id')->on('showcase_groups');
            $table->integer('showcase_id')->unsigned();
            $table->foreign('showcase_id')->references('id')->on('vitrines');
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
        Schema::dropIfExists('showcase_groups_showcases');
    }
};
