@props([
    'inputId' => 'foto',
    'previewId' => 'foto-preview',
    'label' => 'Foto Pemain',
    'optional' => true,
    'previewSrc' => null,
])

@php
    $photoService = app(\App\Services\PemainPhotoService::class);
    $placeholder = $photoService->placeholderUrl();
    $initialPreview = $previewSrc ?: $placeholder;
@endphp

<div class="mb-3 pemain-photo-field">
    <label for="{{ $inputId }}" class="form-label fw-semibold">
        {{ $label }}
        @if ($optional)
            <span class="text-muted fw-normal">(opsional)</span>
        @else
            <span class="text-danger">*</span>
        @endif
    </label>

    <div class="d-flex align-items-center gap-3 mb-2">
        <img id="{{ $previewId }}"
             src="{{ $initialPreview }}"
             alt="Preview foto"
             data-fallback="{{ $placeholder }}"
             onerror="if (this.dataset.fallback) { this.onerror = null; this.src = this.dataset.fallback; }"
             class="pemain-photo-preview rounded-circle border bg-light object-fit-cover"
             width="96"
             height="96"
             style="width: 96px; height: 96px;">
        <div class="small text-muted">
            Format: JPG, PNG, atau WebP. Maks. 5 MB.
        </div>
    </div>

    <input type="file"
           name="foto"
           id="{{ $inputId }}"
           class="form-control @error('foto') is-invalid @enderror"
           accept="image/jpeg,image/png,image/webp"
           data-pemain-photo-input
           data-preview-target="{{ $previewId }}"
           data-placeholder="{{ $placeholder }}">
    @error('foto')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@once
    @push('styles')
    <style>
        .pemain-photo-preview {
            object-fit: cover;
            width: 96px;
            height: 96px;
            min-width: 96px;
            min-height: 96px;
        }
        .pemain-avatar { object-fit: cover; display: block; }
    </style>
    @endpush
    @push('scripts')
    <script src="{{ asset('public/js/pemain-photo-preview.js') }}"></script>
    @endpush
@endonce
