<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDireccionContribuyentesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('direccion_contribuyentes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ref_contribuyente')->unsigned();
            $table->string('tipo');
            $table->string('codigo')->nullable();
            $table->string('direccion');
            $table->bigInteger('ref_comuna')->unsigned();
            $table->timestamps();

            $table->foreign('ref_contribuyente')
                    ->references('id')->on('contribuyentes')
                    ->onDelete('cascade');
            $table->foreign('ref_comuna')
                    ->references('id')->on('comunas')
                    ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('direccion_contribuyentes');
    }
}
