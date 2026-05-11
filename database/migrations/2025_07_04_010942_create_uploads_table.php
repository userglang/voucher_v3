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
        Schema::create('uploads', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Primary key: UUID');

            // Foreign key to users table
            $table->uuid('user_id')->comment('Uploader user ID');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('type')->comment('Type of upload or transaction');
            $table->decimal('amount', 15, 2)->nullable()->comment('Monetary amount related to the upload');

            $table->string('branch_number')->comment('Branch number associated with the upload');
            $table->foreign('branch_number')->references('branch_number')->on('branches')->onDelete('cascade');

            $table->string('voucher_number')->comment('Generated voucher number based on branch and type');

            // Timestamps
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
