@php
    $pemainModel = isset($pemain) ? $pemain : null;
    $isEdit = (bool) $pemainModel;
    $selectedTurnamen = $selectedTurnamen ?? null;
    $isDouble = $selectedTurnamen && $selectedTurnamen->isDouble();
    $showForm = $showForm ?? $isEdit;
    $noHp = old('no_hp', $noHp ?? optional($pemainModel)->no_hp ?? '');
    $partnerNoHp = old('partner_no_hp', $partnerNoHp ?? '');
    $existingPemain = $existingPemain ?? ($noHp ? \App\Models\Pemain::where('no_hp', trim($noHp))->first() : null);
    $existingPartner = $existingPartner ?? null;
    $isExisting = (bool) $existingPemain;
    $isPartnerExisting = $isPartnerExisting ?? (bool) $existingPartner;
    $photoService = app(\App\Services\PemainPhotoService::class);
    $previewSrc = $existingPemain && $existingPemain->foto ? $photoService->url($existingPemain->foto) : null;
    $partnerPreviewSrc = $existingPartner && $existingPartner->foto ? $photoService->url($existingPartner->foto) : null;
    $hasPartnerErrors = $errors->hasAny([
        'partner_no_hp', 'partner_nama', 'partner_tgl_lahir', 'partner_gender', 'partner_rating', 'partner_foto',
    ]);
    $lookupTurnamenId = old('id_turnamen', request('id_turnamen'));
    $lookupTurnamen = $lookupTurnamenId ? \App\Models\Turnamen::find($lookupTurnamenId) : null;
    $lookupIsDouble = $lookupTurnamen && $lookupTurnamen->isDouble();
@endphp

