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
        Schema::dropIfExists('group_clients');
        Schema::dropIfExists('group_categories');
        Schema::dropIfExists('group_deliveries');
        Schema::dropIfExists('group_stores');

        Schema::create('group_associations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('group_id')->unsigned()->nullable();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->integer('model_id');
            $table->string('model_type');
            $table->boolean('fixed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_associations');
    }
};
