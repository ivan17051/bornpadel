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
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
        @foreach (['draft' => 'Draft', 'open' => 'Open (Pendaftaran Dibuka)', 'ongoing' => 'Ongoing (Berlangsung)', 'completed' => 'Completed (Selesai)'] as $value => $label)
            <option value="{{ $value }}" {{ old('status', optional($turnamenModel)->status ?? 'draft') === $value ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    <div class="form-text">Hanya satu turnamen yang boleh berstatus <strong>open</strong> pada saat yang sama.</div>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
