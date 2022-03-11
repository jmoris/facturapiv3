<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRCOFSTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('r_c_o_f_s', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->bigInteger('monto_39')->default(0);
            $table->bigInteger('monto_41')->default(0);
            $table->bigInteger('ref_contribuyente')->unsigned();
            $table->string('trackid');
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
        Schema::dropIfExists('r_c_o_f_s');
    }
}
