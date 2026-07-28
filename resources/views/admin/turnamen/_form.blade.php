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
        Double: pendaftaran guest dapat berpasangan.
        Mahjong: grup 4 pemain tanpa head-to-head.
        Group Match: liga antar grup (4 pemain/grup), tanding pasangan dinamis, tanpa total poin pemain.
    </div>
    @error('jenis')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
        @foreach (['draft' => 'Draft', 'open' => 'Open (Pendaftaran Dibuka)', 'ongoing' => 'Ongoing (Berlangsung)', 'completed' => 'Completed (Selesai)'] as $value => $label)
            <option value="{{ $value }}" {{ old('status', optional($turnamenModel)->status ?? 'draft') === $value ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    <div class="form-text">Beberapa turnamen dapat berstatus <strong>open</strong> secara bersamaan.</div>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
