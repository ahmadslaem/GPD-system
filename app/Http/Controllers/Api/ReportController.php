<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\TransferRequest;
use Illuminate\Http\Request;
use App\Exports\GenericArrayExport;
use Maatwebsite\Excel\Facades\Excel;
class ReportController extends Controller
{




public function demographic(Request $request)
{
    $query = Family::with(['camp', 'members']);

    if ($request->filled('camp_id')) {
        $query->where('camp_id', $request->camp_id);
    }

    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }

    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }

    if ($request->filled('vulnerability_level')) {
        $query->where('vulnerability_level', $request->vulnerability_level);
    }

    $families = $query->get();

    /*
    |--------------------------------------------------------------------------
    | Summary Cards
    |--------------------------------------------------------------------------
    */
    $summary = [

        "total_families" =>
            $families->count(),

        "total_individuals" =>
            $families->sum(function ($family) {
                return $family->members->count();
            }),

        "vulnerable_families" =>
            $families->whereNotNull('vulnerability_level')->count(),

        "pwd" =>
            $families->sum('pwd_count'),

        "female_headed" =>
            $families->where('is_female_headed', true)->count(),

    ];

    /*
    |--------------------------------------------------------------------------
    | Distribution Chart
    |--------------------------------------------------------------------------
    */
    $distribution =
        $families
        ->groupBy('camp_id')
        ->map(function ($items) {
            return [
                "camp_name" =>
                    $items->first()->camp->name ?? 'Unknown',

                "families" =>
                    $items->count(),
            ];
        })
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Detailed Table
    |--------------------------------------------------------------------------
    */
    $details =
        $families
        ->groupBy('camp_id')
        ->map(function ($items) {
            return [
                "camp_name" =>
                    $items->first()->camp->name ?? null,

                "families" =>
                    $items->count(),

                "individuals" =>
                    $items->sum(function ($family) {
                        return $family->members->count();
                    }),

                "vulnerable" =>
                    $items->whereNotNull('vulnerability_level')->count(),

                "pwd" =>
                    $items->sum('pwd_count'),

                "female_headed" =>
                    $items->where('is_female_headed', true)->count(),
            ];
        })
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Totals Row
    |--------------------------------------------------------------------------
    */
    $totals = [
        "families"      => $details->sum('families'),
        "individuals"   => $details->sum('individuals'),
        "vulnerable"    => $details->sum('vulnerable'),
        "pwd"           => $details->sum('pwd'),
        "female_headed" => $details->sum('female_headed'),
    ];

    return response()->json([
        "summary"      => $summary,
        "distribution" => $distribution,
        "details"      => $details,
        "totals"       => $totals,
    ]);
}
//export excel demographic report
public function exportDemographicExcel(Request $request)
{
    $query = Family::with(['camp', 'members']);

    if ($request->filled('camp_id')) {
        $query->where('camp_id', $request->camp_id);
    }
    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }
    if ($request->filled('vulnerability_level')) {
        $query->where('vulnerability_level', $request->vulnerability_level);
    }

    $families = $query->get();

    $details = $families
        ->groupBy('camp_id')
        ->map(function ($items) {
            return [
                $items->first()->camp->name ?? 'Unknown',
                $items->count(),
                $items->sum(fn ($f) => $f->members->count()),
                $items->whereNotNull('vulnerability_level')->count(),
                $items->sum('pwd_count'),
                $items->where('is_female_headed', true)->count(),
            ];
        })
        ->values()
        ->toArray();

    $details[] = [
        'الإجمالي',
        $families->count(),
        $families->sum(fn ($f) => $f->members->count()),
        $families->whereNotNull('vulnerability_level')->count(),
        $families->sum('pwd_count'),
        $families->where('is_female_headed', true)->count(),
    ];

    $headings = ['المخيم', 'عدد الأسر', 'عدد الأفراد', 'الأسر الضعيفة', 'ذوو الإعاقة', 'ترأس نسائي'];

    return Excel::download(
        new GenericArrayExport($details, $headings, 'التقرير الديموغرافي'),
        'demographic_report_' . now()->format('Ymd_His') . '.xlsx'
    );
}
//export pdf demographic report
public function exportDemographicPdf(Request $request)
{
    $query = Family::with(['camp', 'members']);

    if ($request->filled('camp_id')) {
        $query->where('camp_id', $request->camp_id);
    }
    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }
    if ($request->filled('vulnerability_level')) {
        $query->where('vulnerability_level', $request->vulnerability_level);
    }

    $families = $query->get();

    $summary = [
        "total_families"      => $families->count(),
        "total_individuals"   => $families->sum(fn ($f) => $f->members->count()),
        "vulnerable_families" => $families->whereNotNull('vulnerability_level')->count(),
        "pwd"                 => $families->sum('pwd_count'),
        "female_headed"       => $families->where('is_female_headed', true)->count(),
    ];

    $distribution = $families
        ->groupBy('camp_id')
        ->map(fn ($items) => [
            "camp_name" => $items->first()->camp->name ?? 'Unknown',
            "families"  => $items->count(),
        ])
        ->values();

    $details = $families
        ->groupBy('camp_id')
        ->map(fn ($items) => [
            "camp_name"     => $items->first()->camp->name ?? null,
            "families"      => $items->count(),
            "individuals"   => $items->sum(fn ($f) => $f->members->count()),
            "vulnerable"    => $items->whereNotNull('vulnerability_level')->count(),
            "pwd"           => $items->sum('pwd_count'),
            "female_headed" => $items->where('is_female_headed', true)->count(),
        ])
        ->values();

    $totals = [
        "families"      => $details->sum('families'),
        "individuals"   => $details->sum('individuals'),
        "vulnerable"    => $details->sum('vulnerable'),
        "pwd"           => $details->sum('pwd'),
        "female_headed" => $details->sum('female_headed'),
    ];

    $campName = $request->filled('camp_id')
        ? optional(\App\Models\Camp::find($request->camp_id))->name
        : null;

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf.demographic', [
        'summary'      => $summary,
        'distribution' => $distribution,
        'details'      => $details,
        'totals'       => $totals,
        'campName'     => $campName,
        'dateFrom'     => $request->date_from,
        'dateTo'       => $request->date_to,
    ])->setPaper('a4', 'landscape');

    return $pdf->download('demographic_report_' . now()->format('Ymd_His') . '.pdf');
}



