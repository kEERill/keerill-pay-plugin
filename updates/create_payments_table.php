<?php namespace KEERill\Pay\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreatePaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('oc_payments', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->text('hash')->nullable();

            $table->integer('pay_method')->nullable();
            $table->string('description')->nullable();

            $table->float('pay', 12, 2)->default(0)->unsigned();
            $table->timestamp('paid_at')->nullable();

            $table->integer('status')->default(1);
            $table->text('options')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('oc_payments');
    }
}
