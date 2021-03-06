<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('folio');
            $table->smallInteger('tipo');
            $table->timestamp('fecha');
            $table->smallInteger('metodo_pago')->default(0);
            $table->bigInteger('ref_cliente')->nullable()->unsigned();
            $table->string('trackid')->nullable();
            /*
                ESTADOS DEL DOCUMENTO:
                    0 => EN PROCESO
                    1 => ACEPTADO
                    2 => ACEPTADO CON REPAROS
                    3 => RECHAZADO
                    4 => ACEPTADO POR ND
            */
            $table->smallInteger('estado')->default(0);
            $table->bigInteger('monto_neto')->default(0);
            $table->bigInteger('monto_iva')->default(0);
            $table->bigInteger('monto_exento')->default(0);
            $table->bigInteger('monto_otrosimp')->default(0);
            $table->bigInteger('monto_total')->default(0);
            $table->bigInteger('ref_contribuyente')->unsigned();
            $table->bigInteger('ref_sucursal')->nullable()->unsigned();
            $table->bigInteger('efectivo')->default(0);
            $table->string('codigo_autorizacion')->nullable();
            $table->foreign('ref_contribuyente')->references('id')->on('contribuyentes')
                ->onDelete('cascade');
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
        Schema::dropIfExists('documentos');
    }
}
