<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 11px; }
        .voucher { border: 1px solid #475569; padding: 14px; margin-bottom: 14px; page-break-inside: avoid; }
        .copy-label { float: right; color: #475569; font-size: 10px; text-transform: uppercase; }
        h2 { margin: 0 0 3px; font-size: 18px; }
        h3 { margin: 0 0 12px; font-size: 13px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #cbd5e1; padding: 6px; text-align: left; }
        th:last-child, td:last-child { text-align: right; }
        .totals { margin-top: 10px; text-align: right; line-height: 1.7; }
        .candidate { border-top: 1px dashed #64748b; margin-top: 12px; page-break-before: auto; }
        .candidate:before { content: ''; display: block; margin: 0 -14px 12px; border-top: 1px dashed #64748b; }
        .clear { clear: both; }
    </style>
</head>
<body>
@foreach ($vouchers as $voucher)
    @php($student = $voucher->student)
    @php($enrollment = $student?->enrollments?->first())
    @foreach (['Institute Copy', 'Candidate Copy'] as $copy)
        <section class="voucher {{ $loop->last ? 'candidate' : '' }}">
            <span class="copy-label">{{ $copy }}</span>
            <h2>{{ $institute->name }}</h2>
            <h3>Fee Voucher · {{ $voucher->billing_month }}</h3>
            <div class="clear"></div>
            <div><strong>Voucher:</strong> #{{ $voucher->id }} &nbsp; <strong>Due date:</strong> {{ $voucher->due_date?->format('d M Y') ?? '—' }}</div>
            <div><strong>Candidate:</strong> {{ trim(($student?->first_name ?? '').' '.($student?->last_name ?? '')) }} &nbsp; <strong>Roll no:</strong> {{ $enrollment?->roll_number ?? '—' }}</div>
            <div><strong>Class / Section:</strong> {{ $enrollment?->academicClass?->name ?? '—' }} / {{ $enrollment?->section?->name ?? '—' }} &nbsp; <strong>Status:</strong> {{ ucfirst($voucher->status) }}</div>
            <table><thead><tr><th>Description</th><th>Amount</th></tr></thead><tbody>
            @foreach ($voucher->items as $item)
                <tr><td>{{ $item->description }}</td><td>{{ number_format((float) $item->amount, 2) }}</td></tr>
            @endforeach
            </tbody></table>
            <div class="totals">
                Total: {{ number_format((float) $voucher->total_amount, 2) }}<br>
                Paid: {{ number_format((float) $voucher->paid_amount, 2) }}<br>
                <strong>Balance due: {{ number_format($voucher->balance_due, 2) }}</strong>
            </div>
            <p class="muted">Please retain this copy for your records.</p>
        </section>
    @endforeach
@endforeach
</body>
</html>
