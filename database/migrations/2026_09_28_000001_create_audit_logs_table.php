<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | سجل التدقيق (FR-SYS-06 / UC-09):
 | توثيق كل عمليات الإنشاء والتعديل والحذف على الأسر وأفرادها،
 | مع القيم القديمة والجديدة والمستخدم ووقت العملية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->enum('action', ['created', 'updated', 'deleted']);

            $table->morphs('auditable');

            $table->json('changes')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
