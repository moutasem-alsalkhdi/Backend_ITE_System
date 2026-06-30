<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_deadlines', function (Blueprint $table) {
            $table->id();
            // ربط المهلة بمادة معينة من جدول المواد
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();

            // تحديد نوع الطلب (هل المهلة مخصصة للاعتراض أم لإعادة العملي؟)
            $table->enum('request_type', ['objection', 'lab_redo']);
            //  حقل نوع الاعتراض (عملي أو نظري)
            $table->enum('objection_type', ['practical', 'theoretical'])->nullable();
            
            //  حقل تاريخ البداية التلقائي
            $table->timestamp('beginning_date')->nullable();

            // تاريخ ووقت انتهاء المهلة (الذي يفحصه النظام)
            $table->timestamp('end_date')->nullable();

            // وقت إنشاء هذه المهلة
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_deadlines');
    }
};