public function vulnerability(Request $request)
{
    $families = Family::query()->with('camp');

    // Filter camp
    if ($request->filled('camp_id')) {
        $families->where('camp_id', $request->camp_id);
    }

    // Filter dates
    if ($request->filled('date_from')) {
        $families->whereDate('created_at', '>=', $request->date_from);
    }

    if ($request->filled('date_to')) {
        $families->whereDate('created_at', '<=', $request->date_to);
    }

    // Filter vulnerability level
    if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
        $families->where('vulnerability_level', $request->vulnerability_level);
    }

    $data = $families->get();

    $total = $data->count();

    $high = $data->where('vulnerability_level', 'high')->count();

    $medium = $data->where('vulnerability_level', 'medium')->count();

    $low = $data->where('vulnerability_level', 'low')->count();

    // Average score
    $average = round($data->avg('vulnerability_score'), 2);

    // Distribution per camp
    $byCamp = $data
        ->groupBy('camp.name')
        ->map(function ($families, $camp) {

            $familiesTotal = $families->count();

            return [

                'camp' => $camp,

                'high' => $families
                    ->where('vulnerability_level', 'high')
                    ->count(),

                'medium' => $families
                    ->where('vulnerability_level', 'medium')
                    ->count(),

                'low' => $families
                    ->where('vulnerability_level', 'low')
                    ->count(),

                'total' => $familiesTotal,

                'high_percentage' => $familiesTotal
                    ? round(
                        (
                            $families
                                ->where('vulnerability_level', 'high')
                                ->count()
                            / $familiesTotal
                        ) * 100
                    )
                    : 0,

            ];
        })
        ->values();

    return response()->json([

        "summary" => [

            "high" => [
                "count" => $high,
                "percentage" => $total ? round(($high / $total) * 100) : 0,
            ],

            "medium" => [
                "count" => $medium,
                "percentage" => $total ? round(($medium / $total) * 100) : 0,
            ],

            "low" => [
                "count" => $low,
                "percentage" => $total ? round(($low / $total) * 100) : 0,
            ],

            "average_score" => $average,

        ],

        "distribution_by_camp" => $byCamp,

        "total_families" => $total,

    ]);
}
//export vulnerability report to excel
public function exportVulnerabilityExcel(Request $request)
{
    $families = Family::query()->with('camp');

    if ($request->filled('camp_id')) {
        $families->where('camp_id', $request->camp_id);
    }
    if ($request->filled('date_from')) {
        $families->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
        $families->whereDate('created_at', '<=', $request->date_to);
    }
    if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
        $families->where('vulnerability_level', $request->vulnerability_level);
    }

    $data = $families->get();

    $rows = $data
        ->groupBy('camp.name')
        ->map(function ($items, $camp) {
            $total = $items->count();
            $high = $items->where('vulnerability_level', 'high')->count();

            return [
                $camp,
                $high,
                $items->where('vulnerability_level', 'medium')->count(),
                $items->where('vulnerability_level', 'low')->count(),
                $total,
                $total ? round(($high / $total) * 100) . '%' : '0%',
            ];
        })
        ->values()
        ->toArray();

    $headings = ['المخيم', 'خطورة عالية', 'خطورة متوسطة', 'خطورة منخفضة', 'الإجمالي', 'نسبة الخطورة العالية'];

    return Excel::download(
        new GenericArrayExport($rows, $headings, 'تقرير الضعف'),
        'vulnerability_report_' . now()->format('Ymd_His') . '.xlsx'
    );
}


