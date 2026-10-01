@props([
    'peserta' => null,
    'status' => null,
    'paymentStatus' => null,
    'statusLabel' => null,
    'paymentLabel' => null,
])

@php
    $status = $status ?? optional($peserta)->status;
    $paymentStatus = $paymentStatus ?? optional($peserta)->payment_status ?? 'unpaid';
    $statusLabels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];
    $statusLabel = $statusLabel
        ?? optional($peserta)->status_label
        ?? ($statusLabels[$status] ?? ucfirst((string) $status));
    $paymentLabel = $paymentLabel
        ?? optional($peserta)->payment_status_label
        ?? ($paymentStatus === 'paid' ? 'Paid' : 'Unpaid');
@endphp

@if ($status)
    <div {{ $attributes->merge(['class' => 'd-flex flex-wrap gap-1']) }}>
        <span class="badge status-badge-{{ $status }}" data-status-cell>{{ $statusLabel }}</span>
        <span class="badge status-badge-{{ $paymentStatus }}" data-payment-cell>{{ $paymentLabel }}</span>
    </div>
@else
    <span {{ $attributes->merge(['class' => 'text-muted small']) }}>—</span>
@endif
