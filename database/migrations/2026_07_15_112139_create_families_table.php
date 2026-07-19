<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {

            $table->id();

            // بيانات رب الأسرة
            $table->string('national_id', 20)->unique();
            $table->string('head_name');
            $table->string('phone', 20);
            $table->date('birth_date')->nullable();


            // الموقع الأصلي قبل النزوح
            $table->string('original_governorate');
            $table->string('original_city');


            // الموقع الحالي
            $table->foreignId('camp_id')
                  ->constrained('camps')
                  ->restrictOnDelete();

            $table->string('shelter_number')->nullable();


            // تركيبة الأسرة
            $table->unsignedInteger('members_count');
            $table->unsignedInteger('adults_count')->default(0);
            $table->unsignedInteger('children_count')->default(0);
            $table->unsignedInteger('pwd_count')->default(0);


            // أسرة برئاسة أنثى
            $table->boolean('is_female_headed')
                  ->default(false);

            $table->enum('fhh_reason', [
                'widow',
                'divorced',
                'husband_absent',
                'other'
            ])->nullable();


            // الإعاقة
            $table->boolean('has_pwd')
                  ->default(false);

            $table->enum('pwd_type', [
                'حركية',
                'بصرية',
                'سمعية',
                'ذهنية',
                'نفسية',
                'متعددة',
            ])->nullable();


            $table->enum('pwd_cause', [
                'منذ الولادة',
                'مرض',
                'إصابة حرب',
                'حادث',
                'أخرى',
            ])->nullable();


            // تقييم الهشاشة
            $table->unsignedTinyInteger('vulnerability_score')
                  ->default(0);


            $table->enum('vulnerability_level', [
                'high',
                'medium',
                'low',
            ])->default('low');


            // ملاحظات
            $table->text('notes')->nullable();


            // المستخدم الذي أنشأ السجل
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->restrictOnDelete();


            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};