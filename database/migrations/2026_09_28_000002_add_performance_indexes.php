<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | فهارس الأداء (NFR-01 / NFR-06):
 | دعم البحث والفلترة والترقيم على السيرفر حتى 100,000 سجل أسرة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->index('head_name');               // البحث بالاسم
            $table->index('phone');                   // البحث بالهاتف
            $table->index('camp_id');                 // فلترة المخيم + نطاق موظف الإدخال
            $table->index('vulnerability_level');     // فلترة مستوى الضعف
            $table->index('is_female_headed');        // فلترة FHH
            $table->index('created_at');              // ترتيب latest() وفلترة التاريخ
        });

        Schema::table('family_members', function (Blueprint $table) {
            $table->index('national_id');             // البحث العالمي بأفراد الأسرة
            $table->index('family_id');               // جلب أفراد الأسرة (eager loading)
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            // ملاحظة: morphs() أنشأ فهارس auditable_type+auditable_id وcreated_at تلقائياً
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->dropIndex(['head_name']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['camp_id']);
            $table->dropIndex(['vulnerability_level']);
            $table->dropIndex(['is_female_headed']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('family_members', function (Blueprint $table) {
            $table->dropIndex(['national_id']);
            $table->dropIndex(['family_id']);
        });
    }
};
