<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $payload['title'] }}</title>
    <style>
        @page { margin: 28px; }
        body { color: #0f172a; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 2px solid #2563eb; margin-bottom: 14px; padding-bottom: 10px; }
        .title { font-size: 21px; }
        .muted { color: #64748b; }
        .meta { margin-top: 7px; width: 100%; }
        .meta td { padding: 2px 10px 2px 0; vertical-align: top; }
        .summary { border-collapse: separate; border-spacing: 6px; margin: 0 -6px 12px; table-layout: fixed; width: 100%; }
        .summary td { background: #eff6ff; border: 1px solid #bfdbfe; padding: 9px; vertical-align: top; }
        .summary-value { color: #1d4ed8; font-size: 18px; font-weight: bold; }
        .chart { border: 1px solid #cbd5e1; margin-bottom: 12px; padding: 10px; page-break-inside: avoid; }
        .chart-title { font-size: 13px; margin-bottom: 3px; }
        .chart-table { border-collapse: collapse; margin-top: 8px; table-layout: fixed; width: 100%; }
        .chart-table td { padding: 3px 4px; vertical-align: middle; }
        .label { overflow: hidden; width: 31%; }
        .value { font-weight: bold; text-align: right; width: 10%; }
        .bar-cell { width: 59%; }
        .bar-track { background: #e2e8f0; height: 11px; width: 100%; }
        .bar { background: #2563eb; height: 11px; min-width: 2px; }
        .details { border-collapse: collapse; margin-top: 8px; width: 100%; }
        .details th { background: #0f172a; color: #fff; padding: 6px; text-align: left; }
        .details td { border-bottom: 1px solid #e2e8f0; padding: 5px 6px; }
        .numeric { text-align: right; }
        .page-break { page-break-before: always; }
        .footer { color: #64748b; font-size: 8px; margin-top: 12px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">{{ $payload['title'] }}</h1>
        <p class="muted">Generated {{ $generatedAt->format('F j, Y g:i A') }} · {{ config('app.timezone') }}</p>
        <table class="meta">
            <tr>
                <td><strong>Date range:</strong> {{ $payload['filters']['date_from'] ?: 'All' }} to {{ $payload['filters']['date_to'] ?: 'All' }}</td>
                <td><strong>Academic year:</strong> {{ $payload['selectedAcademicYear']['name'] ?? 'All years' }}</td>
                <td><strong>Semester:</strong> {{ $payload['filters']['semester'] ?: 'All semesters' }}</td>
            </tr>
        </table>
    </div>

    <table class="summary">
        <tr>
            @foreach ($payload['summaryCards'] as $card)
                <td>
                    <div class="muted">{{ $card['label'] }}</div>
                    <div class="summary-value">{{ number_format((float) $card['value']) }}</div>
                    @if ($card['detail'])
                        <div class="muted">{{ $card['detail'] }}</div>
                    @endif
                </td>
                @if (($loop->iteration % 4) === 0 && ! $loop->last)
                    </tr><tr>
                @endif
            @endforeach
        </tr>
    </table>

    <h2 style="margin-bottom: 8px;">Report diagrams</h2>
    @foreach ($payload['charts'] as $chart)
        @php
            $values = collect($chart['data'])->map(fn ($item) => (float) ($item['value'] ?? 0));
            $maximum = max(1, (float) ($values->max() ?? 0));
            $total = (float) $values->sum();
        @endphp
        <div class="chart">
            <h2 class="chart-title">{{ $chart['title'] }}</h2>
            <p class="muted">{{ ucfirst($chart['type']) }} diagram · Total {{ number_format($total) }}</p>
            @if (count($chart['data']))
                <table class="chart-table">
                    @foreach ($chart['data'] as $datum)
                        @php $width = max(1, round(((float) ($datum['value'] ?? 0) / $maximum) * 100)); @endphp
                        <tr>
                            <td class="label">{{ $datum['label'] }}</td>
                            <td class="bar-cell"><div class="bar-track"><div class="bar" style="width: {{ $width }}%;"></div></div></td>
                            <td class="value">{{ number_format((float) ($datum['value'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </table>
            @else
                <p class="muted" style="margin-top: 8px;">No data available for this diagram.</p>
            @endif
        </div>
    @endforeach

    <div class="page-break"></div>
    <h2>Report details</h2>
    <table class="details">
        <thead>
            <tr><th>Category</th><th>Metric</th><th class="numeric">Value</th><th>Group</th></tr>
        </thead>
        <tbody>
            @forelse ($payload['tableRows'] as $row)
                <tr>
                    <td>{{ $row['category'] }}</td>
                    <td>{{ $row['metric'] }}</td>
                    <td class="numeric">{{ number_format((float) $row['value']) }}</td>
                    <td>{{ $row['group'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No report rows available.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="footer">Generated by the RFID Attendance and School Operations System.</p>
</body>
</html>
