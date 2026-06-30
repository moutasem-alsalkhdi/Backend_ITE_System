<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            // ربط فريد مع جدول المستخدمين (كل طالب له محفظة واحدة فقط)
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            // حقل الرصيد بدقة decimal مناسبة للمبالغ المالية ومحمي من القيم السالبة كقيمة افتراضية
            $table->decimal('balance', 12, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
