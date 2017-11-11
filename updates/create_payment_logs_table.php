<?php namespace KEERill\Pay\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreatePaymentLogsTable extends Migration
{
    public function up()
    {
        Schema::create('oc_payment_logs', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();

            $table->integer('user_id')->nullable();
            $table->integer('payment_id');

            $table->string('ip_address')->nullable();
            
            $table->string('message');
            $table->string('code');

            $table->text('request_data')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('oc_payment_logs');
    }
}
