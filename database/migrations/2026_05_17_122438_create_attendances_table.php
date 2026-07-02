<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->enum('session_type', ['theoretical', 'practical']);
            $table->foreignId('scanned_by')->constrained('users')->cascadeOnDelete();
            $table->string('lecture_number'); 
            $table->timestamp('attended_at')->useCurrent();
            $table->timestamps();
            $table->unique(['student_id', 'course_id', 'lecture_number']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('attendances');
    }
};