//export vulnerability report to pdf
public function exportVulnerabilityPdf(Request $request)
{
    $families = Family::query()->with('camp');

    if ($request->filled('camp_id')) {
        $families->where('camp_id', $request->camp_id);
    }
    if ($request->filled('date_from')) {
        $families->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
        $families->whereDate('created_at', '<=', $request->date_to);
    }
    if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
        $families->where('vulnerability_level', $request->vulnerability_level);
    }

    $data = $families->get();
    $total = $data->count();

    $high = $data->where('vulnerability_level', 'high')->count();
    $medium = $data->where('vulnerability_level', 'medium')->count();
    $low = $data->where('vulnerability_level', 'low')->count();
    $average = round($data->avg('vulnerability_score'), 2);

    $byCamp = $data
        ->groupBy('camp.name')
        ->map(function ($families, $camp) {
            $familiesTotal = $families->count();
            $highCount = $families->where('vulnerability_level', 'high')->count();

            return [
                'camp' => $camp,
                'high' => $highCount,
                'medium' => $families->where('vulnerability_level', 'medium')->count(),
                'low' => $families->where('vulnerability_level', 'low')->count(),
                'total' => $familiesTotal,
                'high_percentage' => $familiesTotal ? round(($highCount / $familiesTotal) * 100) : 0,
            ];
        })
        ->values();

    $summary = [
        "high" => ["count" => $high, "percentage" => $total ? round(($high / $total) * 100) : 0],
        "medium" => ["count" => $medium, "percentage" => $total ? round(($medium / $total) * 100) : 0],
        "low" => ["count" => $low, "percentage" => $total ? round(($low / $total) * 100) : 0],
        "average_score" => $average,
    ];

    $campName = $request->filled('camp_id')
        ? optional(\App\Models\Camp::find($request->camp_id))->name
        : null;

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf.vulnerability_en', [
        'summary' => $summary,
        'distributionByCamp' => $byCamp,
        'campName' => $campName,
        'dateFrom' => $request->date_from,
        'dateTo' => $request->date_to,
    ])->setPaper('a4', 'landscape');

    return $pdf->download('vulnerability_report_' . now()->format('Ymd_His') . '.pdf');
}

