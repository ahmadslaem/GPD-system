<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Transfer Requests Report</title>
    <style>
        @page { margin: 20px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; direction: ltr; color: #333; }

        h1 { font-size: 16px; text-align: center; margin-bottom: 4px; }
        .meta { text-align: center; color: #777; font-size: 9px; margin-bottom: 18px; }

        .cards { width: 100%; margin-bottom: 20px; }
        .cards table { width: 100%; border-collapse: separate; border-spacing: 6px; }
        .card { border-radius: 6px; padding: 10px; text-align: center; width: 25%; }
        .card .value { font-size: 18px; font-weight: bold; display: block; margin-top: 4px; }
        .card .label { font-size: 9px; color: #555; }
        .card-blue   { background-color: #E8F1FC; }
        .card-green  { background-color: #E7F7EE; }
        .card-red    { background-color: #FBEAEA; }
        .card-orange { background-color: #FDF3E3; }
        .card-blue .value   { color: #1a4d8f; }
        .card-green .value  { color: #1a7f4e; }
        .card-red .value    { color: #b02a37; }
        .card-orange .value { color: #a15c00; }

        .chart-title { font-size: 12px; font-weight: bold; margin: 14px 0 8px; }
        .bar-row { width: 100%; margin-bottom: 6px; }
        .bar-table { width: 100%; }
        .bar-label { width: 110px; text-align: left; padding-right: 6px; font-size: 10px; }
        .bar-value { width: 45px; text-align: right; padding-left: 6px; font-size: 10px; }
        .bar-track { background-color: #eef2f6; border-radius: 3px; height: 14px; }
        .bar-fill { background: linear-gradient(to right, #2c6fbb, #4a90d9); height: 14px; border-radius: 3px; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.data th, table.data td { border: 1px solid #ddd; padding: 6px; text-align: center; }
        table.data th { background-color: #DCE6F1; }
        table.data tr:nth-child(even) { background-color: #f8f9fa; }

        .footer { position: fixed; bottom: -10px; width: 100%; text-align: center; font-size: 8px; color: #999; }
    </style>
</head>
<body>
    <h1>Transfer Requests Report</h1>
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
                    <span class="label">Total Requests</span>
                    <span class="value">{{ number_format($summary['total_requests']) }}</span>
                </td>
                <td class="card card-green">
                    <span class="label">Approved</span>
                    <span class="value">{{ number_format($summary['approved']['count']) }} ({{ $summary['approved']['percentage'] }}%)</span>
                </td>
                <td class="card card-red">
                    <span class="label">Rejected</span>
                    <span class="value">{{ number_format($summary['rejected']['count']) }} ({{ $summary['rejected']['percentage'] }}%)</span>
                </td>
                <td class="card card-orange">
                    <span class="label">Pending</span>
                    <span class="value">{{ number_format($summary['pending']['count']) }} ({{ $summary['pending']['percentage'] }}%)</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- ===================== Bar Chart: Requests by Camp ===================== --}}
    <div class="chart-title">Transfer Requests by Origin Camp</div>
    @php
        $maxRequests = $distributionByCamp->max('requests') ?: 1;
    @endphp
    @foreach($distributionByCamp as $row)
        <div class="bar-row">
            <table class="bar-table">
                <tr>
                    <td class="bar-label">{{ $row['camp_name'] }}</td>
                    <td>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ round(($row['requests'] / $maxRequests) * 100) }}%;"></div>
                        </div>
                    </td>
                    <td class="bar-value">{{ number_format($row['requests']) }}</td>
                </tr>
            </table>
        </div>
    @endforeach

    {{-- ===================== Monthly Breakdown Table ===================== --}}
    <div class="chart-title">Monthly Breakdown</div>
    <table class="data">
        <thead>
            <tr>
                <th>Month</th>
                <th>Requests</th>
                <th>Approved</th>
                <th>Rejected</th>
                <th>Pending</th>
            </tr>
        </thead>
        <tbody>
            @foreach($monthlyBreakdown as $row)
            <tr>
                <td>{{ $row['month'] }}</td>
                <td>{{ number_format($row['requests']) }}</td>
                <td>{{ number_format($row['approved']) }}</td>
                <td>{{ number_format($row['rejected']) }}</td>
                <td>{{ number_format($row['pending']) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Displaced Families Management System</div>
</body>
</html>