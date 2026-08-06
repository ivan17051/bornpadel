@php
    $turnamenModel = isset($turnamen) ? $turnamen : null;
@endphp

<div class="mb-3">
    <label for="nama" class="form-label">Nama Turnamen <span class="text-danger">*</span></label>
    <input type="text"
           name="nama"
           id="nama"
           class="form-control @error('nama') is-invalid @enderror"
           value="{{ old('nama', optional($turnamenModel)->nama) }}"
           required>
    @error('nama')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="tanggal" class="form-label">Tanggal Turnamen <span class="text-danger">*</span></label>
    <input type="date"
           name="tanggal"
           id="tanggal"
           class="form-control @error('tanggal') is-invalid @enderror"
           value="{{ old('tanggal', optional(optional($turnamenModel)->tanggal)->format('Y-m-d')) }}"
           required>
    @error('tanggal')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="harga" class="form-label">Biaya Pendaftaran (Rp) <span class="text-danger">*</span></label>
    <input type="number"
           name="harga"
           id="harga"
           class="form-control @error('harga') is-invalid @enderror"
           value="{{ old('harga', optional($turnamenModel)->harga) }}"
           min="0"
           step="1000"
           required>
    @error('harga')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="maks_peserta" class="form-label">Maksimal Peserta</label>
    <input type="number"
           name="maks_peserta"
           id="maks_peserta"
           class="form-control @error('maks_peserta') is-invalid @enderror"
           value="{{ old('maks_peserta', optional($turnamenModel)->maks_peserta) }}"
           min="1"
           placeholder="Kosongkan jika tidak ada batas">
    <div class="form-text">Membatasi jumlah peserta yang dapat disetujui admin. Tidak membatasi pendaftaran mandiri.</div>
    @error('maks_peserta')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="syarat" class="form-label">Syarat & Ketentuan</label>
    <textarea name="syarat"
              id="syarat"
              rows="5"
              class="form-control @error('syarat') is-invalid @enderror"
              placeholder="Tuliskan syarat dan ketentuan turnamen...">{{ old('syarat', optional($turnamenModel)->syarat) }}</textarea>
    @error('syarat')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@php
    $photoService = app(\App\Services\TurnamenPhotoService::class);
    $fotoPreview = optional($turnamenModel)->foto_url ?: $photoService->placeholderUrl();
@endphp
<div class="mb-3">
    <label for="foto" class="form-label">Foto Turnamen <span class="text-muted fw-normal">(opsional)</span></label>
    <div class="d-flex align-items-start gap-3 mb-2">
        <img id="turnamen-foto-preview"
             src="{{ $fotoPreview }}"
             alt="Preview foto turnamen"
             class="rounded border bg-light object-fit-cover"
             width="160"
             height="90"
             style="width: 160px; height: 90px; object-fit: cover;">
        <div class="small text-muted">
            Digunakan sebagai preview saat link turnamen dibagikan ke WhatsApp.
            Disarankan rasio mendekati 16:9, minimal 300×300 px. Format JPG/PNG/WebP, maks. 5 MB.
        </div>
    </div>
    <input type="file"
           name="foto"
           id="foto"
           class="form-control @error('foto') is-invalid @enderror"
           accept="image/jpeg,image/png,image/webp"
           data-pemain-photo-input
           data-preview-target="turnamen-foto-preview"
           data-placeholder="{{ $photoService->placeholderUrl() }}">
    @error('foto')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if (optional($turnamenModel)->foto)
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" value="1" id="remove_foto" name="remove_foto"
                   {{ old('remove_foto') ? 'checked' : '' }}>
            <label class="form-check-label" for="remove_foto">Hapus foto saat ini</label>
        </div>
    @endif
</div>

<div class="mb-3">
    <label for="jenis" class="form-label">Jenis Turnamen <span class="text-danger">*</span></label>
    <select name="jenis" id="jenis" class="form-select @error('jenis') is-invalid @enderror" required>
        @foreach (['single' => 'Single', 'double' => 'Double', 'mahjong' => 'Mahjong', 'friendly' => 'Group Match'] as $value => $label)
            <option value="{{ $value }}" {{ old('jenis', optional($turnamenModel)->jenis ?? 'single') === $value ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    <div class="form-text">
        Single: daftar individu, pasangan diacak saat pendaftaran ditutup.
        Double: daftar individu atau berpasangan; semua harus berpasangan sebelum pendaftaran ditutup.
        Mahjong: grup 4 pemain tanpa head-to-head.
        Group Match: liga antar grup (ukuran grup diatur di bawah), tanding pasangan dinamis, tanpa total poin pemain.
        Tamu dapat daftar individu atau satu grup lengkap sesuai ukuran yang ditentukan; grup lengkap yang sudah disetujui dipertahankan saat matchmaking.
    </div>
    @error('jenis')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@php
    $canEditPlayersPerGroup = ! $turnamenModel || $turnamenModel->canEditFriendlyPlayersPerGroup();
    $playersPerGroupValue = old(
        'players_per_group',
        optional($turnamenModel)->players_per_group
            ?? \App\Models\Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP
    );
    $showPlayersPerGroup = old('jenis', optional($turnamenModel)->jenis ?? 'single') === 'friendly';
@endphp
<div class="mb-3 {{ $showPlayersPerGroup ? '' : 'd-none' }}" id="players-per-group-wrap">
    <label for="players_per_group" class="form-label">
        Pemain per Grup <span class="text-danger">*</span>
    </label>
    <input type="number"
           name="players_per_group"
           id="players_per_group"
           class="form-control @error('players_per_group') is-invalid @enderror"
           value="{{ $playersPerGroupValue }}"
           min="{{ \App\Models\Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP }}"
           max="255"
           @if (! $canEditPlayersPerGroup) readonly @endif
           @if ($showPlayersPerGroup && $canEditPlayersPerGroup) required @endif>
    <div class="form-text">
        Minimal {{ \App\Models\Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP }} pemain per grup.
        @if ($canEditPlayersPerGroup)
            Bisa diubah selama status draft/open dan belum ada pendaftaran.
        @else
            Terkunci karena sudah ada pendaftaran atau status bukan draft/open.
        @endif
    </div>
    @error('players_per_group')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const jenisSelect = document.getElementById('jenis');
    const wrap = document.getElementById('players-per-group-wrap');
    const input = document.getElementById('players_per_group');
    if (!jenisSelect || !wrap || !input) return;

    const sync = () => {
        const isFriendly = jenisSelect.value === 'friendly';
        wrap.classList.toggle('d-none', !isFriendly);
        if (!input.readOnly) {
            input.required = isFriendly;
        }
    };

    jenisSelect.addEventListener('change', sync);
    sync();
});
</script>
@endpush
@endonce

<div class="mb-3">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
        @foreach (['draft' => 'Draft', 'open' => 'Open (Pendaftaran Dibuka)', 'ongoing' => 'Ongoing (Berlangsung)', 'completed' => 'Completed (Selesai)'] as $value => $label)
            <option value="{{ $value }}" {{ old('status', optional($turnamenModel)->status ?? 'draft') === $value ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    <div class="form-text">Beberapa turnamen dapat berstatus <strong>open</strong> secara bersamaan.
        Setelah disimpan, Anda dapat menambah kategori kompetisi (kapasitas & biaya terpisah) di halaman edit.</div>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
