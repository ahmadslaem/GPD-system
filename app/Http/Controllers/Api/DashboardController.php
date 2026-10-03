<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\Camp;
use App\Models\TransferRequest;
use App\Models\AuditLog;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /** مدة صلاحية كاش الداشبورد */
    private const CACHE_TTL = 60; // ثانية

    public function index()
    {
        $cacheKey = 'dashboard:metrics';

        $payload = cache()->remember($cacheKey, self::CACHE_TTL, function () {
            return $this->buildMetrics();
        });

        return response()->json([
            'status' => true,
            'data' => $payload,
        ]);
    }

    /**
     * حساب كل مؤشرات الداشبورد (يعمل مرة كل CACHE_TTL ثانية فقط).
     */
    private function buildMetrics(): array
    {
        /*
        | 1) الكروت العلوية (Summary Cards)
        */
        $totalFamilies    = Family::count();
        $totalIndividuals = (int) Family::sum('members_count');
        $pendingTransfers = TransferRequest::where('status', 'pending')->count();
        $highVulnerability = Family::where('vulnerability_level', 'high')->count();

        $newFamiliesThisWeek = Family::where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $lastUpdated = Family::latest('updated_at')->value('updated_at');

        $cards = [
            'pending_transfers' => [
                'count' => $pendingTransfers,
                'label' => 'بانتظار الموافقة',
            ],
            'high_vulnerability_cases' => [
                'count' => $highVulnerability,
                'label' => 'تحتاج متابعة فورية',
            ],
            'total_individuals' => [
                'count' => $totalIndividuals,
                'label' => 'بيانات محدثة',
            ],
            'total_families' => [
                'count' => $totalFamilies,
                'new_this_week' => $newFamiliesThisWeek,
            ],
        ];

        /*
        | 2) مستوى الضعف (Vulnerability Levels)
        */
        $high   = Family::where('vulnerability_level', 'high')->count();
        $medium = Family::where('vulnerability_level', 'medium')->count();
        $low    = Family::where('vulnerability_level', 'low')->count();
        $totalClassified = $high + $medium + $low;

        $vulnerabilityLevels = [
            'high' => [
                'count'      => $high,
                'percentage' => $totalClassified ? round(($high / $totalClassified) * 100) : 0,
            ],
            'medium' => [
                'count'      => $medium,
                'percentage' => $totalClassified ? round(($medium / $totalClassified) * 100) : 0,
            ],
            'low' => [
                'count'      => $low,
                'percentage' => $totalClassified ? round(($low / $totalClassified) * 100) : 0,
            ],
        ];

        /*
        | 3) التركيبة الديموغرافية (Demographics)
        */
        $childrenCount     = (int) Family::sum('children_count');
        $adultsCount       = (int) Family::sum('adults_count');
        $pwdCount          = (int) Family::sum('pwd_count');
        $femaleHeadedCount = Family::where('is_female_headed', true)->count();

        $demographics = [
            'children' => [
                'count'      => $childrenCount,
                'percentage' => $totalIndividuals ? round(($childrenCount / $totalIndividuals) * 100) : 0,
            ],
            'adults' => [
                'count'      => $adultsCount,
                'percentage' => $totalIndividuals ? round(($adultsCount / $totalIndividuals) * 100) : 0,
            ],
            'female_headed_families' => $femaleHeadedCount,
            'pwd' => [
                'count'      => $pwdCount,
                'percentage' => $totalIndividuals ? round(($pwdCount / $totalIndividuals) * 100) : 0,
            ],
        ];

        /*
        | 4) التوزيع الجغرافي حسب المخيم (Geographic Distribution)
        */
        $geoDistribution = Camp::query()
            ->withCount('families')
            ->withSum('families as total_individuals', 'members_count')
            ->orderByDesc('families_count')
            ->get()
            ->map(function ($camp) {
                return [
                    'camp_id'           => $camp->id,
                    'camp_name'         => $camp->name,
                    'families_count'    => $camp->families_count,
                    'individuals_count' => (int) ($camp->total_individuals ?? 0),
                ];
            })
            ->values();

        /*
        | 5) آخر الأسر المسجلة (Latest Registered Families)
        */
        $latestFamilies = Family::with('camp')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($family) {
                return [
                    'id'                  => $family->id,
                    'family_number'       => 'F-' . str_pad($family->id, 5, '0', STR_PAD_LEFT),
                    'head_name'           => $family->head_name,
                    'camp_name'           => $family->camp->name ?? null,
                    'members_count'       => $family->members_count,
                    'vulnerability_level' => $family->vulnerability_level,
                    'vulnerability_label' => $family->vulnerability_label,
                    'registered_at'       => optional($family->created_at)->format('Y-m-d'),
                ];
            });

        /*
        | 6) أحدث طلبات النقل المعلقة (Latest Pending Transfer Requests)
        */
        $latestPendingTransfers = TransferRequest::with(['family', 'fromCamp', 'toCamp'])
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($transfer) {
                return [
                    'id'         => $transfer->id,
                    'head_name'  => $transfer->family->head_name ?? null,
                    'from_camp'  => $transfer->fromCamp->name ?? null,
                    'to_camp'    => $transfer->toCamp->name ?? null,
                    'status'     => $transfer->status,
                    'created_at'  => $transfer->created_at?->toDateString(),
                ];
            });

        /*
        | 7) عدادات سجل التدقيق (تدقيق النشاط الأخير)
        */
        $auditToday = AuditLog::whereDate('created_at', Carbon::today())->count();
        $auditWeek  = AuditLog::where('created_at', '>=', Carbon::now()->subDays(7))->count();

        return [
            'cards'                    => $cards,
            'vulnerability_levels'     => $vulnerabilityLevels,
            'demographics'             => $demographics,
            'geographic_distribution'  => $geoDistribution,
            'latest_families'          => $latestFamilies,
            'latest_pending_transfers' => $latestPendingTransfers,
            'last_updated'             => optional($lastUpdated)->format('h:i A'),
            'audit_activity'           => ['today' => $auditToday, 'week' => $auditWeek],
            'generated_at'             => Carbon::now()->toDateTimeString(),
        ];
    }

    /**
     * إبطال كاش الداشبورد عند أي كتابة تؤثر على المؤشرات.
     * تُستدعى من Observers والكونترولرز — رخيصة: مجرد حذف مفتاح.
     */
    public static function flushCache(): void
    {
        cache()->forget('dashboard:metrics');
    }
}
