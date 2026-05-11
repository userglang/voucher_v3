<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->string('ticket_number')->after('user_id')->comment('Ticket number associated with the voucher');

            // Add foreign key constraint
            $table->foreign('ticket_number')
                ->references('ticket_number')
                ->on('vouchers')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropForeign(['ticket_number']);
            $table->dropColumn('ticket_number');
        });
    }
};
