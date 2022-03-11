<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConfigContribuyentesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('config_contribuyentes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ref_contribuyente')->unsigned();
            $table->string('pass_certificado')->nullable();
            $table->boolean('papel_carta')->default(true);
            $table->string('email_dte_prod')->nullable();
            $table->string('pass_dte_prod')->nullable();
            $table->string('email_dte_dev')->nullable();
            $table->string('pass_dte_dev')->nullable();
            $table->timestamps();
            $table->foreign('ref_contribuyente')
                    ->references('id')->on('contribuyentes')
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
        Schema::dropIfExists('config_contribuyentes');
    }
}