public function transfers(Request $request)
{
    $query = TransferRequest::with(['fromCamp', 'family']);

    // Filter camp (المصدر)
    if ($request->filled('camp_id')) {
        $query->where('from_camp_id', $request->camp_id);
    }

    // Filter dates
    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }

    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }

    // Filter by family's vulnerability level
    if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
        $query->whereHas('family', function ($q) use ($request) {
            $q->where('vulnerability_level', $request->vulnerability_level);
        });
    }

    $data = $query->get();

    $total = $data->count();

    $approved = $data->where('status', 'approved')->count();
    $rejected = $data->where('status', 'rejected')->count();
    $pending  = $data->where('status', 'pending')->count();

    /*
    |--------------------------------------------------------------------------
    | Summary Cards
    |--------------------------------------------------------------------------
    */
    $summary = [

        "total_requests" => $total,

        "approved" => [
            "count"      => $approved,
            "percentage" => $total ? round(($approved / $total) * 100) : 0,
        ],

        "rejected" => [
            "count"      => $rejected,
            "percentage" => $total ? round(($rejected / $total) * 100) : 0,
        ],

        "pending" => [
            "count"      => $pending,
            "percentage" => $total ? round(($pending / $total) * 100) : 0,
        ],

    ];

    /*
    |--------------------------------------------------------------------------
    | Distribution by Camp (المصدر - from_camp_id)
    |--------------------------------------------------------------------------
    */
    $byCamp = $data
        ->groupBy('from_camp_id')
        ->map(function ($items) {
            return [
                "camp_name" => $items->first()->fromCamp->name ?? 'Unknown',
                "requests"  => $items->count(),
            ];
        })
        ->sortByDesc('requests')
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Monthly Breakdown Table
    |--------------------------------------------------------------------------
    */
    $byMonth = $data
        ->groupBy(function ($item) {
            return $item->created_at->format('Y-m');
        })
        ->map(function ($items, $month) {
            return [
                "month"    => \Carbon\Carbon::parse($month . '-01')
                                ->translatedFormat('F Y'),
                "requests" => $items->count(),
                "approved" => $items->where('status', 'approved')->count(),
                "rejected" => $items->where('status', 'rejected')->count(),
                "pending"  => $items->where('status', 'pending')->count(),
            ];
        })
        ->sortKeys()
        ->values();

    return response()->json([

        "summary"               => $summary,
        "distribution_by_camp"  => $byCamp,
        "monthly_breakdown"     => $byMonth,
        "total_requests"        => $total,

    ]);
}
//export transfers report to excel
public function exportTransfersExcel(Request $request)
{
    $query = TransferRequest::with(['fromCamp', 'family']);

    if ($request->filled('camp_id')) {
        $query->where('from_camp_id', $request->camp_id);
    }
    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }
    if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
        $query->whereHas('family', function ($q) use ($request) {
            $q->where('vulnerability_level', $request->vulnerability_level);
        });
    }

    $data = $query->get();

    $rows = $data
        ->groupBy(fn ($item) => $item->created_at->format('Y-m'))
        ->map(function ($items, $month) {
            return [
                \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y'),
                $items->count(),
                $items->where('status', 'approved')->count(),
                $items->where('status', 'rejected')->count(),
                $items->where('status', 'pending')->count(),
            ];
        })
        ->sortKeys()
        ->values()
        ->toArray();

    $headings = ['الشهر', 'عدد الطلبات', 'مقبول', 'مرفوض', 'قيد الانتظار'];

    return Excel::download(
        new GenericArrayExport($rows, $headings, 'تقرير طلبات النقل'),
        'transfers_report_' . now()->format('Ymd_His') . '.xlsx'
    );
}

