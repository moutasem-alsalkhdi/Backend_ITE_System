<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_assignments', function (Blueprint $table) {
            $table->id();
            
            // ربط المادة
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            
            // ربモ الدكتور أو المعيد (يفترض أن جدول المستخدمين هو users أو اسم جدول الدكاترة لديك كـ doctors)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); 
            
            // تحديد القسم (نظري أو عملي)
            $table->enum('section_type', ['theoretical', 'practical']); 
            
            // السنة الدراسية (مثال: "2026-2027")
            $table->string('academic_year'); 
            
            // الفصل الدراسي (1: الأول، 2: الثاني، 3: الصيفي)
            $table->tinyInteger('semester'); 

            $table->timestamps();

            // منع تكرار إسناد نفس المادة لنفس الشخص في نفس القسم ونفس الفصل والسنة الدراسية
            $table->unique(['course_id', 'user_id', 'section_type', 'academic_year', 'semester'], 'course_staff_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_assignments');
    }
};