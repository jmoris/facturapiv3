<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBoletasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('boletas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('folio');
            $table->smallInteger('tipo');
            $table->timestamp('fecha');
            $table->smallInteger('metodo_pago')->default(0);
            $table->bigInteger('ref_cliente')->nullable()->unsigned();
            $table->string('trackid')->nullable();
            $table->bigInteger('monto_neto')->default(0);
            $table->bigInteger('monto_iva')->default(0);
            $table->bigInteger('monto_exento')->default(0);
            $table->bigInteger('monto_otrosimp')->default(0);
            $table->bigInteger('monto_total')->default(0);
            $table->bigInteger('ref_contribuyente')->unsigned();
            $table->bigInteger('ref_sucursal')->nullable()->unsigned();
            $table->bigInteger('efectivo')->default(0);
            $table->string('codigo_autorizacion')->nullable();
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
        Schema::dropIfExists('boletas');
    }
}
