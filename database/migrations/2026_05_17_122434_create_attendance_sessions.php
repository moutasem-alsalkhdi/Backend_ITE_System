<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->enum('session_type', ['theoretical', 'practical']);
            $table->string('lecture_number');
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete(); // الدكتور الذي بدأ الجلسة
            $table->integer('total_enrolled'); // عدد الطلاب المسجلين للمادة
            $table->integer('scanned_count')->default(0); // عدد الطلاب الذين تم مسح QR لهم
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
?>