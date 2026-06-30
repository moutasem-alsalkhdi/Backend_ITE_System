<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('academic_year');
            $table->tinyInteger('semester');
            $table->integer('practical_score')->nullable();
            $table->integer('theoretical_score')->nullable();
            $table->integer('total_score')->nullable();
            $table->enum('status', ['pass', 'fail']);
            $table->foreignId('modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('grades');
    }
};