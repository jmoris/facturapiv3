<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInfoContribuyentesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('info_contribuyentes', function (Blueprint $table) {
            $table->string('rut')->unique()->primary();
            $table->string('razon_social');
            $table->integer('nro_resolucion');
            $table->date('fch_resolucion');
            $table->string('correo_dte');
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
        Schema::dropIfExists('info_contribuyentes');
    }
}
