@php
    $representativePemain = $pemain1 ?? $pemain2;
    $missingSlot = ! $pemain1 ? 1 : (! $pemain2 ? 2 : null);
    $hasPairApprove = $turnamen && $representativePemain && in_array($registrationStatus, ['pending', 'rejected', 'unpaid', 'paid'], true);
    $hasPairReject = $turnamen && $representativePemain && (
        ($registrationStatus === 'pending' && ! $turnamenOngoing)
        || ($registrationStatus === 'approved' && ! $turnamenOngoing)
    );
    $hasDelete = $turnamen && ! $turnamenOngoing;
@endphp

<div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
            type="button"
            data-bs-toggle="dropdown"
            data-bs-popper-config='{"strategy":"fixed","placement":"bottom-end"}'
            aria-expanded="false">
        <i class="bi bi-three-dots-vertical"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @if ($pemain1)
            <li>
                <a class="dropdown-item"
                   href="{{ route('admin.pemain.edit', array_merge([$pemain1], request()->only('id_turnamen'))) }}">
                    <i class="bi bi-pencil me-2"></i> Edit {{ $pemain1->nama }}
                </a>
            </li>
        @endif

        @if ($pemain2)
            <li>
                <a class="dropdown-item"
                   href="{{ route('admin.pemain.edit', array_merge([$pemain2], request()->only('id_turnamen'))) }}">
                    <i class="bi bi-pencil me-2"></i> Edit {{ $pemain2->nama }}
                </a>
            </li>
        @endif

        @if ($missingSlot && ($peserta ?? null))
            <li>
                <a class="dropdown-item"
                   href="{{ route('admin.pemain.peserta.slot.create', ['peserta' => $peserta->id, 'slot' => $missingSlot]) }}">
                    <i class="bi bi-person-plus me-2"></i> Tambah Pemain {{ $missingSlot }}
                </a>
            </li>
        @endif

        @if ($hasPairApprove)
            <li><hr class="dropdown-divider"></li>
            <li>
                <button type="button"
                        class="dropdown-item btn-approve"
                        data-url="{{ route('admin.pemain.status', $representativePemain) }}"
                        data-turnamen="{{ $turnamen->id }}">
                    <i class="bi bi-check-all me-2"></i> Setujui Pasangan
                </button>
            </li>
        @endif

        @if ($hasPairReject)
            <li>
                <button type="button"
                        class="dropdown-item btn-reject"
                        data-url="{{ route('admin.pemain.status', $representativePemain) }}"
                        data-turnamen="{{ $turnamen->id }}">
                    <i class="bi bi-x-lg me-2"></i> Tolak Pasangan
                </button>
            </li>
        @endif

        @if ($hasDelete && ($pemain1 || $pemain2))
            <li><hr class="dropdown-divider"></li>
        @endif

        @if ($hasDelete && $pemain1)
            <li>
                <button type="button"
                        class="dropdown-item text-danger btn-delete-pemain"
                        data-url="{{ route('admin.pemain.registration.destroy', $pemain1) }}"
                        data-turnamen="{{ $turnamen->id }}"
                        data-name="{{ $pemain1->nama }}">
                    <i class="bi bi-trash me-2"></i> Hapus {{ $pemain1->nama }}
                </button>
            </li>
        @endif

        @if ($hasDelete && $pemain2)
            <li>
                <button type="button"
                        class="dropdown-item text-danger btn-delete-pemain"
                        data-url="{{ route('admin.pemain.registration.destroy', $pemain2) }}"
                        data-turnamen="{{ $turnamen->id }}"
                        data-name="{{ $pemain2->nama }}">
                    <i class="bi bi-trash me-2"></i> Hapus {{ $pemain2->nama }}
                </button>
            </li>
        @endif
    </ul>
</div>
