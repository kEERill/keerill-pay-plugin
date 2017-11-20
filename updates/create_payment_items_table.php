<?php namespace KEERill\Pay\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreatePaymentItemsTable extends Migration
{
    public function up()
    {
        Schema::create('oc_payment_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();

            $table->integer('payment_id')->nullable();

            $table->string('class_name');
            $table->text('options')->nullable();

            $table->string('code')->nullable();

            $table->string('description');
            $table->float('price', 8, 2)->unsigned();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('oc_payment_items');
    }
}
