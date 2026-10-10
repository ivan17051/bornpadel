@props([
    'peserta' => null,
    'status' => null,
    'paymentStatus' => null,
    'statusLabel' => null,
    'paymentLabel' => null,
])

@php
    $hasReceipt = (bool) optional($peserta)->bukti_bayar;
    $state = \App\Models\TurnamenPeserta::normalizeRegistrationState(
        $status ?? optional($peserta)->status,
        $paymentStatus ?? optional($peserta)->payment_status,
        $hasReceipt
    );
    $status = $state['status'];
    $paymentStatus = $state['payment_status'];
    $statusLabels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];
    $statusLabel = $statusLabels[$status] ?? 'Pending';
    $paymentLabel = $paymentStatus === 'paid' ? 'Paid' : 'Unpaid';
@endphp

@if ($status)
    <div {{ $attributes->merge(['class' => 'd-flex flex-wrap gap-1']) }}>
        <span class="badge status-badge-{{ $status }}" data-status-cell>{{ $statusLabel }}</span>
        <span class="badge status-badge-{{ $paymentStatus }}" data-payment-cell>{{ $paymentLabel }}</span>
    </div>
@else
    <span {{ $attributes->merge(['class' => 'text-muted small']) }}>—</span>
@endif
