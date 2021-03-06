<?php namespace KEERill\Pay\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreatePaymentSystemsTable extends Migration
{
    public function up()
    {
        Schema::create('oc_payment_systems', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');

            $table->string('name');
            $table->string('code');
            $table->string('description')->nullable();

            $table->text('class_name');
            $table->string('gateway_name')->nullable();

            $table->boolean('is_enable')->default(false);
            $table->string('partial_name')->nullable();

            $table->text('options')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('oc_payment_systems');
    }
}
