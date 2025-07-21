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
        Schema::create('subject_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_type_id')->constrained()->onDelete('cascade')->comment('試験タイプID');
            $table->string('code', 50)->comment('分野コード');
            $table->string('name')->comment('分野名');
            $table->text('description')->nullable()->comment('分野説明');
            $table->integer('sort_order')->default(0)->comment('表示順序');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->timestamps();

            $table->unique(['exam_type_id', 'code'], 'unique_exam_subject');
            $table->index(['exam_type_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_areas');
    }
};
