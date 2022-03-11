<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContribuyentesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contribuyentes', function (Blueprint $table) {
            $table->id();
            $table->string('rut');
            $table->string('razon_social');
            $table->smallInteger('ambiente');
            $table->smallInteger('nro_resolucion_prod');
            $table->date('fch_resolucion_prod');
            $table->smallInteger('nro_resolucion_dev');
            $table->date('fch_resolucion_dev');
            $table->string('telefono')->nullable();
            $table->string('mail')->nullable();
            $table->string('web')->nullable();
            $table->index('rut');
            $table->bigInteger('contador_boletas')->default(0);
            $table->bigInteger('contador_peticiones')->default(0);
            $table->bigInteger('contador_documentos')->default(0);
            $table->boolean('boleta_produccion')->default(false);
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
        Schema::dropIfExists('contribuyentes');
    }
}