//export transfers report to pdf
public function exportTransfersPdf(Request $request)
{
    $query = TransferRequest::with(['fromCamp', 'family']);

    if ($request->filled('camp_id')) {
        $query->where('from_camp_id', $request->camp_id);
    }
    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }
    if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
        $query->whereHas('family', function ($q) use ($request) {
            $q->where('vulnerability_level', $request->vulnerability_level);
        });
    }

    $data = $query->get();
    $total = $data->count();

    $approved = $data->where('status', 'approved')->count();
    $rejected = $data->where('status', 'rejected')->count();
    $pending  = $data->where('status', 'pending')->count();

    $summary = [
        "total_requests" => $total,
        "approved" => ["count" => $approved, "percentage" => $total ? round(($approved / $total) * 100) : 0],
        "rejected" => ["count" => $rejected, "percentage" => $total ? round(($rejected / $total) * 100) : 0],
        "pending"  => ["count" => $pending, "percentage" => $total ? round(($pending / $total) * 100) : 0],
    ];

    $byCamp = $data
        ->groupBy('from_camp_id')
        ->map(fn ($items) => [
            "camp_name" => $items->first()->fromCamp->name ?? 'Unknown',
            "requests"  => $items->count(),
        ])
        ->sortByDesc('requests')
        ->values();

    $byMonth = $data
        ->groupBy(fn ($item) => $item->created_at->format('Y-m'))
        ->map(fn ($items, $month) => [
            "month"    => \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y'),
            "requests" => $items->count(),
            "approved" => $items->where('status', 'approved')->count(),
            "rejected" => $items->where('status', 'rejected')->count(),
            "pending"  => $items->where('status', 'pending')->count(),
        ])
        ->sortKeys()
        ->values();

    $campName = $request->filled('camp_id')
        ? optional(\App\Models\Camp::find($request->camp_id))->name
        : null;

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf.transfers_en', [
        'summary' => $summary,
        'distributionByCamp' => $byCamp,
        'monthlyBreakdown' => $byMonth,
        'campName' => $campName,
        'dateFrom' => $request->date_from,
        'dateTo' => $request->date_to,
    ])->setPaper('a4', 'landscape');

    return $pdf->download('transfers_report_' . now()->format('Ymd_His') . '.pdf');
}

public function periodic(Request $request)
{
    $query = Family::with('camp');

    // Filter camp
    if ($request->filled('camp_id')) {
        $query->where('camp_id', $request->camp_id);
    }

    // Filter dates
    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }

    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }

    // Filter vulnerability level
    if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
        $query->where('vulnerability_level', $request->vulnerability_level);
    }

    $families = $query->get();

    /*
    |--------------------------------------------------------------------------
    | Cumulative Base (الأسر المسجلة قبل بداية الفترة المفلترة)
    |--------------------------------------------------------------------------
    | حتى يعكس "إجمالي تراكمي" العدد الحقيقي التراكمي وليس فقط
    | مجموع الأشهر الظاهرة في الفلتر.
    */
    $cumulativeBase = 0;

    if ($request->filled('date_from')) {

        $baseQuery = Family::query();

        if ($request->filled('camp_id')) {
            $baseQuery->where('camp_id', $request->camp_id);
        }

        if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
            $baseQuery->where('vulnerability_level', $request->vulnerability_level);
        }

        $cumulativeBase = $baseQuery
            ->whereDate('created_at', '<', $request->date_from)
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Monthly Grouping
    |--------------------------------------------------------------------------
    */
    $byMonth = $families
        ->groupBy(function ($item) {
            return $item->created_at->format('Y-m');
        })
        ->sortKeys()
        ->map(function ($items, $month) {

            $topCamp = $items
                ->groupBy('camp_id')
                ->map(function ($campItems) {
                    return [
                        'camp_name' => $campItems->first()->camp->name ?? 'Unknown',
                        'count'     => $campItems->count(),
                    ];
                })
                ->sortByDesc('count')
                ->first();

            return [
                'month'              => \Carbon\Carbon::parse($month . '-01')
                                            ->translatedFormat('F Y'),
                'new_registrations'  => $items->count(),
                'top_camp'           => $topCamp['camp_name'] ?? null,
                'top_camp_count'     => $topCamp['count'] ?? 0,
            ];
        })
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Running Cumulative Total
    |--------------------------------------------------------------------------
    */
    $running = $cumulativeBase;

    $byMonth = $byMonth->map(function ($row) use (&$running) {
        $running += $row['new_registrations'];
        $row['cumulative_total'] = $running;
        return $row;
    });

    /*
    |--------------------------------------------------------------------------
    | Summary Cards (آخر 4 أشهر كما في التصميم)
    |--------------------------------------------------------------------------
    */
    $summaryCards = $byMonth
        ->map(function ($row) {
            return [
                'month'             => $row['month'],
                'new_registrations' => $row['new_registrations'],
            ];
        })
        ->values();

    return response()->json([

        "summary_cards" => $summaryCards,

        "monthly_chart" => $byMonth
            ->map(fn ($row) => [
                'month'             => $row['month'],
                'new_registrations' => $row['new_registrations'],
            ])
            ->values(),

        "details" => $byMonth,

    ]);
}
//export periodic report to excel
public function exportPeriodicExcel(Request $request)
{
    $query = Family::with('camp');

    if ($request->filled('camp_id')) {
        $query->where('camp_id', $request->camp_id);
    }
    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }
    if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
        $query->where('vulnerability_level', $request->vulnerability_level);
    }

    $families = $query->get();

    $cumulativeBase = 0;
    if ($request->filled('date_from')) {
        $baseQuery = Family::query();
        if ($request->filled('camp_id')) {
            $baseQuery->where('camp_id', $request->camp_id);
        }
        if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
            $baseQuery->where('vulnerability_level', $request->vulnerability_level);
        }
        $cumulativeBase = $baseQuery->whereDate('created_at', '<', $request->date_from)->count();
    }

    $byMonth = $families
        ->groupBy(fn ($item) => $item->created_at->format('Y-m'))
        ->sortKeys()
        ->map(function ($items, $month) {
            $topCamp = $items
                ->groupBy('camp_id')
                ->map(fn ($campItems) => [
                    'camp_name' => $campItems->first()->camp->name ?? 'Unknown',
                    'count' => $campItems->count(),
                ])
                ->sortByDesc('count')
                ->first();

            return [
                'month' => \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y'),
                'new_registrations' => $items->count(),
                'top_camp' => $topCamp['camp_name'] ?? '-',
            ];
        })
        ->values();

    $running = $cumulativeBase;
    $rows = $byMonth->map(function ($row) use (&$running) {
        $running += $row['new_registrations'];
        return [$row['month'], $row['new_registrations'], $row['top_camp'], $running];
    })->toArray();

    $headings = ['الشهر', 'تسجيلات جديدة', 'المخيم الأكثر استقبالاً', 'الإجمالي التراكمي'];

    return Excel::download(
        new GenericArrayExport($rows, $headings, 'التقرير الدوري'),
        'periodic_report_' . now()->format('Ymd_His') . '.xlsx'
    );
}

