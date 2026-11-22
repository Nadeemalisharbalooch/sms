<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 10px; }
        .voucher-page { page-break-inside: avoid; page-break-after: always; }
        .voucher-page:last-child { page-break-after: auto; }
        .voucher { border: 1px solid #64748b; padding: 9px 12px; margin-bottom: 8px; page-break-inside: avoid; }
        .copy-label { float: right; color: #475569; font-size: 9px; font-weight: bold; text-transform: uppercase; border: 1px solid #94a3b8; padding: 4px 7px; }
        .brand { border-bottom: 2px solid #334155; padding-bottom: 8px; margin-bottom: 10px; }
        .brand-logo { float: left; max-width: 58px; max-height: 58px; margin-right: 10px; }
        .brand-copy { overflow: hidden; }
        .brand h1 { margin: 0 0 3px; font-size: 17px; }
        .brand p { margin: 2px 0; color: #475569; }
        h2 { margin: 8px 0; font-size: 13px; }
        .details { width: 100%; border-collapse: collapse; }
        .details td { padding: 4px 2px; vertical-align: top; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .items th, .items td { border-bottom: 1px solid #cbd5e1; padding: 6px; text-align: left; }
        .items th:last-child, .items td:last-child { text-align: right; }
        .totals { margin-top: 8px; width: 100%; border-collapse: collapse; }
        .totals td { padding: 4px; }
        .totals td:last-child { text-align: right; }
        .grand { font-weight: bold; border-top: 1px solid #64748b; }
        .manual { margin-top: 11px; padding-top: 8px; border-top: 1px dashed #64748b; line-height: 2.2; }
        .muted { color: #64748b; }
        .candidate { border-top: 1px dashed #64748b; }
        .clear { clear: both; }
    </style>
</head>
<body>
@foreach ($vouchers as $voucher)
    @php($student = $voucher->student)
    @php($enrollment = $student?->enrollments?->first())
    @php($currency = $institute->currency_symbol ?: 'Rs')
    @php($lateFee = $voucher->late_fee_waived ? 0 : (float) ($voucher->late_fee_amount ?? 0))
    @php($totalAfterDue = (float) $voucher->total_amount + $lateFee)
    @php($amountDueAfterDue = max(0, $totalAfterDue - (float) $voucher->paid_amount - (float) ($voucher->carried_forward_amount ?? 0)))
    @php($balanceDue = $voucher->status === 'carried_forward' ? (float) ($voucher->carried_forward_amount ?? 0) : (float) $voucher->balance_due)
    <div class="voucher-page">
    @foreach (['Institute Copy', 'Candidate Copy'] as $copy)
        <section class="voucher {{ $loop->last ? 'candidate' : '' }}">
            <span class="copy-label">{{ $copy }}</span>
            <div class="brand">
                @if ($logoDataUri)
                    <img class="brand-logo" src="{{ $logoDataUri }}" alt="Institute logo">
                @endif
                <div class="brand-copy">
                    <h1>{{ $institute->name ?: 'School / Institute' }}</h1>
                    <p>{{ $institute->address }}</p>
                    <p>Helpline: {{ $institute->phone ?: 'Not provided' }}@if($institute->email) &nbsp; | &nbsp; {{ $institute->email }}@endif</p>
                </div>
            </div>
            <div class="clear"></div>
            <h2>Fee Voucher - Billing Month: {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $voucher->billing_month)->format('F Y') }}</h2>
            <table class="details">
                <tr><td><strong>Voucher ID:</strong> #{{ $voucher->id }}</td><td><strong>Due Date:</strong> {{ $voucher->due_date?->format('d M Y') ?? '-' }}</td></tr>
                <tr><td><strong>Candidate:</strong> {{ trim(($student?->first_name ?? '').' '.($student?->last_name ?? '')) }}</td><td><strong>Roll No:</strong> {{ $enrollment?->roll_number ?? '-' }}</td></tr>
                <tr><td><strong>Class / Section:</strong> {{ $enrollment?->academicClass?->name ?? '-' }} / {{ $enrollment?->section?->name ?? '-' }}</td><td><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $voucher->status)) }}</td></tr>
            </table>
            <table class="items">
                <thead><tr><th>Description</th><th>Amount ({{ $currency }})</th></tr></thead>
                <tbody>
                @foreach ($voucher->items as $item)
                    @php($isDiscount = $item->type === 'discount' || str_contains(strtolower($item->description), 'discount'))
                    @php($amount = abs((float) $item->amount) * ($isDiscount ? -1 : 1))
                    <tr><td>{{ $item->description }}</td><td>{{ $isDiscount ? '-' : '' }}{{ number_format(abs($amount), 2) }}</td></tr>
                @endforeach
                </tbody>
            </table>
            <table class="totals">
                <tr><td>Base Total (Before Due Date)</td><td>{{ $currency }} {{ number_format((float) $voucher->total_amount, 2) }}</td></tr>
                <tr><td>Late Fee Penalty (if paid after {{ $voucher->due_date?->format('d M Y') ?? 'due date' }})</td><td>+ {{ $currency }} {{ number_format($lateFee, 2) }}</td></tr>
                <tr class="grand"><td>Total After Due Date</td><td>{{ $currency }} {{ number_format($totalAfterDue, 2) }}</td></tr>
                <tr><td>Paid Amount</td><td>{{ $currency }} {{ number_format((float) $voucher->paid_amount, 2) }}</td></tr>
                <tr class="grand"><td>Amount Due After Due Date</td><td>{{ $currency }} {{ number_format($amountDueAfterDue, 2) }}</td></tr>
                <tr class="grand"><td>Balance Due{{ $voucher->status === 'carried_forward' ? ' (Carried Forward)' : '' }}</td><td>{{ $currency }} {{ number_format($balanceDue, 2) }}</td></tr>
            </table>
            <div class="manual">
                @if ($copy === 'Institute Copy')
                    Paid Amount Received: ______________________________________________<br>
                    Payment Date: ____________ / ____________ / ____________<br>
                    Remaining Balance Due: ____________________________________________<br>
                    Payment Mode: [ ] Cash &nbsp; [ ] Bank Transfer &nbsp; [ ] Online &nbsp; [ ] Cheque<br>
                    Received By (Cashier Signature): ____________________ &nbsp; [ Official School Stamp ]
                @else
                    Payment Date: ____________ / ____________ / ____________<br>
                    Balance Due: {{ $currency }} {{ number_format($balanceDue, 2) }}<br>
                    Please retain this copy as your official payment receipt.<br>
                    Cashier Signature: ____________________ &nbsp; [ Official School Stamp ]
                @endif
            </div>
        </section>
    @endforeach
    </div>
@endforeach
</body>
</html>
