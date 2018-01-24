<?php namespace KEERill\Pay\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class UpdateTablePaymentsAndSystems extends Migration
{
    public function up()
    {
        Schema::table('oc_payments', function ($table) {
            $table->text('class_name')->after('hash')->nullable();
            $table->timestamp('cancelled_at')->after('options')->nullable();
        });

        Schema::table('oc_payment_systems', function ($table) {
            $table->integer('pay_timeout')->unsigned()->after('partial_name')->default(0);
        });
    }

    public function down()
    {
        Schema::table('oc_payments', function ($table) {
            $table->dropColumn('class_name');
            $table->dropColumn('cancelled_at');
        });

        Schema::table('oc_payment_systems', function ($table) {
            $table->dropColumn('pay_timeout');
        });
    }
}