@if (! $isEdit && ! $showForm)
    <div class="alert alert-light border mb-4">
        <i class="bi bi-search me-2"></i>
        Pilih turnamen dan masukkan nomor HP pemain. Untuk turnamen <strong>double</strong>, masukkan nomor HP pemain 1 dan pemain 2.
    </div>

    <form action="{{ route('admin.pemain.lookup') }}" method="POST" id="admin-pemain-lookup-form">
        @csrf
        <div class="mb-3">
            <label for="lookup_id_turnamen" class="form-label">Turnamen <span class="text-danger">*</span></label>
            <select name="id_turnamen" id="lookup_id_turnamen" class="form-select @error('id_turnamen') is-invalid @enderror" required>
                <option value="" disabled {{ $lookupTurnamenId ? '' : 'selected' }}>Pilih turnamen</option>
                @foreach ($turnamenList as $item)
                    <option value="{{ $item->id }}" data-jenis="{{ $item->jenis }}"
                        {{ (string) $lookupTurnamenId === (string) $item->id ? 'selected' : '' }}>
                        {{ $item->nama }} — {{ ucfirst($item->status) }} ({{ $item->jenis_label }})
                    </option>
                @endforeach
            </select>
            @error('id_turnamen')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="lookup_status" class="form-label">Status Pendaftaran <span class="text-danger">*</span></label>
            <select name="status" id="lookup_status" class="form-select" required>
                @foreach (['pending' => 'Pending', 'unpaid' => 'Unpaid', 'paid' => 'Paid', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                    <option value="{{ $value }}" {{ old('status', request('status', 'approved')) === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <x-phone-input name="no_hp"
                           id="lookup_no_hp"
                           label="Nomor HP / WhatsApp Pemain 1"
                           :value="old('no_hp')" />
        </div>

        <div class="mb-4 {{ $lookupIsDouble ? '' : 'd-none' }}" id="lookup-partner-hp-wrap">
            <x-phone-input name="partner_no_hp"
                           id="lookup_partner_no_hp"
                           label="Nomor HP / WhatsApp Pemain 2"
                           :value="old('partner_no_hp')"
                           :required="$lookupIsDouble" />
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-search me-1"></i> Cari & Lanjutkan
        </button>
    </form>

    @push('scripts')
    <script>
        (function () {
            const select = document.getElementById('lookup_id_turnamen');
            const wrap = document.getElementById('lookup-partner-hp-wrap');

            if (!select || !wrap) {
                return;
            }

            function togglePartnerField() {
                const option = select.options[select.selectedIndex];
                const isDouble = option && option.dataset.jenis === 'double';
                wrap.classList.toggle('d-none', !isDouble);
                const localInput = wrap.querySelector('[data-phone-local]');
                if (localInput) {
                    localInput.required = isDouble;
                    if (!isDouble) {
                        localInput.value = '';
                    }
                }
            }

            select.addEventListener('change', togglePartnerField);
            togglePartnerField();
        })();
    </script>
    @endpush
@elseif ($isEdit)
    @if ($showPhotoField ?? false)
        <x-pemain-photo-input input-id="admin-foto" preview-id="admin-foto-preview" label="Foto Pemain" :show-preview="false" />
    @endif

    @include('admin.pemain.partials.player-fields', [
        'prefix' => '',
        'labelPrefix' => 'Pemain',
        'existingPemain' => $pemainModel,
        'inputId' => 'edit-foto',
        'previewId' => 'edit-foto-preview',
        'phoneReadonly' => false,
        'phoneValue' => optional($pemainModel)->no_hp,
        'showPhoto' => false,
    ])
@else
    <input type="hidden" name="id_turnamen" value="{{ old('id_turnamen', $selectedTurnamen->id) }}">
    <input type="hidden" name="status" value="{{ old('status', request('status', 'approved')) }}">

    @if ($isDouble)
        <div class="alert alert-light border mb-3">
            <i class="bi bi-people me-2"></i>
            Lengkapi data pemain 1 dan pemain 2 melalui tab di bawah.
        </div>

        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $hasPartnerErrors ? '' : 'active' }}"
                        type="button"
                        data-bs-toggle="tab"
                        data-bs-target="#admin-player1-tab"
                        role="tab">
                    Pemain 1
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $hasPartnerErrors ? 'active' : '' }}"
                        type="button"
                        data-bs-toggle="tab"
                        data-bs-target="#admin-player2-tab"
                        role="tab">
                    Pemain 2
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade {{ $hasPartnerErrors ? '' : 'show active' }}" id="admin-player1-tab" role="tabpanel">
                @if ($isExisting)
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-person-check me-1"></i>
                        Data pemain 1 ditemukan. Periksa dan perbarui jika perlu.
                    </div>
                @else
                    <div class="alert alert-light border py-2 small mb-3">
                        <i class="bi bi-person-plus me-1"></i>
                        Pemain 1 belum terdaftar. Lengkapi data di bawah.
                    </div>
                @endif

                @include('admin.pemain.partials.player-fields', [
                    'prefix' => '',
                    'labelPrefix' => 'Pemain 1',
                    'existingPemain' => $existingPemain,
                    'previewSrc' => $previewSrc,
                    'inputId' => 'admin-foto',
                    'previewId' => 'admin-foto-preview',
                    'phoneReadonly' => true,
                    'phoneValue' => $noHp,
                ])
            </div>
            <div class="tab-pane fade {{ $hasPartnerErrors ? 'show active' : '' }}" id="admin-player2-tab" role="tabpanel">
                @if ($isPartnerExisting)
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-person-check me-1"></i>
                        Data pemain 2 ditemukan. Periksa dan perbarui jika perlu.
                    </div>
                @else
                    <div class="alert alert-light border py-2 small mb-3">
                        <i class="bi bi-person-plus me-1"></i>
                        Pemain 2 belum terdaftar. Lengkapi data di bawah.
                    </div>
                @endif

                @include('admin.pemain.partials.player-fields', [
                    'prefix' => 'partner_',
                    'labelPrefix' => 'Pemain 2',
                    'existingPemain' => $existingPartner,
                    'previewSrc' => $partnerPreviewSrc,
                    'inputId' => 'partner-foto',
                    'previewId' => 'partner-foto-preview',
                    'inputName' => 'partner_foto',
                    'phoneReadonly' => true,
                    'phoneValue' => $partnerNoHp,
                ])
            </div>
        </div>

        <div class="mb-3 mt-3">
            <label for="bukti_bayar_double" class="form-label">Bukti Pembayaran</label>
            <input type="file"
                   name="bukti_bayar"
                   id="bukti_bayar_double"
                   class="form-control @error('bukti_bayar') is-invalid @enderror"
                   accept="image/jpeg,image/png,image/webp,application/pdf">
            @error('bukti_bayar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @else
        @if ($isExisting)
            <div class="alert alert-info py-2 small mb-3">
                <i class="bi bi-person-check me-1"></i>
                Data pemain ditemukan. Periksa dan perbarui jika perlu.
            </div>
        @else
            <div class="alert alert-light border py-2 small mb-3">
                <i class="bi bi-person-plus me-1"></i>
                Nomor HP belum terdaftar. Lengkapi data di bawah.
            </div>
        @endif

        @include('admin.pemain.partials.player-fields', [
            'prefix' => '',
            'labelPrefix' => 'Pemain',
            'existingPemain' => $existingPemain,
            'previewSrc' => $previewSrc,
            'inputId' => 'admin-foto',
            'previewId' => 'admin-foto-preview',
            'phoneReadonly' => true,
            'phoneValue' => $noHp,
        ])

        <div class="mb-3 mt-3">
            <label for="bukti_bayar" class="form-label">Bukti Pembayaran</label>
            <input type="file"
                   name="bukti_bayar"
                   id="bukti_bayar"
                   class="form-control @error('bukti_bayar') is-invalid @enderror"
                   accept="image/jpeg,image/png,image/webp,application/pdf">
            @error('bukti_bayar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif
@endif
