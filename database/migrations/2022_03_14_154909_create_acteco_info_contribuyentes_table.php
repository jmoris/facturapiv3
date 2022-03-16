<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActecoInfoContribuyentesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('acteco_info_contribuyentes', function (Blueprint $table) {
            $table->bigInteger('ref_icontribuyente')->unsigned();
            $table->bigInteger('ref_acteco')->unsigned();
            $table->foreign('ref_icontribuyente')->references('id')->on('info_contribuyentes')
                ->onDelete('cascade');
            $table->foreign('ref_acteco')->references('id')->on('actecos')
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
        Schema::dropIfExists('acteco_info_contribuyentes');
    }
}
