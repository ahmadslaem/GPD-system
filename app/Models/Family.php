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

    // public function transferRequests()
    // {
    //     return $this->hasMany(TransferRequest::class);
    // }

    // ============================
    // حساب درجة الهشاشة تلقائياً
    // ============================
    public function calculateVulnerability(): void
    {
        $score = 0;

        if ($this->is_female_headed) $score += 3;
        if ($this->has_pwd)          $score += 4;
        if ($this->pwd_count > 1)    $score += 2;
        if ($this->children_count > 3) $score += 2;
        if ($this->members_count > 7)  $score += 1;

        $this->vulnerability_score = $score;
        $this->vulnerability_level = match(true) {
            $score >= 7 => 'high',
            $score >= 4 => 'medium',
            default     => 'low',
        };

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

