<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include 'graded' status
        DB::statement("ALTER TABLE assignment_submissions MODIFY COLUMN status ENUM('submitted','graded','returned','needs_fix') DEFAULT 'submitted'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE assignment_submissions MODIFY COLUMN status ENUM('submitted','returned','needs_fix') DEFAULT 'submitted'");
    }
};
