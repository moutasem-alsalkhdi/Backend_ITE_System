<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('uploaded_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->enum('file_type', ['exam_numbers', 'group_lists', 'grades']);
            $table->text('file_url');
            $table->string('academic_year', 50);
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('uploaded_files');
    }
};