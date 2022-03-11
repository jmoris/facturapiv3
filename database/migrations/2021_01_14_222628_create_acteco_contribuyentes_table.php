<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActecoContribuyentesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('acteco_contribuyentes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ref_contribuyente')->unsigned();
            $table->bigInteger('ref_acteco')->unsigned();
            $table->timestamps();
            $table->foreign('ref_contribuyente')
                ->references('id')->on('contribuyentes')
                ->onDelete('cascade');
            $table->foreign('ref_acteco')
                ->references('id')->on('actecos')
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
        Schema::dropIfExists('acteco_contribuyentes');
    }
}
