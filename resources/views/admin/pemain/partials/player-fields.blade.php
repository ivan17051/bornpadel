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

    $photoService = app(\App\Services\PemainPhotoService::class);
    $previewSrcResolved = $previewSrc ?? (
        $existingPemain && $existingPemain->foto
            ? $photoService->url($existingPemain->foto)
            : null
    );
@endphp

@if ($phoneReadonly ?? false)
    <div class="mb-3">
        <label class="form-label">Nomor HP / WhatsApp</label>
        <input type="text" class="form-control" value="{{ $phoneValue ?? '' }}" readonly>
        <input type="hidden" name="{{ $field('no_hp') }}" value="{{ $phoneValue ?? '' }}">
    </div>
@else
    <div class="mb-3">
        <label for="{{ $field('no_hp') }}" class="form-label">Nomor HP / WhatsApp <span class="text-danger">*</span></label>
        <input type="tel"
               name="{{ $field('no_hp') }}"
               id="{{ $field('no_hp') }}"
               class="form-control @error($errorName('no_hp')) is-invalid @enderror"
               value="{{ $phoneValue ?? $oldValue('no_hp', optional($existingPemain)->no_hp) }}"
               placeholder="08xxxxxxxxxx"
               required
               inputmode="tel"
               autocomplete="tel">
        @error($errorName('no_hp'))
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
@endif

@if ($existingPemain ?? null)
    <div class="alert alert-info py-2 small mb-3">
        <i class="bi bi-person-check me-1"></i>
        Pemain ditemukan di database. Data dapat diperbarui sebelum disimpan.
    </div>
@elseif (($phoneValue ?? '') !== '' || old($field('no_hp')))
    <div class="alert alert-light border py-2 small mb-3">
        <i class="bi bi-person-plus me-1"></i>
        Nomor HP belum terdaftar. Lengkapi data pemain baru.
    </div>
@endif

@if ($showPhoto ?? true)
    <x-pemain-photo-input
        :input-id="$inputId"
        :preview-id="$previewId"
        :input-name="$inputName ?? ($prefix === 'partner_' ? 'partner_foto' : 'foto')"
        :label="'Foto ' . $labelPrefix . ( ($optionalPhoto ?? true) ? '' : '' )"
        :preview-src="$previewSrcResolved"
        :show-preview="$showPhotoPreview ?? true" />
@endif

<div class="mb-3">
    <label for="{{ $field('nama') }}" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
    <input type="text"
           name="{{ $field('nama') }}"
           id="{{ $field('nama') }}"
           class="form-control @error($errorName('nama')) is-invalid @enderror"
           value="{{ $oldValue('nama', optional($existingPemain)->nama) }}"
           required>
    @error($errorName('nama'))
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="{{ $field('tgl_lahir') }}" class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
    <input type="date"
           name="{{ $field('tgl_lahir') }}"
           id="{{ $field('tgl_lahir') }}"
           class="form-control @error($errorName('tgl_lahir')) is-invalid @enderror"
           value="{{ $oldValue('tgl_lahir', optional(optional($existingPemain)->tgl_lahir)->format('Y-m-d')) }}"
           max="{{ date('Y-m-d', strtotime('-1 day')) }}"
           required>
    @error($errorName('tgl_lahir'))
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="{{ $field('gender') }}" class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
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
    <label for="{{ $field('rating') }}" class="form-label">Rating</label>
    <input type="number"
           name="{{ $field('rating') }}"
           id="{{ $field('rating') }}"
           class="form-control @error($errorName('rating')) is-invalid @enderror"
           value="{{ $oldValue('rating', optional($existingPemain)->rating) }}"
           min="0"
           max="10"
           step="0.1">
    <div class="form-text">Skala 0–10. Kosongkan jika belum ada rating.</div>
    @error($errorName('rating'))
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
