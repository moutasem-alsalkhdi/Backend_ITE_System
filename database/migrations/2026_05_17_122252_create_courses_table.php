<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->boolean('has_lab')->default(false);
            $table->tinyInteger('year_of_study');
            $table->integer('theory_max_mark');    // العلامة العظمى للنظري
            $table->integer('practical_max_mark'); // العلامة العظمى للعملي
            $table->tinyInteger('semester');
            $table->string('department', 50); // الاختصاص 
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
