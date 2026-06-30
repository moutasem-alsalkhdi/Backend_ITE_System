<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->enum('type', ['credit', 'debit']); // credit = شحن رصيد، debit = خصم/دفع رسوم
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2); // الرصيد قبل العملية (مهم جداً للتدقيق المحاسبي)
            $table->decimal('balance_after', 12, 2);  // الرصيد بعد العملية
            $table->string('description')->nullable();  // بيان العملية (مثال: شحن رصيد بالديوان، دفع رسوم تظلم مادة)
            $table->foreignId('processed_by')->nullable()->constrained('users'); // معرف الآدمن/الموظف الذي قام بالشحن

            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
