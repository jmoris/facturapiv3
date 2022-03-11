<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContribuyentesUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contribuyentes_users', function (Blueprint $table) {
            $table->integer('ref_user')->unsigned();
            $table->integer('ref_contribuyente')->unsigned();
            $table->foreign('ref_user')->references('id')->on('users')
                ->onDelete('cascade');
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
        Schema::dropIfExists('contribuyentes_users');
    }
}
