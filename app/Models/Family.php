<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    protected $fillable = [
        'national_id', 'head_name', 'phone', 'birth_date',
        'original_governorate', 'original_city',
        'camp_id', 'shelter_number',
        'members_count', 'adults_count',
        'children_count', 'pwd_count',
        'is_female_headed', 'fhh_reason',
        'has_pwd', 'pwd_type', 'pwd_cause',
        'vulnerability_score', 'vulnerability_level',
        'notes', 'created_by',
    ];

    protected $casts = [
        'birth_date'        => 'date',
        'is_female_headed'  => 'boolean',
        'has_pwd'           => 'boolean',
    ];

    // ============================
    // العلاقات
    // ============================
    public function camp()
    {
        return $this->belongsTo(Camp::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // public function assistances()
    // {
    //     return $this->hasMany(Assistance::class);
    // }

    public function transferRequests()
    {
        return $this->hasMany(TransferRequest::class);
    }

    // ============================
    // حساب درجة الضعف تلقائياً
    // ============================
    /*
     | المعادلة الموحدة (سلم 0-100) — مطابقة لمعاينة التسجيل في register.html
     | ولإعدادات معايير الضعف في settings.html، حتى يكون المعروض عند التسجيل
     | هو نفسه المحفوظ والمُعروض في البحث والتقارير.
     |
     | رئاسة أنثى +20 · كل فرد ذو إعاقة +15 · نسبة اعتماد عالية +15 (>1) أو +8 (>0.5)
     | حجم أسرة كبير +15 (>8) أو +8 (>5) · أطفال +10 (>3) أو +5 (>1)
     | العتبات: ≥50 عالٍ · ≥25 متوسط · أقل: منخفض
     */
    /**
     * حساب درجة ومستوى الضعف بدون حفظ — مصدر واحد للحسبة يستخدمه
     * التسجيل وتحديث الأعضاء وأمر إعادة الحساب (families:recalculate-vulnerability).
     *
     * @return array{score: int, level: string}
     */
    public function computeVulnerability(): array
    {
        $score = 0;

        $fhh = $this->is_female_headed ? 20 : 0;
        $score += $fhh;

        $pwd = ((int) $this->pwd_count) * 15;
        $score += $pwd;

        $total    = max(1, (int) $this->members_count);
        $adults   = (int) $this->adults_count;
        $children = (int) $this->children_count;

        $depRatio = $adults > 0 ? max(0, $total - $adults) / $adults : 0;
        $depScore = $depRatio > 1 ? 15 : ($depRatio > 0.5 ? 8 : 0);
        $score += $depScore;

        $sizeScore = $total > 8 ? 15 : ($total > 5 ? 8 : 0);
        $score += $sizeScore;

        $childScore = $children > 3 ? 10 : ($children > 1 ? 5 : 0);
        $score += $childScore;

        $level = match(true) {
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default      => 'low',
        };

        return [
            'score'   => $score,
            'level'   => $level,
            'factors' => [
                'fhh'        => $fhh,
                'pwd'        => $pwd,
                'dependency' => $depScore,
                'size'       => $sizeScore,
                'children'   => $childScore,
            ],
        ];
    }

    public function calculateVulnerability(): void
    {
        ['score' => $score, 'level' => $level] = $this->computeVulnerability();

        $this->vulnerability_score = $score;
        $this->vulnerability_level = $level;

        $this->save();
    }

    // ============================
    // Accessors مفيدة
    // ============================
    public function getVulnerabilityLabelAttribute(): string
    {
        return match($this->vulnerability_level) {
            'high'   => 'مرتفع',
            'medium' => 'متوسط',
            default  => 'منخفض',
        };
    }

    public function getFhhReasonLabelAttribute(): string
    {
        return match($this->fhh_reason) {
            'widow'           => 'أرملة',
            'divorced'        => 'مطلقة',
            'husband_absent'  => 'غياب رب الأسرة',
            default           => 'أخرى',
        };
    }

    
    public function members()
   {
    return $this->hasMany(
        FamilyMember::class
    );
   }
}
