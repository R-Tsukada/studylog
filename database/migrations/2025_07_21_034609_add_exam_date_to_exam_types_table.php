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
        Schema::table('exam_types', function (Blueprint $table) {
            $table->date('exam_date')->nullable()->after('description'); // 試験予定日
            $table->text('exam_notes')->nullable()->after('exam_date'); // 試験に関するメモ
            $table->string('color', 7)->default('#3B82F6')->after('exam_notes'); // テーマカラー
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_types', function (Blueprint $table) {
            $table->dropColumn(['exam_date', 'exam_notes', 'color']);
        });
    }
};
