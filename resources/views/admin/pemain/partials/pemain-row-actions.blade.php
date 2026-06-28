@php
    $hasApprove = $turnamen && in_array($registrationStatus, ['pending', 'rejected', 'unpaid', 'paid'], true);
    $hasReject = $turnamen && (
        ($registrationStatus === 'pending' && ! $turnamenOngoing)
        || ($registrationStatus === 'approved' && ! $turnamenOngoing)
    );
    $hasDelete = ! $turnamenOngoing;
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
        <li>
            <a class="dropdown-item"
               href="{{ route('admin.pemain.edit', array_merge([$pemain], request()->only('id_turnamen'))) }}">
                <i class="bi bi-pencil me-2"></i> Edit
            </a>
        </li>

        @if ($hasApprove)
            <li>
                <button type="button"
                        class="dropdown-item btn-approve"
                        data-url="{{ route('admin.pemain.status', $pemain) }}"
                        data-turnamen="{{ $turnamen->id }}">
                    <i class="bi bi-check-lg me-2"></i> Setujui
                </button>
            </li>
        @endif

        @if ($hasReject)
            <li>
                <button type="button"
                        class="dropdown-item btn-reject"
                        data-url="{{ route('admin.pemain.status', $pemain) }}"
                        data-turnamen="{{ $turnamen->id }}">
                    <i class="bi bi-x-lg me-2"></i> Tolak
                </button>
            </li>
        @endif

        @if ($hasDelete)
            <li><hr class="dropdown-divider"></li>
            <li>
                <button type="button"
                        class="dropdown-item text-danger btn-delete-pemain"
                        data-url="{{ route('admin.pemain.destroy', $pemain) }}"
                        data-name="{{ $pemain->nama }}">
                    <i class="bi bi-trash me-2"></i> Hapus
                </button>
            </li>
        @endif
    </ul>
</div>
