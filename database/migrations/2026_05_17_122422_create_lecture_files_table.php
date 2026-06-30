<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('lecture_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('file_url');
            $table->enum('uploader_type', ['doctor','assistant', 'volunteer']);
            $table->string('academic_year', 15);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('lecture_files');
    }
};