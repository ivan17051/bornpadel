@php
    $field = function (string $name) use ($prefix) {
        return $prefix . $name;
    };

    $oldValue = function (string $name, $default = null) use ($prefix) {
        return old($prefix . $name, $default);
    };

    $errorName = function (string $name) use ($prefix) {
        return $prefix . $name;
    };
@endphp

@if ($phoneReadonly)
    <div class="mb-3">
        <label class="form-label fw-semibold">Nomor HP / WhatsApp</label>
        <input type="text" class="form-control" value="{{ $phoneValue }}" readonly>
    </div>
@else
    <div class="mb-3">
        <label for="{{ $field('no_hp') }}" class="form-label fw-semibold">Nomor HP / WhatsApp <span class="text-danger">*</span></label>
        <input type="tel"
               name="{{ $field('no_hp') }}"
               id="{{ $field('no_hp') }}"
               class="form-control @error($errorName('no_hp')) is-invalid @enderror"
               value="{{ $phoneValue }}"
               placeholder="08xxxxxxxxxx"
               required
               inputmode="tel"
               autocomplete="tel">
        @error($errorName('no_hp'))
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
@endif

<x-pemain-photo-input
    :input-id="$inputId"
    :preview-id="$previewId"
    :input-name="$inputName ?? 'foto'"
    :label="'Foto ' . $labelPrefix "
    :preview-src="$previewSrc" />

<div class="mb-3">
    <label for="{{ $field('nama') }}" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
    <input type="text"
           name="{{ $field('nama') }}"
           id="{{ $field('nama') }}"
           class="form-control @error($errorName('nama')) is-invalid @enderror"
           value="{{ $oldValue('nama', optional($existingPemain)->nama) }}"
           placeholder="Masukkan nama lengkap"
           required
           autocomplete="name">
    @error($errorName('nama'))
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="{{ $field('tgl_lahir') }}" class="form-label fw-semibold">Tanggal Lahir <span class="text-danger">*</span></label>
    <input type="date"
           name="{{ $field('tgl_lahir') }}"
           id="{{ $field('tgl_lahir') }}"
           class="form-control @error($errorName('tgl_lahir')) is-invalid @enderror"
           value="{{ $oldValue('tgl_lahir', optional(optional($existingPemain)->tgl_lahir)->format('Y-m-d')) }}"
           required
           max="{{ date('Y-m-d', strtotime('-1 day')) }}">
    @error($errorName('tgl_lahir'))
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="{{ $field('gender') }}" class="form-label fw-semibold">Jenis Kelamin <span class="text-danger">*</span></label>
    <select name="{{ $field('gender') }}"
            id="{{ $field('gender') }}"
            class="form-select @error($errorName('gender')) is-invalid @enderror"
            required>
        <option value="" disabled {{ $oldValue('gender', optional($existingPemain)->gender) ? '' : 'selected' }}>Pilih jenis kelamin</option>
        <option value="male" {{ $oldValue('gender', optional($existingPemain)->gender) === 'male' ? 'selected' : '' }}>Laki-laki</option>
        <option value="female" {{ $oldValue('gender', optional($existingPemain)->gender) === 'female' ? 'selected' : '' }}>Perempuan</option>
    </select>
    @error($errorName('gender'))
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-0">
    <label for="{{ $field('rating') }}" class="form-label fw-semibold">Rating</label>
    <input type="number"
           name="{{ $field('rating') }}"
           id="{{ $field('rating') }}"
           class="form-control @error($errorName('rating')) is-invalid @enderror"
           value="{{ $oldValue('rating', optional($existingPemain)->rating) }}"
           placeholder="Contoh: 3.5"
           min="0"
           max="10"
           step="0.1">
    <div class="form-text">Perkiraan level permainan (skala 0–10).</div>
    @error($errorName('rating'))
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
