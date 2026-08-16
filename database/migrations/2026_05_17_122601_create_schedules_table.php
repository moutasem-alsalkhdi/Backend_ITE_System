<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete(); // معرف الإداري الذي رفع الجدول
            $table->string('title'); // عنوان الجدول (مثال: جدول الدوام الفعلي - الفصل الثاني)
            $table->string('image_url'); // مسار حفظ صورة الجدول على السيرفر
            $table->tinyInteger('target_year'); // السنة الدراسية المستهدفة (1، 2، 3، 4، 5)
            $table->string('academic_year'); // السنة الأكاديمية (مثال: 2025-2026)
            $table->string('department'); 
            $table->tinyInteger('semester'); // الفصل الدراسي (1: أول، 2: ثاني، 3: تكميلي/صيفي)

            // طابع زمني للرفع متوافق مع الـ Query Builder تلقائياً
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
