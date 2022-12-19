<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['failed', 'processing', 'executed']);
            $table->enum('type', ['deposit', 'check_balance', 'pay']);
            $table->unsignedBigInteger('wallet_id');
            $table->decimal('value', 19,2)->default(0.00);
            $table->text('token_confirmation')->nullable()->default(null);
            $table->text('token_client')->nullable()->default(null);
            $table->integer('client_executer_id')->unsigned();
            $table->integer('client_receptor_id')->unsigned()->nullable()->default(null);
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
        Schema::dropIfExists('transactions');
    }
};
