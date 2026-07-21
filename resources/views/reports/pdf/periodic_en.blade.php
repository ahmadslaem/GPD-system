<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Periodic Report</title>
    <style>
        @page { margin: 20px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; direction: rtl; color: #333; text-align: right; }

        h1 { font-size: 16px; text-align: center; margin-bottom: 4px; }
        .meta { text-align: center; color: #777; font-size: 9px; margin-bottom: 18px; }

        .cards { width: 100%; margin-bottom: 20px; }
        .cards table { width: 100%; border-collapse: separate; border-spacing: 6px; }
        .card { border-radius: 6px; padding: 10px; text-align: center; }
        .card .value { font-size: 16px; font-weight: bold; display: block; margin-top: 4px; }
        .card .label { font-size: 9px; color: #555; }
        .card-blue  { background-color: #E8F1FC; }
        .card-blue .value { color: #1a4d8f; }

        .chart-title { font-size: 12px; font-weight: bold; margin: 14px 0 8px; }
        .bar-row { width: 100%; margin-bottom: 6px; }
        .bar-table { width: 100%; }
        .bar-label { width: 90px; text-align: right; padding-left: 6px; font-size: 10px; }
        .bar-value { width: 45px; text-align: left; padding-right: 6px; font-size: 10px; }
        .bar-track { background-color: #eef2f6; border-radius: 3px; height: 14px; }
        .bar-fill { background: linear-gradient(to right, #1a7f4e, #4bc98a); height: 14px; border-radius: 3px; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.data th, table.data td { border: 1px solid #ddd; padding: 6px; text-align: center; }
        table.data th { background-color: #DCE6F1; }
        table.data tr:nth-child(even) { background-color: #f8f9fa; }

        .footer { position: fixed; bottom: -10px; width: 100%; text-align: center; font-size: 8px; color: #999; }
    </style>
</head>
<body>
    <h1>Periodic Registration Report</h1>
    <div class="meta">
        {{ $campName ?? 'All Camps' }}
        @if(!empty($dateFrom) || !empty($dateTo))
            &nbsp;–&nbsp; {{ $dateFrom ?? '...' }} to {{ $dateTo ?? '...' }}
        @endif
        &nbsp;|&nbsp; Generated: {{ now()->format('Y-m-d H:i') }}
    </div>

    {{-- ===================== Summary Cards (per month) ===================== --}}
    <div class="cards">
        <table>
            <tr>
                @foreach($summaryCards->take(4) as $card)
                <td class="card card-blue" style="width: {{ 100 / max($summaryCards->take(4)->count(), 1) }}%;">
                    <span class="label">{{ $card['month'] }}</span>
                    <span class="value">{{ number_format($card['new_registrations']) }}</span>
                </td>
                @endforeach
            </tr>
        </table>
    </div>

    {{-- ===================== Bar Chart: Monthly Registrations ===================== --}}
    <div class="chart-title">New Registrations per Month</div>
    @php
        $maxReg = $monthlyChart->max('new_registrations') ?: 1;
    @endphp
    @foreach($monthlyChart as $row)
        <div class="bar-row">
            <table class="bar-table">
                <tr>
                    <td class="bar-label">{{ $row['month'] }}</td>
                    <td>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ round(($row['new_registrations'] / $maxReg) * 100) }}%;"></div>
                        </div>
                    </td>
                    <td class="bar-value">{{ number_format($row['new_registrations']) }}</td>
                </tr>
            </table>
        </div>
    @endforeach

    {{-- ===================== Detailed Table ===================== --}}
    <div class="chart-title">Detailed Data</div>
    <table class="data">
        <thead>
            <tr>
                <th>Month</th>
                <th>New Registrations</th>
                <th>Top Receiving Camp</th>
                <th>Cumulative Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($details as $row)
            <tr>
                <td>{{ $row['month'] }}</td>
                <td>{{ number_format($row['new_registrations']) }}</td>
                <td>{{ $row['top_camp'] ?? '-' }}</td>
                <td>{{ number_format($row['cumulative_total']) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Displaced Families Management System</div>
</body>
</html>
