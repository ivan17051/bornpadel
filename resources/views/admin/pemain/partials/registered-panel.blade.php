@php
    $filterRoute = $filterRoute ?? route('admin.pemain.index');
    $preserveTab = $preserveTab ?? null;
    $soloPesertaOptions = $soloPesertaOptions ?? collect();
    $showBulkActions = auth()->user()->isAdmin() && $turnamen && empty($isDoubleView);
    $showPartnerActions = auth()->user()->isAdmin() && $turnamen && $turnamen->requiresPairRegistration() && ! $turnamen->isRegistrationClosed() && empty($isDoubleView);
    $showPartnerColumn = $turnamen && $turnamen->requiresPairRegistration() && empty($isDoubleView);
    $showFriendlyGroups = $turnamen && $turnamen->allowsGroupRegistration() && empty($isDoubleView) && ! request()->filled('sort');
    $canEditRegistrationGroups = $canEditRegistrationGroups ?? false;
    $friendlyRegistrationGroupTargets = collect($friendlyRegistrationGroupTargets ?? []);
    $friendlyPlayersPerGroup = $turnamen && $turnamen->allowsGroupRegistration()
        ? (isset($kategori) && $kategori
            ? $kategori->friendlyPlayersPerGroup()
            : $turnamen->friendlyPlayersPerGroup())
        : 4;
    $sortThParams = compact('filterRoute', 'preserveTab');
@endphp

@once
@push('styles')
<style>
    .pemain-table-sort-link {
        display: inline-flex;
        align-items: center;
        gap: 0.15rem;
        white-space: nowrap;
    }

    .pemain-table-sort-link:hover {
        color: var(--bs-primary) !important;
    }

    .pemain-table-sort-icon {
        font-size: 0.85rem;
        opacity: 0.35;
    }

    .pemain-table-sort-icon.is-active {
        opacity: 1;
        color: var(--bs-primary);
    }
