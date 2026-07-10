@php
    $namePrefix = $prefix !== '' ? rtrim($prefix, '.') : '';

    $fieldName = function (string $name) use ($namePrefix) {
        return $namePrefix === '' ? $name : $namePrefix . '[' . $name . ']';
    };

    $dotKey = function (string $name) use ($namePrefix) {
        return $namePrefix === '' ? $name : $namePrefix . '.' . $name;
    };

    $oldValue = function (string $name, $default = null) use ($dotKey) {
        return old($dotKey($name), $default);
    };

    $errorKey = function (string $name) use ($dotKey) {
        return $dotKey($name);
    };
@endphp

@if ($phoneReadonly)
    <div class="mb-3">
        <label class="form-label fw-semibold">Nomor HP / WhatsApp</label>
        <input type="text" class="form-control" value="{{ $phoneValue }}" readonly>
        <input type="hidden" name="{{ $fieldName('no_hp') }}" value="{{ old($errorKey('no_hp'), $phoneValue) }}">
    </div>
@else
    <div class="mb-3">
        <label for="{{ $dotKey('no_hp') }}" class="form-label fw-semibold">Nomor HP / WhatsApp <span class="text-danger">*</span></label>
        <input type="tel"
               name="{{ $fieldName('no_hp') }}"
               id="{{ $dotKey('no_hp') }}"
               class="form-control @error($errorKey('no_hp')) is-invalid @enderror"
               value="{{ $phoneValue }}"
               placeholder="08xxxxxxxxxx"
               required
               inputmode="tel"
               autocomplete="tel">
        @error($errorKey('no_hp'))
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
    <label for="{{ $dotKey('nama') }}" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
    <input type="text"
           name="{{ $fieldName('nama') }}"
           id="{{ $dotKey('nama') }}"
           class="form-control @error($errorKey('nama')) is-invalid @enderror"
           value="{{ $oldValue('nama', optional($existingPemain)->nama) }}"
           placeholder="Masukkan nama lengkap"
           required
           autocomplete="name">
    @error($errorKey('nama'))
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="{{ $dotKey('tgl_lahir') }}" class="form-label fw-semibold">Tanggal Lahir <span class="text-muted fw-normal">(opsional)</span></label>
    <input type="date"
           name="{{ $fieldName('tgl_lahir') }}"
           id="{{ $dotKey('tgl_lahir') }}"
           class="form-control @error($errorKey('tgl_lahir')) is-invalid @enderror"
           value="{{ $oldValue('tgl_lahir', optional(optional($existingPemain)->tgl_lahir)->format('Y-m-d')) }}"
           max="{{ date('Y-m-d', strtotime('-1 day')) }}">
    @error($errorKey('tgl_lahir'))
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="{{ $dotKey('gender') }}" class="form-label fw-semibold">Jenis Kelamin <span class="text-danger">*</span></label>
    <select name="{{ $fieldName('gender') }}"
            id="{{ $dotKey('gender') }}"
            class="form-select @error($errorKey('gender')) is-invalid @enderror"
            required>
        <option value="" disabled {{ $oldValue('gender', optional($existingPemain)->gender) ? '' : 'selected' }}>Pilih jenis kelamin</option>
        <option value="male" {{ $oldValue('gender', optional($existingPemain)->gender) === 'male' ? 'selected' : '' }}>Laki-laki</option>
        <option value="female" {{ $oldValue('gender', optional($existingPemain)->gender) === 'female' ? 'selected' : '' }}>Perempuan</option>
    </select>
    @error($errorKey('gender'))
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-0">
    <label for="{{ $dotKey('rating') }}" class="form-label fw-semibold">Rating</label>
    <input type="number"
           name="{{ $fieldName('rating') }}"
           id="{{ $dotKey('rating') }}"
           class="form-control @error($errorKey('rating')) is-invalid @enderror"
           value="{{ $oldValue('rating', optional($existingPemain)->rating) }}"
           placeholder="Contoh: 3.5"
           min="0"
           max="10"
           step="0.1">
    <div class="form-text">Perkiraan level permainan (skala 0–10).</div>
    @error($errorKey('rating'))
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
