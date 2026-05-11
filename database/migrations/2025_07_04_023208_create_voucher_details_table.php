<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('voucher_details', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Primary key: UUID');

            $table->string('ticket_number')->comment('Ticket number associated with the voucher');
            $table->foreign('ticket_number')->references('ticket_number')->on('vouchers')->onDelete('cascade');

            $table->string('voucher_number')->nullable()->comment('Voucher number associated with the voucher');

            // Business fields
            $table->string('account_code')->comment('Account code');
            $table->string('account_title')->comment('Descriptive account title');
            $table->string('type', 10)->comment('Transaction type');
            $table->decimal('amount', 15, 2)->default(0)->comment('Amount of the transaction');

            $table->uuid('series_id')->nullable()->comment('Optional series ID for sorting');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_details');
    }
};
