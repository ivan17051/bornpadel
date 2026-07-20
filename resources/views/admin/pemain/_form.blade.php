@php
    $pemainModel = isset($pemain) ? $pemain : null;
    $isEdit = (bool) $pemainModel;
    $selectedTurnamen = $selectedTurnamen ?? null;
    $showForm = $showForm ?? $isEdit;
    $noHp = old('no_hp', $noHp ?? optional($pemainModel)->no_hp ?? '');
    $existingPemain = $existingPemain ?? ($noHp ? \App\Models\Pemain::where('no_hp', trim($noHp))->first() : null);
    $isExisting = (bool) $existingPemain;
    $photoService = app(\App\Services\PemainPhotoService::class);
    $previewSrc = $existingPemain && $existingPemain->foto ? $photoService->url($existingPemain->foto) : null;
    $lookupTurnamenId = old('id_turnamen', request('id_turnamen'));
    $lookupTurnamen = $lookupTurnamenId ? \App\Models\Turnamen::find($lookupTurnamenId) : null;
@endphp

@if (! $isEdit && ! $showForm)
    <div class="alert alert-light border mb-4">
        <i class="bi bi-search me-2"></i>
        Pilih turnamen dan masukkan nomor HP pemain.
        @if ($lookupTurnamen && $lookupTurnamen->isDouble())
            <span class="d-block mt-1 small">Turnamen double: daftarkan satu pemain per kali. Pasangan dibuat otomatis saat pendaftaran ditutup.</span>
        @endif
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

        <div class="mb-4">
            <x-phone-input name="no_hp"
                           id="lookup_no_hp"
                           label="Nomor HP / WhatsApp"
                           :value="old('no_hp')" />
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-search me-1"></i> Cari & Lanjutkan
        </button>
    </form>
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

    @if ($selectedTurnamen && $selectedTurnamen->isDouble())
        <div class="alert alert-light border mb-3">
            <i class="bi bi-shuffle me-2"></i>
            Pemain ini akan dipasangkan secara acak dengan peserta lain setelah pendaftaran ditutup.
        </div>
    @endif

    @if ($isExisting)
        @include('admin.pemain.partials.existing-profile-banner', [
            'pemain' => $existingPemain,
            'exceptTurnamenId' => optional($selectedTurnamen)->id,
        ])
    @else
        <div class="alert alert-light border py-2 small mb-3">
            <i class="bi bi-person-plus me-1"></i>
            Nomor HP belum terdaftar. Lengkapi data di bawah untuk membuat profil baru.
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
        'lockUntilEdit' => $isExisting,
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
