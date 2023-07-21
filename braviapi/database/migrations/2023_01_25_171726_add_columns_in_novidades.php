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
        Schema::table('novidades', function (Blueprint $table) {
            $table->string('type_content', 100)->nullable();
            $table->string('type_action', 100)->nullable();
            $table->string('status', 50)->default('ACTIVE');
            $table->string('content_image')->nullable();
            $table->string('type_store', 100)->nullable();
            $table->json('cta')->nullable();
            $table->renameColumn('horario', 'active_in');
            $table->timestamp('disable_on')->nullable();
            $table->string('titulo', 191)->nullable()->change();
            $table->string('conteudo', 1024)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        Schema::table('novidades', function (Blueprint $table) {
            $table->dropColumn('type_content');
            $table->dropColumn('type_action');
            $table->dropColumn('content_image');
            $table->dropColumn('status');
            $table->dropColumn('type_store');
            $table->dropColumn('cta');
            $table->renameColumn('active_in', 'horario');
            $table->dropColumn('disable_on');
            $table->string('titulo', 191)->nullable(false)->change();
            $table->string('conteudo', 1024)->nullable(false)->change();
        });
    }
};