//export periodic report to pdf
public function exportPeriodicPdf(Request $request)
{
    $query = Family::with('camp');

    if ($request->filled('camp_id')) {
        $query->where('camp_id', $request->camp_id);
    }
    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }
    if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
        $query->where('vulnerability_level', $request->vulnerability_level);
    }

    $families = $query->get();

    $cumulativeBase = 0;
    if ($request->filled('date_from')) {
        $baseQuery = Family::query();
        if ($request->filled('camp_id')) {
            $baseQuery->where('camp_id', $request->camp_id);
        }
        if ($request->filled('vulnerability_level') && $request->vulnerability_level !== 'all') {
            $baseQuery->where('vulnerability_level', $request->vulnerability_level);
        }
        $cumulativeBase = $baseQuery->whereDate('created_at', '<', $request->date_from)->count();
    }

    $byMonth = $families
        ->groupBy(fn ($item) => $item->created_at->format('Y-m'))
        ->sortKeys()
        ->map(function ($items, $month) {
            $topCamp = $items
                ->groupBy('camp_id')
                ->map(fn ($campItems) => [
                    'camp_name' => $campItems->first()->camp->name ?? 'Unknown',
                    'count' => $campItems->count(),
                ])
                ->sortByDesc('count')
                ->first();

            return [
                'month' => \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y'),
                'new_registrations' => $items->count(),
                'top_camp' => $topCamp['camp_name'] ?? null,
            ];
        })
        ->values();

    $running = $cumulativeBase;
    $byMonth = $byMonth->map(function ($row) use (&$running) {
        $running += $row['new_registrations'];
        $row['cumulative_total'] = $running;
        return $row;
    });

    $summaryCards = $byMonth->map(fn ($row) => [
        'month' => $row['month'],
        'new_registrations' => $row['new_registrations'],
    ])->values();

    $campName = $request->filled('camp_id')
        ? optional(\App\Models\Camp::find($request->camp_id))->name
        : null;

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf.periodic_en', [
        'summaryCards' => $summaryCards,
        'monthlyChart' => $byMonth,
        'details' => $byMonth,
        'campName' => $campName,
        'dateFrom' => $request->date_from,
        'dateTo' => $request->date_to,
    ])->setPaper('a4', 'landscape');

    return $pdf->download('periodic_report_' . now()->format('Ymd_His') . '.pdf');
}

}