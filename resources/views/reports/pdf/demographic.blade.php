<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Demographic Report</title>
    <style>
        @page { margin: 20px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; direction: rtl; color: #333; text-align: right; }

        h1 { font-size: 16px; text-align: center; margin-bottom: 4px; }
        .meta { text-align: center; color: #777; font-size: 9px; margin-bottom: 18px; }

        /* ---------- Summary Cards ---------- */
        .cards { width: 100%; margin-bottom: 20px; }
        .cards table { width: 100%; border-collapse: separate; border-spacing: 6px; }
        .card { border-radius: 6px; padding: 10px; text-align: center; width: 25%; }
        .card .value { font-size: 18px; font-weight: bold; display: block; margin-top: 4px; }
        .card .label { font-size: 9px; color: #555; }
        .card-blue   { background-color: #E8F1FC; }
        .card-green  { background-color: #E7F7EE; }
        .card-orange { background-color: #FDF3E3; }
        .card-red    { background-color: #FBEAEA; }
        .card-blue .value   { color: #1a4d8f; }
        .card-green .value  { color: #1a7f4e; }
        .card-orange .value { color: #a15c00; }
        .card-red .value    { color: #b02a37; }

        /* ---------- Bar Chart (CSS-based) ---------- */
        .chart-title { font-size: 12px; font-weight: bold; margin: 14px 0 8px; }
        .bar-row { width: 100%; margin-bottom: 6px; }
        .bar-table { width: 100%; }
        .bar-label { width: 90px; text-align: right; padding-left: 6px; font-size: 10px; }
        .bar-value { width: 55px; text-align: left; padding-right: 6px; font-size: 10px; }
        .bar-track { background-color: #eef2f6; border-radius: 3px; height: 14px; }
        .bar-fill { background: linear-gradient(to right, #2c6fbb, #4a90d9); height: 14px; border-radius: 3px; }

        /* ---------- Table ---------- */
        table.data { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.data th, table.data td { border: 1px solid #ddd; padding: 6px; text-align: center; }
        table.data th { background-color: #DCE6F1; }
        table.data tr:nth-child(even) { background-color: #f8f9fa; }
        table.data tr.total-row { font-weight: bold; background-color: #eef2f6; }

        .footer { position: fixed; bottom: -10px; width: 100%; text-align: center; font-size: 8px; color: #999; }
    </style>
</head>
<body>
    <h1>Comprehensive Demographic Report</h1>
    <div class="meta">
        {{ $campName ?? 'All Camps' }}
        @if(!empty($dateFrom) || !empty($dateTo))
            &nbsp;–&nbsp; {{ $dateFrom ?? '...' }} to {{ $dateTo ?? '...' }}
        @endif
        &nbsp;|&nbsp; Generated: {{ now()->format('Y-m-d H:i') }}
    </div>

    {{-- ===================== Summary Cards ===================== --}}
    <div class="cards">
        <table>
            <tr>
                <td class="card card-blue">
                    <span class="label">Total Families</span>
                    <span class="value">{{ number_format($summary['total_families']) }}</span>
                </td>
                <td class="card card-green">
                    <span class="label">Total Individuals</span>
                    <span class="value">{{ number_format($summary['total_individuals']) }}</span>
                </td>
                <td class="card card-orange">
                    <span class="label">Persons with Disability</span>
                    <span class="value">{{ number_format($summary['pwd']) }}</span>
                </td>
                <td class="card card-red">
                    <span class="label">Female-Headed Households</span>
                    <span class="value">{{ number_format($summary['female_headed']) }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- ===================== Bar Chart ===================== --}}
    <div class="chart-title">Families Distribution by Camp</div>
    @php
        $maxFamilies = $distribution->max('families') ?: 1;
    @endphp
    @foreach($distribution as $row)
        <div class="bar-row">
            <table class="bar-table">
                <tr>
                    <td class="bar-label">{{ $row['camp_name'] }}</td>
                    <td>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ round(($row['families'] / $maxFamilies) * 100) }}%;"></div>
                        </div>
                    </td>
                    <td class="bar-value">{{ number_format($row['families']) }}</td>
                </tr>
            </table>
        </div>
    @endforeach

    {{-- ===================== Detailed Table ===================== --}}
    <div class="chart-title">Detailed Data</div>
    <table class="data">
        <thead>
            <tr>
                <th>Camp</th>
                <th>Families</th>
                <th>Individuals</th>
                <th>High Vulnerability</th>
                <th>PWD</th>
                <th>FHH</th>
            </tr>
        </thead>
        <tbody>
            @foreach($details as $row)
            <tr>
                <td>{{ $row['camp_name'] }}</td>
                <td>{{ number_format($row['families']) }}</td>
                <td>{{ number_format($row['individuals']) }}</td>
                <td>{{ number_format($row['vulnerable']) }}</td>
                <td>{{ number_format($row['pwd']) }}</td>
                <td>{{ number_format($row['female_headed']) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td>Total</td>
                <td>{{ number_format($totals['families']) }}</td>
                <td>{{ number_format($totals['individuals']) }}</td>
                <td>{{ number_format($totals['vulnerable']) }}</td>
                <td>{{ number_format($totals['pwd']) }}</td>
                <td>{{ number_format($totals['female_headed']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">Displaced Families Management System</div>
</body>
</html>
