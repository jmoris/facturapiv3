<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCAFSTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('c_a_f_s', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('folio');
            $table->smallInteger('tipo');
            $table->longText('xml');
            $table->bigInteger('ref_contribuyente')->unsigned();
            $table->timestamps();

            $table->foreign('ref_contribuyente')->references('id')->on('contribuyentes')
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
        Schema::dropIfExists('c_a_f_s');
    }
}
