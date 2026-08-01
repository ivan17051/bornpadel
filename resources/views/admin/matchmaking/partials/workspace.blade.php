@if ($turnamen)
    @php
        $unitLabel = $unitLabel ?? ($turnamen->playsAsPairs() ? 'pasangan' : 'pemain');
        $unitLabelTitle = ucfirst($unitLabel);
        $sideLabel = $turnamen->playsAsPairs() ? 'Pasangan' : 'Pemain';
        $isMahjong = $isMahjong ?? $turnamen->isMahjong();
        $isFriendly = $isFriendly ?? $turnamen->isFriendly();
        $groupingUnitCount = $groupingUnitCount ?? $approvedCount;
        $pairingSummary = $pairingSummary ?? null;
        $isPairingOpen = $turnamen->playsAsPairs() && $turnamen->isRegistrationOpen();
        $randomizesPartners = $turnamen->randomizesPartners();
        $requiresPairRegistration = $turnamen->requiresPairRegistration();
        $bracketUrl = $bracketUrl ?? route('admin.bracket.index', ['id_turnamen' => $turnamen->id]);
        $isKnockoutPhase = ! $isMahjong && ! $isFriendly && ($hasKnockoutBracket ?? false);
        $expandGroupsByDefault = $isMahjong || $isFriendly || ! $isKnockoutPhase;
        $expandKnockoutByDefault = $isKnockoutPhase;
        $groupsEditable = ! $isMahjong && ($canEditGroups ?? false);
    @endphp
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center row">
            <div class="col-md-6">
                <h5 class="card-title mb-0">{{ $turnamen->nama }}</h5>
            </div>
            <div class="col-md-6 text-end">
                <span class="badge text-bg-light text-dark border">{{ $turnamen->jenis_label }}</span>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-8">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge bg-{{ $turnamen->status === 'open' ? 'success' : 'primary' }} fs-6">
                            Status: {{ strtoupper($turnamen->status) }}
                        </span>
                        <span class="badge text-bg-secondary fs-6">
                            @if ($isPairingOpen)
                                {{ $approvedCount }} pemain approved
                            @else
                                {{ $approvedCount }} {{ $unitLabel }} approved
                            @endif
                        </span>
                        @if ($isMahjong && ($mahjongIsFinal ?? false))
                            <span class="badge bg-warning text-dark fs-6">Grup Final</span>
                        @endif
                    </div>
                    <p class="text-muted mb-0 small">
                        @if ($turnamen->isRegistrationOpen())
                            @if ($isPairingOpen && $randomizesPartners && ($pairingSummary['odd_player_warning'] ?? false))
                                Pendaftaran masih dibuka. <strong class="text-danger">Jumlah pemain approved ganjil ({{ $pairingSummary['approved_solos'] ?? 0 }}).</strong>
                                Tolak satu pemain atau tambahkan pemain baru sebelum menutup pendaftaran.
                            @elseif ($isPairingOpen && $randomizesPartners)
                                Pendaftaran masih dibuka. Setiap peserta mendaftar individu; saat ditutup, sistem akan memasangkan {{ $pairingSummary['pairs_preview'] ?? 0 }} pasangan secara acak.
                            @elseif ($isPairingOpen && $requiresPairRegistration && ($pairingSummary['odd_player_warning'] ?? false))
                                Pendaftaran masih dibuka. <strong class="text-danger">Masih ada {{ $pairingSummary['approved_solos'] ?? 0 }} pemain approved tanpa pasangan.</strong>
                                Lengkapi pasangan sebelum menutup pendaftaran.
                            @elseif ($isPairingOpen && $requiresPairRegistration)
                                Pendaftaran masih dibuka. Pemain boleh daftar individu, lalu dipasangkan sebelum close — {{ $pairingSummary['complete_pairs'] ?? 0 }} pasangan lengkap saat ini.
                            @else
                                Pendaftaran masih dibuka. Tutup pendaftaran sebelum membuat grup.
                            @endif
                        @elseif ($isMahjong && ($mahjongIsFinal ?? false))
                            Grup final aktif. Input poin babak final per grup lalu selesaikan turnamen untuk menentukan juara.
                        @elseif ($isMahjong && $canReshuffle)
                            Grup Mahjong aktif. Input poin per grup, reshuffle kapan saja, atau lanjut ke babak berikutnya.
                        @elseif ($isFriendly && ($canAddFriendlyMatch ?? false))
                            Grup Group Match aktif. Slot pertandingan antar grup sudah dibuat — isi pasangan (2 pemain) per sisi, atau tambah tanding ekstra.
                        @elseif ($isFriendly && $grup->isNotEmpty() && ($friendlyUnassigned ?? collect())->isNotEmpty())
                            Susun pemain ke grup secara manual, atau acak hanya pemain yang belum digrup.
                        @elseif ($isFriendly && $grup->isNotEmpty())
                            Grup Group Match sudah dibuat. Klasemen dihitung dari hasil tanding antar grup.
                        @elseif ($canRandomGrup && $isFriendly)
                            Pendaftaran ditutup. Buat kerangka grup dulu (susun manual), atau isi semua sekaligus secara acak/rating. Slot pertandingan dibuat otomatis saat semua grup penuh.
                        @elseif ($canRandomGrup && $isMahjong)
                            Pendaftaran ditutup. Buat grup Mahjong (4 pemain per grup, jumlah approved harus kelipatan 4).
                        @elseif ($groupsEditable)
                            Grup sudah dibuat dan masih dapat diubah. Klik {{ $unitLabel }} di daftar anggota untuk menukar, atau buka "Random Grup" untuk acak ulang, lalu klik "Buat Matchmaking" untuk mengunci grup dan membuat jadwal.
                        @elseif ($canRandomGrup)
                            Pendaftaran ditutup. Klik "Random Grup" untuk mengatur min/max dan membagi {{ $unitLabel }}.
                        @elseif ($hasKnockoutBracket)
                            Fase grup selesai. Bracket knockout sudah dibuat.
                        @elseif ($canEndGroupStage && ! $isMahjong && ! $isFriendly)
                            Semua pertandingan fase grup selesai. Klik "End Group Stage" untuk membuat bracket.
                        @elseif ($grup->isNotEmpty())
                            {{ $isMahjong ? 'Grup Mahjong sudah dibuat.' : ($isFriendly ? 'Grup Group Match sudah dibuat.' : 'Matchmaking fase grup sudah dibuat dan susunan grup dikunci.') }}
                        @else
                            Turnamen tidak siap untuk matchmaking.
                        @endif
                    </p>
                </div>
                <div class="col-md-4">
                    @if ($canRandomGrup && ($isMahjong || $isFriendly))
                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase small mb-2">{{ $isFriendly ? 'Group Match' : 'Mahjong' }}</h6>
                                <div id="group-split-preview"
                                     class="small text-muted"
                                     data-approved="{{ $approvedCount }}"
                                     data-mahjong="{{ $isMahjong ? '1' : '0' }}"
                                     data-friendly="{{ $isFriendly ? '1' : '0' }}">
                                    @if ($groupSplitPreview)
                                        {{ $approvedCount }} pemain → {{ $groupSplitPreview['group_count'] }} grup ({{ $groupSplitPreview['label'] }})
                                    @else
                                        Jumlah pemain approved harus minimal {{ $isFriendly ? '8' : '4' }} dan kelipatan 4.
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                    @if ($isPairingOpen && $randomizesPartners)
                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase small mb-2">Pemasangan Otomatis</h6>
                                <ul class="small text-muted mb-0 ps-3">
                                    <li>{{ $pairingSummary['approved_individuals'] ?? 0 }} pemain approved</li>
                                    <li>{{ $pairingSummary['approved_solos'] ?? 0 }} pemain belum berpasangan</li>
                                    <li>{{ $pairingSummary['pairs_preview'] ?? 0 }} pasangan akan dibuat saat pendaftaran ditutup</li>
                                    @if ($pairingSummary['odd_player_warning'] ?? false)
                                        <li class="text-danger">Jumlah pemain approved ganjil, mohon perbaiki data sebelum menutup pendaftaran</li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    @elseif ($isPairingOpen && $requiresPairRegistration)
                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase small mb-2">Status Pasangan</h6>
                                <ul class="small text-muted mb-0 ps-3">
                                    <li>{{ $pairingSummary['approved_individuals'] ?? 0 }} pemain approved</li>
                                    <li>{{ $pairingSummary['complete_pairs'] ?? 0 }} pasangan lengkap</li>
                                    <li>{{ $pairingSummary['approved_solos'] ?? 0 }} pemain tanpa pasangan</li>
                                    @if ($pairingSummary['odd_player_warning'] ?? false)
                                        <li class="text-danger">Semua pemain approved harus berpasangan sebelum menutup pendaftaran</li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    @elseif ($turnamen->playsAsPairs() && ($pairingSummary['is_paired'] ?? false))
                        <div class="alert alert-success py-2 small mb-3">
                            <i class="bi bi-check-circle me-1"></i>
                            Pemasangan selesai — {{ $approvedCount }} pasangan siap untuk pembagian grup.
                        </div>
                    @endif
                    <div class="d-grid gap-2">
                        @if ($turnamen->isRegistrationOpen())
                        <button type="button"
                                id="btn-close-registration"
                                class="btn btn-warning"
                                data-url="{{ route('admin.matchmaking.close-registration') }}"
                                data-turnamen="{{ $turnamen->id }}"
                                data-randomize-partners="{{ $randomizesPartners ? '1' : '0' }}"
                                data-pairs-preview="{{ $pairingSummary['pairs_preview'] ?? 0 }}"
                                @if (! $canCloseRegistration) disabled @endif>
                            <i class="bi bi-lock me-1"></i> Tutup Pendaftaran
                        </button>
                        @endif
                        @if ($canRandomGrup && ! $isMahjong && ! $isFriendly)
                            <button type="button"
                                    id="btn-open-random-grup-modal"
                                    class="btn btn-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#randomGrupModal">
                                <i class="bi bi-shuffle me-1"></i> Random Grup
                            </button>
                        @endif
                        @if ($canRandomGrup && ($isMahjong || $isFriendly))
                            @if ($isFriendly && ($canCreateFriendlySkeleton ?? false))
                                <button type="button"
                                        class="btn btn-outline-primary btn-friendly-skeleton"
                                        data-url="{{ route('admin.matchmaking.friendly.skeleton') }}"
                                        data-turnamen="{{ $turnamen->id }}">
                                    <i class="bi bi-grid-3x3-gap me-1"></i> Buat Kerangka Grup
                                </button>
                            @endif
                            @if ($isMahjong || ($isFriendly && $grup->isEmpty()))
                                <button type="button"
                                        class="btn btn-primary btn-matchmaking-grup"
                                        data-url="{{ route('admin.matchmaking.random-grup') }}"
                                        data-turnamen="{{ $turnamen->id }}"
                                        data-mode="random"
                                        data-mahjong="{{ $isMahjong ? '1' : '0' }}"
                                        data-friendly="{{ $isFriendly ? '1' : '0' }}">
                                    <i class="bi bi-shuffle me-1"></i> {{ $isFriendly ? 'Isi Semua Acak' : 'Buat Grup' }}
                                </button>
                                <button type="button"
                                        class="btn btn-secondary btn-matchmaking-grup"
                                        data-url="{{ route('admin.matchmaking.random-grup') }}"
                                        data-turnamen="{{ $turnamen->id }}"
                                        data-mode="by_rating"
                                        data-mahjong="{{ $isMahjong ? '1' : '0' }}"
                                        data-friendly="{{ $isFriendly ? '1' : '0' }}">
                                    <i class="bi bi-bar-chart-steps me-1"></i> {{ $isFriendly ? 'Isi Semua by Rating' : 'Grup by Rating' }}
                                </button>
                            @elseif ($isFriendly && ($canRandomizeFriendlyUnassigned ?? false))
                                <button type="button"
                                        class="btn btn-primary btn-matchmaking-grup"
                                        data-url="{{ route('admin.matchmaking.random-grup') }}"
                                        data-turnamen="{{ $turnamen->id }}"
                                        data-mode="random"
                                        data-mahjong="0"
                                        data-friendly="1">
                                    <i class="bi bi-shuffle me-1"></i> Acak Sisa
                                </button>
                                <button type="button"
                                        class="btn btn-secondary btn-matchmaking-grup"
                                        data-url="{{ route('admin.matchmaking.random-grup') }}"
                                        data-turnamen="{{ $turnamen->id }}"
                                        data-mode="by_rating"
                                        data-mahjong="0"
                                        data-friendly="1">
                                    <i class="bi bi-bar-chart-steps me-1"></i> Acak Sisa by Rating
                                </button>
                            @endif
                        @endif
                        @if (($canGenerateGroupMatches ?? false) && ! $isFriendly)
                            <button type="button"
                                    id="btn-generate-group-matches"
                                    class="btn btn-success"
                                    data-url="{{ route('admin.matchmaking.generate-group-matches') }}"
                                    data-turnamen="{{ $turnamen->id }}">
                                <i class="bi bi-calendar2-check me-1"></i> Buat Matchmaking
                            </button>
                        @endif
                        @if ($canResetGroupsAndMatches ?? false)
                            <button type="button"
                                    id="btn-reset-groups"
                                    class="btn btn-outline-danger"
                                    data-url="{{ route('admin.matchmaking.reset-groups') }}"
                                    data-turnamen="{{ $turnamen->id }}"
                                    data-mahjong="{{ $isMahjong ? '1' : '0' }}"
                                    data-friendly="{{ $isFriendly ? '1' : '0' }}">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Grup & Matchmaking
                            </button>
                        @endif
                        @if ($isMahjong && ($canReshuffle ?? false))
                            <button type="button"
                                    id="btn-reshuffle-groups"
                                    class="btn btn-outline-primary"
                                    data-url="{{ route('admin.matchmaking.reshuffle-groups') }}"
                                    data-turnamen="{{ $turnamen->id }}">
                                <i class="bi bi-arrow-repeat me-1"></i> Reshuffle Groups
                            </button>
                        @endif
                        @if (! $isFriendly)
                        <button type="button"
                                id="btn-end-group-stage"
                                class="btn btn-success {{ $canEndGroupStage ? '' : 'd-none' }}"
                                data-url="{{ route('admin.matchmaking.end-group-stage') }}"
                                data-turnamen="{{ $turnamen->id }}"
                                data-bracket-url="{{ $bracketUrl }}"
                                data-jenis="{{ $turnamen->jenis }}"
                                data-mahjong="{{ $isMahjong ? '1' : '0' }}"
                                data-max-lolos="{{ $activePlayerCount ?? $approvedCount }}"
                                data-group-count="{{ $grup->count() }}"
                                data-participant-count="{{ $grup->sum(fn ($g) => $g->members->count()) }}"
                                {{ $canEndGroupStage ? '' : 'd-none' }}>
                            <i class="bi bi-flag me-1"></i> {{ $isMahjong ? 'Akhiri Babak' : 'Akhiri Fase Grup' }}
                        </button>
                        @endif
                        @if ($canCompleteTournament ?? false)
                            <button type="button"
                                    id="btn-complete-tournament"
                                    class="btn btn-dark"
                                    data-url="{{ route('admin.matchmaking.complete-tournament') }}"
                                    data-turnamen="{{ $turnamen->id }}"
                                    data-pending-third-place="{{ ($hasPendingThirdPlacePlayoff ?? false) ? '1' : '0' }}">
                                <i class="bi bi-trophy me-1"></i> Selesaikan Turnamen
                            </button>
                        @endif
                        @if ($hasKnockoutBracket)
                            <a href="{{ $bracketUrl }}" class="btn btn-outline-success">
                                <i class="bi bi-diagram-2 me-1"></i> Lihat Bracket
                            </a>
                        @endif
                        @if ($canResetKnockoutBracket ?? false)
                            <button type="button"
                                    id="btn-reset-bracket"
                                    class="btn btn-outline-danger"
                                    data-url="{{ route('admin.matchmaking.reset-bracket') }}"
                                    data-turnamen="{{ $turnamen->id }}"
                                    data-has-scores="{{ ($hasKnockoutScores ?? false) ? '1' : '0' }}">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Bracket
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $showFriendlyRegistrationPanel = $isFriendly
            && ($turnamen->isRegistrationOpen() || $grup->isEmpty())
            && isset($friendlyRegistrationGroups);
        $canBulkApproveFriendly = auth()->user()->isAdmin();
        $turnamenOngoing = $turnamen->status === 'ongoing';
    @endphp

    @if ($showFriendlyRegistrationPanel)
        <div class="card mb-4 pemain-table-card"
             data-turnamen-id="{{ $turnamen->id }}"
             data-bulk-approve-url="{{ route('admin.peserta.bulk-approve') }}">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">
                    <i class="bi bi-people me-1"></i> Pendaftaran Pemain
                </h5>
                <div class="d-flex align-items-center gap-2">
                    @if ($canBulkApproveFriendly)
                        <button type="button"
                                class="btn btn-success btn-sm btn-bulk-approve"
                                disabled
                                title="Pilih peserta terlebih dahulu">
                            <i class="bi bi-check-all me-1"></i> Setujui Terpilih
                        </button>
                    @endif
                    <a href="{{ route('admin.pemain.index', ['id_turnamen' => $turnamen->id]) }}"
                       class="btn btn-outline-secondary btn-sm">
                        Kelola Pemain
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                @if ($canBulkApproveFriendly)
                                    <th style="width: 2.5rem;">
                                        <input type="checkbox" class="form-check-input select-all-approvable" title="Pilih semua yang dapat disetujui">
                                    </th>
                                @endif
                                <th style="width: 3.5rem;"></th>
                                <th>Nama</th>
                                <th class="d-none d-md-table-cell">No. HP</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($friendlyRegistrationGroups as $regGroup)
                                <tr class="table-secondary">
                                    <td colspan="{{ $canBulkApproveFriendly ? 6 : 5 }}" class="small fw-semibold py-2">
                                        <i class="bi bi-{{ ($regGroup['is_solo_bucket'] ?? false) ? 'person' : 'people' }} me-1"></i>
                                        {{ $regGroup['nama'] }}
                                        @if (! ($regGroup['is_solo_bucket'] ?? false))
                                            <span class="badge text-bg-light text-dark border ms-1">
                                                {{ ($regGroup['members'] ?? collect())->count() }}/4
                                            </span>
                                            @if ($regGroup['is_complete'] ?? false)
                                                <span class="badge bg-success ms-1">Siap materialisasi</span>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                                @forelse ($regGroup['members'] ?? [] as $pesertaRow)
                                    @php
                                        $item = $pesertaRow->pemain1;
                                        $registrationStatus = $pesertaRow->status;
                                        $canBulkApprove = $canBulkApproveFriendly
                                            && in_array($registrationStatus, ['pending', 'unpaid', 'paid', 'rejected'], true);
                                    @endphp
                                    @if ($item)
                                        <tr data-pemain-id="{{ $item->id }}" data-peserta-id="{{ $pesertaRow->id }}">
                                            @if ($canBulkApproveFriendly)
                                                <td>
                                                    @if ($canBulkApprove)
                                                        <input type="checkbox"
                                                               class="form-check-input peserta-bulk-checkbox"
                                                               value="{{ $pesertaRow->id }}"
                                                               data-peserta-id="{{ $pesertaRow->id }}">
                                                    @endif
                                                </td>
                                            @endif
                                            <td><x-pemain-avatar :pemain="$item" :size="36" /></td>
                                            <td>
                                                <strong>
                                                    <x-pemain-link :pemain="$item" class="text-decoration-none text-dark" />
                                                </strong>
                                            </td>
                                            <td class="d-none d-md-table-cell">{{ $item->no_hp }}</td>
                                            <td>
                                                <span class="badge status-badge-{{ $registrationStatus }}" data-status-cell>
                                                    {{ ucfirst($registrationStatus) }}
                                                </span>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                @include('admin.pemain.partials.pemain-row-actions', [
                                                    'pemain' => $item,
                                                    'peserta' => $pesertaRow,
                                                    'turnamen' => $turnamen,
                                                    'registrationStatus' => $registrationStatus,
                                                    'turnamenOngoing' => $turnamenOngoing,
                                                    'pemainEditFrom' => 'matchmaking',
                                                    'showPartnerActions' => false,
                                                ])
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="{{ $canBulkApproveFriendly ? 6 : 5 }}" class="text-muted small py-3 text-center">
                                            Belum ada pemain di kelompok ini.
                                        </td>
                                    </tr>
                                @endforelse
                            @empty
                                <tr>
                                    <td colspan="{{ $canBulkApproveFriendly ? 6 : 5 }}" class="text-center text-muted py-4">
                                        Belum ada pemain terdaftar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($grup->isNotEmpty())
        @php
            $friendlyUnassignedOptions = ($isFriendly ? ($friendlyUnassigned ?? collect()) : collect())->map(function ($peserta) {
                $pemain = $peserta->pemain1;
                $nama = optional($pemain)->nama ?? 'Pemain';
                $rating = number_format((float) optional($pemain)->rating, 1);

                return [
                    'id' => $peserta->id,
                    'text' => "{$nama} (Rating {$rating})",
                ];
            })->values();
        @endphp
        <div class="accordion matchmaking-groups-accordion mb-3" id="matchmaking-groups-accordion"
             @if ($groupsEditable)
                 data-group-swap="1"
                 data-swap-url="{{ route('admin.matchmaking.swap-group-members') }}"
                 data-turnamen="{{ $turnamen->id }}"
                 data-unit-label="{{ $unitLabel }}"
                 @if ($isFriendly)
                     data-friendly-edit="1"
                     data-assign-url="{{ route('admin.matchmaking.friendly.assign') }}"
                     data-unassigned='@json($friendlyUnassignedOptions)'
                     data-rename-url-template="{{ route('admin.matchmaking.grup.rename', ['grup' => '__ID__']) }}"
                     data-unassign-url-template="{{ route('admin.matchmaking.friendly.unassign', ['member' => '__ID__']) }}"
                 @endif
             @endif>
        @if ($isMahjong)
            @foreach ($grup as $g)
                <div class="accordion-item">
                    <h2 class="accordion-header d-flex align-items-stretch" id="group-heading-{{ $g->id }}">
                        <button class="accordion-button {{ $expandGroupsByDefault ? '' : 'collapsed' }}"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#group-collapse-{{ $g->id }}"
                                aria-expanded="{{ $expandGroupsByDefault ? 'true' : 'false' }}"
                                aria-controls="group-collapse-{{ $g->id }}">
                            <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                <span>
                                    <i class="bi bi-diagram-3 me-1"></i>{{ $g->nama }}
                                    @if ($g->babak)
                                        <small class="text-muted fw-normal">— Babak {{ $g->babak }}</small>
                                    @endif
                                </span>
                                <span class="badge text-bg-info ms-auto">{{ $g->members->count() }} pemain</span>
                            </span>
                        </button>
                        <div class="friendly-grup-header-actions d-flex align-items-center gap-1 px-2">
                            <button type="button"
                                    class="btn btn-sm btn-primary btn-mahjong-input-poin"
                                    data-grup-id="{{ $g->id }}"
                                    data-grup-name="{{ $g->nama }}"
                                    data-url="{{ route('admin.matchmaking.mahjong-group-point-entries.store', $g) }}"
                                    data-members='@json($g->members->map(fn ($m) => ["id" => $m->id, "name" => $m->display_name])->values())'
                                    title="Input poin untuk semua pemain di grup">
                                <i class="bi bi-pencil-square me-1"></i>Input Poin
                            </button>
                        </div>
                    </h2>
                    <div id="group-collapse-{{ $g->id }}"
                         class="accordion-collapse collapse {{ $expandGroupsByDefault ? 'show' : '' }}"
                         aria-labelledby="group-heading-{{ $g->id }}">
                        <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Pemain</th>
                                        <th class="text-center" style="width:7rem">Akumulasi</th>
                                        <th class="text-center" style="width:14rem">Poin Babak</th>
                                        <th class="text-center" style="width:7rem">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($g->members as $member)
                                        @php
                                            $memberEntries = $member->relationLoaded('poinEntries')
                                                ? $member->poinEntries
                                                : $member->poinEntries()->get();
                                        @endphp
                                        <tr class="mahjong-member-row" data-member-id="{{ $member->id }}">
                                            <td class="fw-semibold">{{ $member->display_name }}</td>
                                            <td class="text-center text-muted mahjong-akumulasi" data-member-id="{{ $member->id }}">
                                                {{ (int) $member->poin_akumulasi }}
                                            </td>
                                            <td class="text-center">
                                                <span class="badge text-bg-info mahjong-poin-babak" data-member-id="{{ $member->id }}">
                                                    {{ (int) $member->poin_didapat }}
                                                </span>
                                                <div class="mahjong-poin-entries mt-1 d-flex flex-wrap justify-content-center gap-1"
                                                     data-member-id="{{ $member->id }}"
                                                     data-destroy-url-template="{{ route('admin.matchmaking.mahjong-point-entries.destroy', ['member' => $member->id, 'entry' => '__ENTRY__']) }}">
                                                    @foreach ($memberEntries as $entry)
                                                        <span class="badge text-bg-light text-dark border mahjong-poin-entry"
                                                              data-entry-id="{{ $entry->id }}">
                                                            {{ (int) $entry->poin > 0 ? '+' : '' }}{{ (int) $entry->poin }}
                                                            <button type="button"
                                                                    class="btn btn-link btn-sm p-0 ms-1 text-danger btn-delete-mahjong-poin"
                                                                    data-member-id="{{ $member->id }}"
                                                                    data-entry-id="{{ $entry->id }}"
                                                                    data-url="{{ route('admin.matchmaking.mahjong-point-entries.destroy', ['member' => $member->id, 'entry' => $entry->id]) }}"
                                                                    title="Hapus entri">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge text-bg-primary mahjong-total-poin" data-member-id="{{ $member->id }}">
                                                    {{ $member->total_poin }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            @foreach ($grup as $g)
                @php
                    $friendlySlotsRemaining = $isFriendly
                        ? max(0, 4 - $g->members->count())
                        : 0;
                    $canAssignFriendlyMembers = $isFriendly
                        && ($groupsEditable ?? false)
                        && $friendlySlotsRemaining > 0
                        && ($friendlyUnassigned ?? collect())->isNotEmpty();
                @endphp
                <div class="accordion-item"
                     @if ($isFriendly && ($groupsEditable ?? false))
                         data-friendly-grup="1"
                         data-grup-id="{{ $g->id }}"
                         data-grup-name="{{ $g->nama }}"
                         data-member-count="{{ $g->members->count() }}"
                         data-slots-remaining="{{ $friendlySlotsRemaining }}"
                     @endif>
                    <h2 class="accordion-header d-flex align-items-stretch" id="group-heading-{{ $g->id }}">
                        <button class="accordion-button {{ $expandGroupsByDefault ? '' : 'collapsed' }}"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#group-collapse-{{ $g->id }}"
                                aria-expanded="{{ $expandGroupsByDefault ? 'true' : 'false' }}"
                                aria-controls="group-collapse-{{ $g->id }}">
                            <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                <span class="d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-diagram-3 me-1"></i>
                                    <span class="group-name-label">{{ $g->nama }}</span>
                                </span>
                                @if ($isFriendly)
                                    <span class="badge text-bg-info ms-auto">
                                        {{ $g->members->count() }}/4 pemain
                                    </span>
                                    <span class="badge text-bg-secondary">
                                        Poin {{ (int) $g->poin_didapat }}
                                    </span>
                                @else
                                    <span class="badge text-bg-info ms-auto">
                                        {{ $g->members->count() }} {{ $unitLabel }}
                                        · {{ $g->pertandingan->count() }} pertandingan
                                    </span>
                                @endif
                            </span>
                        </button>
                        @if ($isFriendly && ($groupsEditable ?? false))
                            <div class="friendly-grup-header-actions d-flex align-items-center gap-1 px-2">
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary btn-rename-grup"
                                        data-grup-id="{{ $g->id }}"
                                        data-grup-name="{{ $g->nama }}"
                                        title="Ubah nama grup">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if ($canAssignFriendlyMembers)
                                    <button type="button"
                                            class="btn btn-sm btn-primary friendly-grup-assign-open"
                                            title="Tambah pemain ke grup">
                                        <i class="bi bi-person-plus me-1"></i>Tambah pemain
                                    </button>
                                @endif
                            </div>
                        @endif
                    </h2>
                    <div id="group-collapse-{{ $g->id }}"
                         class="accordion-collapse collapse {{ $expandGroupsByDefault ? 'show' : '' }}"
                         aria-labelledby="group-heading-{{ $g->id }}">
                        <div class="accordion-body">
                        <div class="row">
                            <div class="{{ $isFriendly ? 'col-12' : 'col-md-5 mb-3 mb-md-0' }}">
                                <h6 class="text-muted text-uppercase small">
                                    Anggota Grup
                                    @if ($groupsEditable && $isFriendly)
                                    @elseif ($groupsEditable)
                                        <span class="fw-normal text-lowercase">— klik untuk tukar</span>
                                    @endif
                                </h6>
                                <ul class="list-group list-group-flush">
                                    @forelse ($g->members as $member)
                                        @php
                                            $ratingLabel = $turnamen->playsAsPairs()
                                                ? number_format(optional($member->turnamenPeserta)->average_rating ?? 0, 1)
                                                : number_format(optional($member->pemain)->rating ?? 0, 1);
                                        @endphp
                                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center
                                                   {{ $groupsEditable ? 'group-member-swap-source' : '' }}"
                                            @if ($groupsEditable)
                                                role="button"
                                                tabindex="0"
                                                data-member-id="{{ $member->id }}"
                                                data-group-id="{{ $g->id }}"
                                                data-group-name="{{ $g->nama }}"
                                                data-label="{{ $member->display_name }}"
                                                title="Klik untuk menukar {{ $unitLabel }} ini"
                                            @endif>
                                            <span>
                                                @if ($groupsEditable)
                                                    <i class="bi bi-arrow-left-right me-1 text-primary"></i>
                                                @endif
                                                {{ $member->display_name }}
                                            </span>
                                            <span class="d-inline-flex align-items-center gap-2">
                                                <small class="text-muted">Rating {{ $ratingLabel }}</small>
                                                @if ($isFriendly && $groupsEditable)
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger btn-friendly-unassign"
                                                            data-member-id="{{ $member->id }}"
                                                            title="Lepas dari grup"
                                                            onclick="event.stopPropagation();">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                @endif
                                            </span>
                                        </li>
                                    @empty
                                        @if ($canAssignFriendlyMembers)
                                            <li class="list-group-item px-0 text-muted">
                                                Belum ada anggota.
                                                <button type="button"
                                                        class="btn btn-link btn-sm p-0 align-baseline friendly-grup-assign-open">
                                                    Tambah pemain
                                                </button>
                                            </li>
                                        @else
                                            <li class="list-group-item px-0 text-muted">Belum ada anggota.</li>
                                        @endif
                                    @endforelse
                                </ul>
                            </div>
                            @unless ($isFriendly)
                            <div class="col-md-7">
                                <h6 class="text-muted text-uppercase small">Jadwal Fase Grup (Round-Robin)</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>{{ $sideLabel }} 1</th>
                                                <th>vs</th>
                                                <th>{{ $sideLabel }} 2</th>
                                                <th>Skor</th>
                                                <th>Status</th>
                                                <th class="text-end">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($g->pertandingan as $match)
                                                <tr>
                                                    <td>@include('admin.pertandingan.partials.match-side-label', ['match' => $match, 'side' => 1])</td>
                                                    <td class="text-center">vs</td>
                                                    <td>@include('admin.pertandingan.partials.match-side-label', ['match' => $match, 'side' => 2])</td>
                                                    <td>
                                                        @if ($match->skor->isNotEmpty())
                                                            @foreach ($match->skor as $s)
                                                                <span class="badge text-bg-light text-dark border me-1">
                                                                    {{ $s->skor_pemain1 }}-{{ $s->skor_pemain2 }}
                                                                </span>
                                                            @endforeach
                                                            @if ($match->pemenang || $match->pesertaPemenang)
                                                                <i class="bi bi-trophy-fill text-warning ms-1" title="{{ $match->winner_label }}"></i>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-{{ $match->status === 'completed' ? 'success' : ($match->status === 'ongoing' ? 'warning' : 'secondary') }}">
                                                            {{ $match->status }}
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        @if ($match->status !== 'completed' && $match->isReadyForScoring())
                                                            <button type="button"
                                                                    class="btn btn-sm btn-primary btn-input-score"
                                                                    data-id="{{ $match->id }}"
                                                                    data-show-url="{{ route('admin.pertandingan.show', $match) }}"
                                                                    data-store-url="{{ route('admin.pertandingan.score', $match) }}">
                                                                <i class="bi bi-pencil-square me-1"></i> Input Skor
                                                            </button>
                                                        @elseif ($match->status !== 'completed')
                                                            <span class="badge text-bg-light text-dark border">Menunggu Pemain</span>
                                                        @elseif ($canEditGroupScores ?? false)
                                                            <button type="button"
                                                                    class="btn btn-sm btn-outline-primary btn-input-score"
                                                                    data-id="{{ $match->id }}"
                                                                    data-show-url="{{ route('admin.pertandingan.show', $match) }}"
                                                                    data-store-url="{{ route('admin.pertandingan.score', $match) }}">
                                                                <i class="bi bi-pencil-square me-1"></i> Edit Skor
                                                            </button>
                                                        @else
                                                            <button type="button"
                                                                    class="btn btn-sm btn-outline-secondary btn-view-score"
                                                                    data-show-url="{{ route('admin.pertandingan.show', $match) }}">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-3">
                                                        Jadwal belum dibuat. Susun grup lalu klik "Buat Matchmaking".
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @endunless
                        </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
        </div>
    @endif

    @if ($isFriendly && $grup->isNotEmpty())
        @include('admin.matchmaking.partials.friendly-matches')
    @endif

    @if ($isFriendly && ($groupsEditable ?? false) && $grup->isNotEmpty())
        <div class="modal fade" id="friendlyAssignModal" tabindex="-1" aria-labelledby="friendlyAssignModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="friendlyAssignModalLabel">Tambah Pemain ke Grup</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3" id="friendly-assign-help">
                            Pilih satu atau beberapa pemain yang belum digrup.
                        </p>
                        <label for="friendly-assign-peserta" class="form-label">Pemain</label>
                        <select id="friendly-assign-peserta" class="form-select" multiple></select>
                        <div class="form-text" id="friendly-assign-slots-hint"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="btn-save-friendly-assign">
                            <i class="bi bi-check-lg me-1"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (! ($isMahjong ?? false) && ! ($isFriendly ?? false) && ! empty($knockoutRounds) && collect($knockoutRounds)->isNotEmpty())
        <div class="accordion matchmaking-knockout-accordion mb-3" id="matchmaking-knockout-accordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="knockout-heading">
                    <button class="accordion-button {{ $expandKnockoutByDefault ? '' : 'collapsed' }}"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#knockout-collapse"
                            aria-expanded="{{ $expandKnockoutByDefault ? 'true' : 'false' }}"
                            aria-controls="knockout-collapse">
                        <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                            <span><i class="bi bi-diagram-2 me-1"></i>Pertandingan Knockout</span>
                            <a href="{{ $bracketUrl }}"
                               class="btn btn-sm btn-outline-success ms-auto me-1"
                               onclick="event.stopPropagation();">
                                <i class="bi bi-diagram-2 me-1"></i> Lihat Bracket
                            </a>
                        </span>
                    </button>
                </h2>
                <div id="knockout-collapse"
                     class="accordion-collapse collapse {{ $expandKnockoutByDefault ? 'show' : '' }}"
                     aria-labelledby="knockout-heading">
                    <div class="accordion-body">
                @foreach ($knockoutRounds as $round)
                    <h6 class="text-muted text-uppercase small {{ $loop->first ? '' : 'mt-4' }}">{{ $round['nama_ronde'] }}</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ $sideLabel }} 1</th>
                                    <th>vs</th>
                                    <th>{{ $sideLabel }} 2</th>
                                    <th>Skor</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($round['matches'] as $match)
                                    <tr>
                                        <td>@include('admin.pertandingan.partials.match-side-label', ['match' => $match, 'side' => 1])</td>
                                        <td class="text-center">vs</td>
                                        <td>@include('admin.pertandingan.partials.match-side-label', ['match' => $match, 'side' => 2])</td>
                                        <td>
                                            @if ($match->skor->isNotEmpty())
                                                @foreach ($match->skor as $s)
                                                    <span class="badge text-bg-light text-dark border me-1">
                                                        {{ $s->skor_pemain1 }}-{{ $s->skor_pemain2 }}
                                                    </span>
                                                @endforeach
                                                @if ($match->pemenang || $match->pesertaPemenang)
                                                    <i class="bi bi-trophy-fill text-warning ms-1" title="{{ $match->winner_label }}"></i>
                                                @endif
                                            @elseif ($match->status === 'completed' && ($match->pemenang || $match->pesertaPemenang))
                                                <i class="bi bi-fast-forward-fill text-muted" title="BYE — {{ $match->winner_label }}"></i>
                                                <span class="text-muted small">BYE</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $match->status === 'completed' ? 'success' : ($match->status === 'ongoing' ? 'warning' : 'secondary') }}">
                                                {{ $match->status }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @if ($match->status !== 'completed' && $match->isReadyForScoring())
                                                <button type="button"
                                                        class="btn btn-sm btn-primary btn-input-score"
                                                        data-id="{{ $match->id }}"
                                                        data-show-url="{{ route('admin.pertandingan.show', $match) }}"
                                                        data-store-url="{{ route('admin.pertandingan.score', $match) }}">
                                                    <i class="bi bi-pencil-square me-1"></i> Input Skor
                                                </button>
                                            @elseif ($match->status !== 'completed')
                                                <span class="badge text-bg-light text-dark border">Menunggu Pemain</span>
                                            @elseif ($match->skor->isNotEmpty() && ($match->can_edit_score ?? false))
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-primary btn-input-score"
                                                        data-id="{{ $match->id }}"
                                                        data-show-url="{{ route('admin.pertandingan.show', $match) }}"
                                                        data-store-url="{{ route('admin.pertandingan.score', $match) }}">
                                                    <i class="bi bi-pencil-square me-1"></i> Edit Skor
                                                </button>
                                            @elseif ($match->skor->isNotEmpty())
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-secondary btn-view-score"
                                                        data-show-url="{{ route('admin.pertandingan.show', $match) }}">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            @else
                                                <span class="badge text-bg-light text-dark border">BYE</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif

@once
@push('styles')
<style>
    .matchmaking-groups-accordion .accordion-button,
    .matchmaking-knockout-accordion .accordion-button {
        font-weight: 600;
        padding-top: 0.85rem;
        padding-bottom: 0.85rem;
    }
    .matchmaking-groups-accordion .accordion-header.d-flex .accordion-button {
        flex: 1 1 auto;
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }
    .matchmaking-groups-accordion .friendly-grup-header-actions {
        flex: 0 0 auto;
        background-color: var(--bs-accordion-btn-bg, #fff);
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    }
    .matchmaking-groups-accordion .accordion-header:has(.accordion-button:not(.collapsed)) .friendly-grup-header-actions {
        background-color: #f8f9fa;
    }
    .matchmaking-groups-accordion .accordion-item:first-of-type .friendly-grup-header-actions {
        border-top-right-radius: var(--bs-accordion-border-radius, 0.375rem);
    }
    .matchmaking-groups-accordion .accordion-button:not(.collapsed),
    .matchmaking-knockout-accordion .accordion-button:not(.collapsed) {
        background-color: #f8f9fa;
        color: inherit;
        box-shadow: none;
    }
    .matchmaking-knockout-accordion .accordion-button .btn {
        font-weight: 500;
    }
    .group-member-swap-source {
        cursor: pointer;
        border-radius: 0.375rem;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        transition: background-color 0.15s ease;
    }
    .group-member-swap-source:hover,
    .group-member-swap-source:focus {
        background-color: rgba(13, 110, 253, 0.08);
        outline: none;
    }
</style>
@endpush
@endonce

@if ($turnamen ?? null)
    @if (($canRandomGrup ?? false) && ! ($isMahjong ?? false))
        <div class="modal fade" id="randomGrupModal" tabindex="-1" aria-labelledby="randomGrupModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="randomGrupModalLabel">
                            <i class="bi bi-shuffle me-1"></i> Random Grup
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase small mb-3">Pengaturan Grup</h6>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="min-pemain-grup" class="form-label small mb-1">Min / grup</label>
                                        <input type="number"
                                               id="min-pemain-grup"
                                               class="form-control form-control-sm"
                                               min="2"
                                               max="12"
                                               value="{{ $defaultMinPerGroup }}">
                                    </div>
                                    <div class="col-6">
                                        <label for="max-pemain-grup" class="form-label small mb-1">Max / grup</label>
                                        <input type="number"
                                               id="max-pemain-grup"
                                               class="form-control form-control-sm"
                                               min="2"
                                               max="12"
                                               value="{{ $defaultMaxPerGroup }}">
                                    </div>
                                </div>
                                <div id="group-split-preview"
                                     class="small text-muted"
                                     data-approved="{{ $groupingUnitCount }}">
                                    @if ($groupSplitPreview)
                                        {{ $groupingUnitCount }} {{ $unitLabel }} → {{ $groupSplitPreview['group_count'] }} grup ({{ $groupSplitPreview['label'] }})
                                    @else
                                        {{ ucfirst($unitLabel) }} tidak cukup untuk pembagian grup dengan batas ini.
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button"
                                    class="btn btn-primary btn-matchmaking-grup"
                                    data-url="{{ route('admin.matchmaking.random-grup') }}"
                                    data-turnamen="{{ $turnamen->id }}"
                                    data-mode="random"
                                    data-mahjong="0">
                                <i class="bi bi-shuffle me-1"></i> Random Grup
                            </button>
                            <button type="button"
                                    class="btn btn-secondary btn-matchmaking-grup"
                                    data-url="{{ route('admin.matchmaking.random-grup') }}"
                                    data-turnamen="{{ $turnamen->id }}"
                                    data-mode="by_rating"
                                    data-mahjong="0">
                                <i class="bi bi-bar-chart-steps me-1"></i> Grup by Rating
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="modal fade" id="endGroupStageModal" tabindex="-1" aria-labelledby="endGroupStageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="endGroupStageModalLabel">
                        {{ ($isMahjong ?? false) ? 'Akhiri Babak' : 'Akhiri Fase Grup' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    @if ($isMahjong ?? false)
                        <p class="text-muted small">
                            Berapa banyak pemain untuk diloloskan ke babak selanjutnya?
                            Sistem akan mengambil pemain dengan total poin tertinggi dan membagi ulang ke grup berisi 4 pemain.
                            Jumlah lolos harus kelipatan 4, atau tepat 4 untuk grup final.
                        </p>
                        <div class="mb-0">
                            <label for="jumlah-lolos-input" class="form-label">Jumlah pemain lolos</label>
                            <input type="number"
                                   id="jumlah-lolos-input"
                                   class="form-control"
                                   min="4"
                                   max="{{ $activePlayerCount ?? $approvedCount }}"
                                   step="4"
                                   value="{{ min(max(4, ($activePlayerCount ?? $approvedCount) >= 8 ? 8 : 4), $activePlayerCount ?? $approvedCount) }}"
                                   required>
                        </div>
                    @else
                        @php
                            $unit = $turnamen->playsAsPairs() ? 'pasangan' : 'pemain';
                            $groupCount = $grup->count();
                            $participantCount = $grup->sum(fn ($g) => $g->members->count());
                            $defaultTotal = min(
                                $participantCount,
                                max($groupCount, (int) (2 ** floor(log(max(2, $participantCount), 2))))
                            );
                        @endphp
                        <p class="text-muted small mb-3">
                            Pilih cara menentukan {{ $unit }} yang lolos ke knockout.
                            Sistem memberikan <strong>BYE</strong> otomatis jika jumlah lolos bukan pangkat dua.
                        </p>

                        <div class="mb-3">
                            <label class="form-label d-block">Mode kualifikasi</label>
                            <div class="btn-group w-100" role="group" aria-label="Mode kualifikasi">
                                <input type="radio" class="btn-check" name="qualification_mode" id="qualification-mode-per-group" value="per_group" checked autocomplete="off">
                                <label class="btn btn-outline-primary" for="qualification-mode-per-group">Per grup</label>

                                <input type="radio" class="btn-check" name="qualification_mode" id="qualification-mode-total" value="total" autocomplete="off">
                                <label class="btn btn-outline-primary" for="qualification-mode-total">Total lolos</label>
                            </div>
                        </div>

                        <div class="mb-2" id="jumlah-lolos-per-group-wrap">
                            <label for="jumlah-lolos-input" class="form-label">
                                Jumlah {{ $unit }} lolos per grup
                            </label>
                            <input type="number"
                                   id="jumlah-lolos-input"
                                   class="form-control"
                                   min="1"
                                   max="8"
                                   value="2"
                                   required>
                            <div class="form-text">Contoh: 2 = juara 1 &amp; 2 tiap grup.</div>
                        </div>

                        <div class="mb-2 d-none" id="jumlah-lolos-total-wrap">
                            <label for="jumlah-lolos-total-input" class="form-label">
                                Total {{ $unit }} lolos ke knockout
                            </label>
                            <input type="number"
                                   id="jumlah-lolos-total-input"
                                   class="form-control"
                                   min="{{ max(2, $groupCount) }}"
                                   max="{{ max(2, $participantCount) }}"
                                   value="{{ $defaultTotal }}"
                                   data-group-count="{{ $groupCount }}"
                                   data-participant-count="{{ $participantCount }}">
                            <div class="form-text" id="jumlah-lolos-total-preview"></div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" id="btn-confirm-end-group-stage">
                        <i class="bi bi-flag me-1"></i> {{ ($isMahjong ?? false) ? 'Lanjutkan Babak' : 'Buat Bracket' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if ($groupsEditable)
        <div class="modal fade" id="groupMemberSwapModal" tabindex="-1" aria-labelledby="groupMemberSwapModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="groupMemberSwapModalLabel">
                            <i class="bi bi-arrow-left-right me-1"></i> Tukar {{ $unitLabelTitle }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-3">
                            Tukar <strong id="group-swap-source-label"></strong>
                            (<span class="text-muted" id="group-swap-source-group"></span>
                            dengan {{ $unitLabel }} dari grup lain:
                        </p>
                        <div id="group-swap-list" class="list-group list-group-flush"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($isMahjong ?? false)
        <div class="modal fade" id="mahjongGroupPointsModal" tabindex="-1" aria-labelledby="mahjongGroupPointsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="mahjongGroupPointsModalLabel">
                            <i class="bi bi-pencil-square me-1"></i> Input Poin
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3" id="mahjong-group-points-help">
                            Isi poin untuk keempat pemain dalam grup, lalu simpan sekaligus.
                        </p>
                        <div id="mahjong-group-points-fields" class="d-grid gap-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="btn-save-mahjong-group-points">
                            <i class="bi bi-check-lg me-1"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (! ($isMahjong ?? false))
        @include('admin.pertandingan.partials.score-modal')
    @endif
@endif
