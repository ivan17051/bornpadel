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

    $lockUntilEdit = (bool) ($lockUntilEdit ?? false);
    $fieldsLocked = $lockUntilEdit && old('profile_unlocked', '0') !== '1';
    $namaValue = $oldValue('nama', optional($existingPemain)->nama);
    $tglLahirValue = $oldValue('tgl_lahir', optional(optional($existingPemain)->tgl_lahir)->format('Y-m-d'));
    $genderValue = $oldValue('gender', optional($existingPemain)->gender);
    $ratingValue = $oldValue('rating', optional($existingPemain)->rating);
@endphp

@if ($lockUntilEdit)
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted mb-0" data-profile-lock-hint {{ $fieldsLocked ? '' : 'hidden' }}>
            <i class="bi bi-lock me-1"></i> Profil terkunci. Klik Edit Profil untuk mengubah data.
        </div>
        <div class="small text-success mb-0" data-profile-unlock-hint {{ $fieldsLocked ? 'hidden' : '' }}>
            <i class="bi bi-unlock me-1"></i> Mode edit aktif. Perubahan akan disimpan ke profil ini.
        </div>
        <button type="button"
                class="btn btn-sm btn-outline-primary"
                data-unlock-profile-fields
                {{ $fieldsLocked ? '' : 'hidden' }}>
            <i class="bi bi-pencil me-1"></i> Edit Profil
        </button>
    </div>
    <input type="hidden" name="profile_unlocked" value="{{ $fieldsLocked ? '0' : '1' }}" data-profile-unlocked-flag>
@endif

<div data-profile-fields @if ($fieldsLocked) data-locked="1" @endif>
    @if ($showPhoto ?? true)
        <x-pemain-photo-input
            :input-id="$inputId"
            :preview-id="$previewId"
            :input-name="$inputName ?? ($prefix === 'partner_' ? 'partner_foto' : 'foto')"
            :label="'Foto ' . $labelPrefix . ( ($optionalPhoto ?? true) ? '' : '' )"
            :preview-src="$previewSrcResolved"
            :show-preview="$showPhotoPreview ?? true"
            :disabled="$fieldsLocked" />
    @endif

    @if ($phoneReadonly ?? false)
        <x-phone-input :name="$field('no_hp')"
                       :id="$field('no_hp')"
                       label="Nomor HP / WhatsApp"
                       :value="$phoneValue ?? ''"
                       :readonly="true" />
    @else
        <x-phone-input :name="$field('no_hp')"
                       :id="$field('no_hp')"
                       label="Nomor HP / WhatsApp"
                       :value="$phoneValue ?? $oldValue('no_hp', optional($existingPemain)->no_hp)"
                       :error-key="$errorName('no_hp')" />
    @endif

    <div class="mb-3">
        <label for="{{ $field('nama') }}" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
        <input type="text"
               name="{{ $field('nama') }}"
               id="{{ $field('nama') }}"
               class="form-control @error($errorName('nama')) is-invalid @enderror"
               value="{{ $namaValue }}"
               required
               @if ($fieldsLocked) readonly @endif
               data-lockable>
        @error($errorName('nama'))
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="{{ $field('tgl_lahir') }}" class="form-label">Tanggal Lahir <span class="text-muted fw-normal">(opsional)</span></label>
        <input type="date"
               name="{{ $field('tgl_lahir') }}"
               id="{{ $field('tgl_lahir') }}"
               class="form-control @error($errorName('tgl_lahir')) is-invalid @enderror"
               value="{{ $tglLahirValue }}"
               max="{{ date('Y-m-d', strtotime('-1 day')) }}"
               @if ($fieldsLocked) readonly @endif
               data-lockable>
        @error($errorName('tgl_lahir'))
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="{{ $field('gender') }}" class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
        <select name="{{ $field('gender') }}"
                id="{{ $field('gender') }}"
                class="form-select @error($errorName('gender')) is-invalid @enderror"
                required
                @if ($fieldsLocked) disabled @endif
                data-lockable
                data-lockable-select>
            <option value="" disabled {{ $genderValue ? '' : 'selected' }}>Pilih jenis kelamin</option>
            <option value="male" {{ $genderValue === 'male' ? 'selected' : '' }}>Laki-laki</option>
            <option value="female" {{ $genderValue === 'female' ? 'selected' : '' }}>Perempuan</option>
        </select>
        @if ($fieldsLocked)
            <input type="hidden" name="{{ $field('gender') }}" value="{{ $genderValue }}" data-lockable-select-fallback>
        @endif
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
               value="{{ $ratingValue }}"
               min="0"
               max="10"
               step="0.1"
               @if ($fieldsLocked) readonly @endif
               data-lockable>
        <div class="form-text">Skala 0–10. Kosongkan jika belum ada rating.</div>
        @error($errorName('rating'))
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

@if ($lockUntilEdit)
@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-unlock-profile-fields]').forEach((button) => {
        button.addEventListener('click', function () {
            const root = button.closest('form') || document;
            const fields = root.querySelector('[data-profile-fields]');
            const flag = root.querySelector('[data-profile-unlocked-flag]');
            const lockHint = root.querySelector('[data-profile-lock-hint]');
            const unlockHint = root.querySelector('[data-profile-unlock-hint]');

            if (fields) {
                fields.removeAttribute('data-locked');
                fields.querySelectorAll('[data-lockable]').forEach((el) => {
                    el.removeAttribute('readonly');
                    el.removeAttribute('disabled');
                });
                fields.querySelectorAll('[data-lockable-select-fallback]').forEach((el) => el.remove());
                fields.querySelectorAll('[data-pemain-photo-input]').forEach((el) => {
                    el.removeAttribute('disabled');
                });
            }

            if (flag) {
                flag.value = '1';
            }

            button.hidden = true;
            if (lockHint) lockHint.hidden = true;
            if (unlockHint) unlockHint.hidden = false;
        });
    });
});
</script>
@endpush
@endonce
@endif
