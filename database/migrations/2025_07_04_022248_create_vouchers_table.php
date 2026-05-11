<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Primary key: UUID');

            $table->string('voucher_number')->comment('Unique voucher number (system generated)');
            $table->string('ticket_number')->index()->comment('Optional ticket number or reference');
            $table->string('payee')->nullable()->comment('Recipient of the payment');
            $table->string('ck_number')->nullable()->comment('Check number if applicable');
            $table->text('description')->comment('Description or purpose of the voucher');

            // Redundant branch details for history purposes
            $table->string('branch_name')->comment('Name of the branch at the time of voucher creation');
            $table->string('branch_address')->nullable()->comment('Branch address at the time of voucher creation');

            // Prepared by
            $table->string('prepared_by')->nullable()->comment('Name of the preparer');
            $table->string('prepared_designation')->nullable()->comment('Designation of the preparer');

            // Checked by
            $table->string('checked_by')->nullable()->comment('Name of the checker');
            $table->string('checked_designation')->nullable()->comment('Designation of the checker');

            // Approved by
            $table->string('approved_by')->nullable()->comment('Name of the approver');
            $table->string('approved_designation')->nullable()->comment('Designation of the approver');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
