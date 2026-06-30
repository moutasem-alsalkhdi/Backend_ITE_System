<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->enum('request_type', ['grade_sheet', 'objection', 'lab_redo', 'life_cert']);
            $table->enum('objection_type', ['practical', 'theoretical'])->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'awaiting_payment','paid', 'ready', 'cancelled','completed'])->default('pending');
            $table->decimal('fee_amount', 8, 2)->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('window_deadline')->nullable();
            $table->timestamp('payment_deadline')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('service_requests');
    }
};