</style>
@endpush
@endonce

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ $filterRoute }}" class="row g-2 align-items-end">
            @if (request('id_turnamen'))
                <input type="hidden" name="id_turnamen" value="{{ request('id_turnamen') }}">
            @endif
            @if ($preserveTab)
                <input type="hidden" name="tab" value="{{ $preserveTab }}">
            @endif
            @if (request('sort'))
                <input type="hidden" name="sort" value="{{ request('sort') }}">
            @endif
            @if (request('dir'))
                <input type="hidden" name="dir" value="{{ request('dir') }}">
            @endif
            <div class="col-md-5">
                <label class="form-label small text-muted">Cari</label>
                <input type="text" name="search" class="form-control" placeholder="Nama atau no. HP..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Status Pendaftaran</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <a href="{{ $filterRoute . '?' . http_build_query(array_filter(array_merge(request()->only('id_turnamen'), $preserveTab ? ['tab' => $preserveTab] : []))) }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card pemain-table-card"
     id="pemain-table-card"
     @if ($turnamen)
     data-turnamen-id="{{ $turnamen->id }}"
     data-bulk-approve-url="{{ route('admin.peserta.bulk-approve') }}"
     @if (! empty($kategoriId) || ! empty(optional($kategori ?? null)->id))
     data-kategori="{{ $kategoriId ?? $kategori->id }}"
     @endif
     @if ($canEditRegistrationGroups)
     data-reg-edit="1"
     data-reg-players-per-group="{{ $friendlyPlayersPerGroup }}"
     data-reg-assign-url="{{ route('admin.pemain.friendly.registration-group.assign') }}"
     data-reg-remove-url="{{ route('admin.pemain.friendly.registration-group.remove') }}"
     data-reg-rename-url-template="{{ route('admin.pemain.friendly.registration-group.rename', ['group' => '__ID__']) }}"
     data-reg-groups='@json($friendlyRegistrationGroupTargets->values())'
     @endif
     @endif>
    <div class="card-header d-flex justify-content-between align-items-center row border-top-0">
        <div class="col-md-6">
            <h5 class="card-title mb-0">
                Daftar Pemain
                @if ($turnamen)
                    <small class="text-muted fw-normal">— {{ $turnamen->nama }}</small>
                @endif
            </h5>
        </div>
        <div class="col-md-6 text-end">
            <div class="d-inline-flex align-items-center gap-2 flex-wrap justify-content-end">
                <span class="badge text-bg-secondary">
                    @if (! empty($isDoubleView))
                        {{ $peserta->total() }} pasangan
                    @else
                        {{ $pemain->total() }} pemain
                    @endif
                </span>
                @php
                    $capacityMaks = isset($kategori) && $kategori
                        ? $kategori->maks_peserta
                        : optional($turnamen)->maks_peserta;
                    $availableQuery = array_filter([
                        'id_turnamen' => optional($turnamen)->id,
                        'id_kategori' => optional($kategori ?? null)->id
                            ?? request('id_kategori'),
                    ]);
                @endphp
                @if ($turnamen && $capacityMaks)
                    <span class="badge text-bg-light text-dark border">
                        Maks. {{ $capacityMaks }} disetujui
                    </span>
                @endif
                @if ($showBulkActions)
                    <button type="button"
                            class="btn btn-success btn-sm btn-bulk-approve"
                            disabled
                            title="Pilih peserta pada tabel terlebih dahulu">
                        <i class="bi bi-check-all me-1"></i> Setujui Terpilih
                    </button>
                @endif
                @if ($turnamen)
                    <a href="{{ route('admin.pemain.available', $availableQuery) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-person-plus me-1"></i> Tambah Pemain
                    </a>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" id="pemain-table-wrapper">
            <table class="table table-hover table-striped mb-0 align-middle" id="pemain-table">
                <thead class="table-light">
                    <tr>
                        @if (! empty($isDoubleView))
                            <th>#</th>
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Pemain 1', 'column' => 'pemain1_nama']))
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Gender', 'column' => 'pemain1_gender', 'class' => 'd-none d-lg-table-cell']))
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Rating', 'column' => 'pemain1_rating', 'class' => 'd-none d-lg-table-cell']))
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Pemain 2', 'column' => 'pemain2_nama']))
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Gender', 'column' => 'pemain2_gender', 'class' => 'd-none d-lg-table-cell']))
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Rating', 'column' => 'pemain2_rating', 'class' => 'd-none d-lg-table-cell']))
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Status', 'column' => 'status']))
                            <th class="text-end">Aksi</th>
                        @else
                            @if ($showBulkActions)
                                <th style="width: 2.5rem;">
                                    <input type="checkbox" class="form-check-input select-all-approvable" title="Pilih semua yang dapat disetujui">
                                </th>
                            @endif
                            <th style="width: 3.5rem;"></th>
                            <th>#</th>
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Nama', 'column' => 'nama']))
                            @if ($showPartnerColumn)
                                @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Partner', 'column' => 'partner']))
                            @endif
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'No. HP', 'column' => 'no_hp', 'class' => 'd-none d-md-table-cell']))
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Gender', 'column' => 'gender', 'class' => 'd-none d-lg-table-cell']))
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Rating', 'column' => 'rating', 'class' => 'd-none d-lg-table-cell']))
                            @include('admin.pemain.partials.sortable-th', array_merge($sortThParams, ['label' => 'Status', 'column' => 'status']))
                            <th class="text-end">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php
                        $turnamenOngoing = $turnamen && $turnamen->status === 'ongoing';
                    @endphp
                    @if (! empty($isDoubleView))
                        @forelse ($peserta as $entry)
                            @php
                                $pemain1 = $entry->pemain1;
                                $pemain2 = $entry->pemain2;
                            @endphp
                            <tr data-peserta-id="{{ $entry->id }}">
                                <td>{{ $peserta->firstItem() + $loop->index }}</td>
                                <td>@include('admin.pemain.partials.pemain-cell', ['pemain' => $pemain1, 'turnamen' => $turnamen])</td>
                                <td class="d-none d-lg-table-cell">
                                    {{ $pemain1 && $pemain1->gender === 'male' ? 'Laki-laki' : ($pemain1 ? 'Perempuan' : '—') }}
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    {{ $pemain1 ? number_format($pemain1->rating, 1) : '—' }}
                                </td>
                                <td>@include('admin.pemain.partials.pemain-cell', ['pemain' => $pemain2, 'turnamen' => $turnamen])</td>
                                <td class="d-none d-lg-table-cell">
                                    {{ $pemain2 && $pemain2->gender === 'male' ? 'Laki-laki' : ($pemain2 ? 'Perempuan' : '—') }}
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    {{ $pemain2 ? number_format($pemain2->rating, 1) : '—' }}
                                </td>
                                <td>
                                    <span class="badge status-badge-{{ $entry->status }}" data-status-cell>
                                        {{ ucfirst($entry->status) }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    @include('admin.pemain.partials.pemain-pair-row-actions', [
                                        'peserta' => $entry,
                                        'pemain1' => $pemain1,
                                        'pemain2' => $pemain2,
                                        'turnamen' => $turnamen,
                                        'registrationStatus' => $entry->status,
                                        'turnamenOngoing' => $turnamenOngoing,
                                        'pemainEditFrom' => $pemainEditFrom ?? 'index',
                                    ])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    @if ($turnamen)
                                        Belum ada pasangan terdaftar pada turnamen ini.
                                    @else
                                        Pilih turnamen untuk melihat daftar pemain.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    @else
                        @php
                            $prevFriendlyGroupKey = '__unset__';
                            $friendlyColspan = 8 + ($showBulkActions ? 1 : 0) + ($showPartnerColumn ? 1 : 0);
                        @endphp
                        @forelse ($pemain as $item)
                            @php
                                $pesertaRow = $turnamen ? $item->turnamenPesertaAsPemain1->first() : null;
                                $partnerPemain = $showPartnerColumn ? optional($pesertaRow)->partner_pemain : null;
                                $registrationStatus = optional($pesertaRow)->status;
                                $canBulkApprove = $showBulkActions && in_array($registrationStatus, ['pending', 'unpaid', 'paid', 'rejected'], true);
                                $friendlyGroup = $showFriendlyGroups
                                    ? optional(optional($pesertaRow)->grupPendaftaranMember)->grupPendaftaran
                                    : null;
                                $friendlyGroupKey = $friendlyGroup ? 'g:' . $friendlyGroup->id : 'solo';
                            @endphp
                            @if ($showFriendlyGroups && $friendlyGroupKey !== $prevFriendlyGroupKey)
                                <tr class="table-secondary">
                                    <td colspan="{{ $friendlyColspan }}" class="small fw-semibold py-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span>
                                                <i class="bi bi-people me-1"></i>
                                                <span class="friendly-reg-group-name" @if ($friendlyGroup) data-group-id="{{ $friendlyGroup->id }}" @endif>
                                                    {{ $friendlyGroup ? $friendlyGroup->nama : 'Individu / Belum berkelompok' }}
                                                </span>
                                                @if ($friendlyGroup)
                                                    @php
                                                        $groupTargetMeta = $friendlyRegistrationGroupTargets->firstWhere('id', (int) $friendlyGroup->id);
                                                        $groupCount = $groupTargetMeta['count'] ?? null;
                                                    @endphp
                                                    @if ($groupCount !== null)
                                                        <span class="badge text-bg-light text-dark border ms-1">
                                                            {{ $groupCount }}/{{ $friendlyPlayersPerGroup }}
                                                        </span>
                                                    @endif
                                                @endif
                                            </span>
                                            @if ($canEditRegistrationGroups && $friendlyGroup)
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-secondary friendly-reg-rename-open ms-auto"
                                                        data-group-id="{{ $friendlyGroup->id }}"
                                                        data-group-name="{{ $friendlyGroup->nama }}"
                                                        title="Ubah nama grup"
                                                        aria-label="Ubah nama grup">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @php $prevFriendlyGroupKey = $friendlyGroupKey; @endphp
                            @endif
                            <tr data-pemain-id="{{ $item->id }}" data-peserta-id="{{ optional($pesertaRow)->id }}">
                                @if ($showBulkActions)
                                    <td>
                                        @if ($canBulkApprove)
                                            <input type="checkbox"
                                                   class="form-check-input peserta-bulk-checkbox"
                                                   value="{{ $pesertaRow->id }}"
                                                   data-peserta-id="{{ $pesertaRow->id }}">
                                        @endif
                                    </td>
                                @endif
                                <td><x-pemain-avatar :pemain="$item" :size="40" /></td>
                                <td>{{ $pemain->firstItem() + $loop->index }}</td>
                                <td>
                                    <strong>
                                        <x-pemain-link :pemain="$item" class="text-decoration-none text-dark" />
                                    </strong>
                                    <div class="small text-muted d-md-none">{{ $item->no_hp }}</div>
                                    @if ($turnamen && $item->hasPriorRegistrations($turnamen->id))
                                        <span class="badge text-bg-warning text-dark mt-1" title="Profil sudah ada sebelum turnamen ini">
                                            Profil existing
                                        </span>
                                    @endif
                                </td>
                                @if ($showPartnerColumn)
                                    <td>
                                        @if ($partnerPemain)
                                            <x-pemain-link :pemain="$partnerPemain" class="text-decoration-none" />
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="d-none d-md-table-cell">{{ $item->no_hp }}</td>
                                <td class="d-none d-lg-table-cell">{{ $item->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}</td>
                                <td class="d-none d-lg-table-cell">{{ number_format($item->rating, 1) }}</td>
                                <td>
                                    @if ($registrationStatus)
                                        <span class="badge status-badge-{{ $registrationStatus }}" data-status-cell>
                                            {{ ucfirst($registrationStatus) }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @php
                                        $regGroupMove = null;
                                        $regGroupRemove = null;
                                        if ($canEditRegistrationGroups && $pesertaRow) {
                                            $fromGroupId = $friendlyGroup ? (int) $friendlyGroup->id : null;
                                            $moveTargets = $friendlyRegistrationGroupTargets->filter(function ($group) use ($fromGroupId) {
                                                if (($group['slots'] ?? 0) <= 0) {
                                                    return false;
                                                }
                                                if ($fromGroupId && (int) $group['id'] === $fromGroupId) {
                                                    return false;
                                                }

                                                return true;
                                            });
                                            if ($moveTargets->isNotEmpty()) {
                                                $regGroupMove = [
                                                    'peserta_id' => $pesertaRow->id,
                                                    'pemain_name' => $item->nama,
                                                    'from_group_id' => $fromGroupId ?: '',
                                                    'label' => 'Pindah Grup',
                                                ];
                                            }
                                            if ($friendlyGroup) {
                                                $regGroupRemove = [
                                                    'peserta_id' => $pesertaRow->id,
                                                    'pemain_name' => $item->nama,
                                                    'label' => 'Lepas dari grup',
                                                ];
                                            }
                                        }
                                    @endphp
                                    @include('admin.pemain.partials.pemain-row-actions', [
                                        'pemain' => $item,
                                        'peserta' => $pesertaRow,
                                        'turnamen' => $turnamen,
                                        'registrationStatus' => $registrationStatus,
                                        'turnamenOngoing' => $turnamenOngoing,
                                        'pemainEditFrom' => $pemainEditFrom ?? 'index',
                                        'showPartnerActions' => $showPartnerActions,
                                        'regGroupMove' => $regGroupMove,
                                        'regGroupRemove' => $regGroupRemove,
                                    ])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 8 + ($showBulkActions ? 1 : 0) + ($showPartnerColumn ? 1 : 0) }}" class="text-center text-muted py-4">
                                    @if ($turnamen)
                                        Belum ada pemain terdaftar pada turnamen ini.
                                    @else
                                        Pilih turnamen untuk melihat daftar pemain.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @if ((! empty($isDoubleView) && $peserta->hasPages()) || (empty($isDoubleView) && $pemain->hasPages()))
        <div class="card-footer">
            {{ ! empty($isDoubleView) ? $peserta->links() : $pemain->links() }}
        </div>
    @endif
</div>

@if ($showPartnerActions ?? false)
    @include('admin.pemain.partials.set-partner-modal', [
        'soloPesertaOptions' => $soloPesertaOptions ?? collect(),
    ])
@endif

@if ($canEditRegistrationGroups)
    <div class="modal fade friendly-reg-modal" id="friendlyRegGroupModal" tabindex="-1" aria-labelledby="friendlyRegGroupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="friendlyRegGroupModalLabel">Pindah Grup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Pindahkan <strong id="friendly-reg-player-name">pemain</strong> ke grup yang masih ada slot kosong.
                    </p>
                    <label for="friendly-reg-target-group" class="form-label">Grup tujuan</label>
                    <select id="friendly-reg-target-group" class="form-select"></select>
                    <div class="form-text" id="friendly-reg-slots-hint"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btn-save-friendly-reg">
                        <i class="bi bi-check-lg me-1"></i> Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade friendly-reg-modal" id="friendlyRegRenameModal" tabindex="-1" aria-labelledby="friendlyRegRenameModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="friendlyRegRenameModalLabel">Ubah Nama Grup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label for="friendly-reg-rename-input" class="form-label">Nama grup</label>
                    <input type="text" class="form-control" id="friendly-reg-rename-input" maxlength="255" autocomplete="off">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btn-save-friendly-reg-rename">
                        <i class="bi bi-check-lg me-1"></i> Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="modal fade" id="buktiBayarModal" tabindex="-1" aria-labelledby="buktiBayarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="buktiBayarModalLabel">
                    <i class="bi bi-receipt me-2"></i>Bukti Pembayaran
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body text-center">
                <p id="bukti-bayar-modal-label" class="small text-muted mb-3"></p>
                <img id="bukti-bayar-image" src="" alt="Bukti pembayaran" class="img-fluid rounded border d-none">
                <iframe id="bukti-bayar-pdf" title="Bukti pembayaran PDF" class="d-none w-100 rounded border" style="height: 70vh;"></iframe>
                <div id="bukti-bayar-empty" class="text-muted py-4 d-none">
                    <i class="bi bi-file-earmark-x display-6 d-block mb-2"></i>
                    Belum ada bukti bayar yang diunggah.
                </div>
            </div>
            <div class="modal-footer">
                <a id="bukti-bayar-open-tab" href="#" target="_blank" rel="noopener" class="btn btn-outline-primary d-none">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Buka di Tab Baru
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
