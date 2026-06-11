@php
    $pemainModel = isset($pemain) ? $pemain : null;
    $showRegistrationFields = $showRegistrationFields ?? false;
    $turnamenList = $turnamenList ?? collect();
@endphp

@if ($showRegistrationFields)
    <div class="mb-3">
        <label for="id_turnamen" class="form-label">Turnamen <span class="text-danger">*</span></label>
        <select name="id_turnamen" id="id_turnamen" class="form-select @error('id_turnamen') is-invalid @enderror" required>
            <option value="" disabled {{ old('id_turnamen', request('id_turnamen')) ? '' : 'selected' }}>Pilih turnamen</option>
            @foreach ($turnamenList as $item)
                <option value="{{ $item->id }}"
                    {{ (string) old('id_turnamen', request('id_turnamen')) === (string) $item->id ? 'selected' : '' }}>
                    {{ $item->nama }} — {{ ucfirst($item->status) }}
                </option>
            @endforeach
        </select>
        @error('id_turnamen')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="status" class="form-label">Status Pendaftaran <span class="text-danger">*</span></label>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                <option value="{{ $value }}" {{ old('status', 'approved') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <hr class="my-4">
@endif

@if ($showPhotoField ?? false)
    <x-pemain-photo-input input-id="admin-foto" preview-id="admin-foto-preview" label="Foto Pemain" />
@endif

<div class="mb-3">
    <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
    <input type="text"
           name="nama"
           id="nama"
           class="form-control @error('nama') is-invalid @enderror"
           value="{{ old('nama', optional($pemainModel)->nama) }}"
           required>
    @error('nama')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="tgl_lahir" class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
    <input type="date"
           name="tgl_lahir"
           id="tgl_lahir"
           class="form-control @error('tgl_lahir') is-invalid @enderror"
           value="{{ old('tgl_lahir', optional(optional($pemainModel)->tgl_lahir)->format('Y-m-d')) }}"
           max="{{ date('Y-m-d', strtotime('-1 day')) }}"
           required>
    @error('tgl_lahir')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="gender" class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
    <select name="gender" id="gender" class="form-select @error('gender') is-invalid @enderror" required>
        <option value="" disabled {{ old('gender', optional($pemainModel)->gender) ? '' : 'selected' }}>Pilih jenis kelamin</option>
        <option value="male" {{ old('gender', optional($pemainModel)->gender) === 'male' ? 'selected' : '' }}>Laki-laki</option>
        <option value="female" {{ old('gender', optional($pemainModel)->gender) === 'female' ? 'selected' : '' }}>Perempuan</option>
    </select>
    @error('gender')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="no_hp" class="form-label">Nomor HP / WhatsApp <span class="text-danger">*</span></label>
    <input type="tel"
           name="no_hp"
           id="no_hp"
           class="form-control @error('no_hp') is-invalid @enderror"
           value="{{ old('no_hp', optional($pemainModel)->no_hp) }}"
           placeholder="08xxxxxxxxxx"
           required>
    @error('no_hp')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="rating" class="form-label">Rating</label>
    <input type="number"
           name="rating"
           id="rating"
           class="form-control @error('rating') is-invalid @enderror"
           value="{{ old('rating', optional($pemainModel)->rating) }}"
           min="0"
           max="10"
           step="0.1">
    <div class="form-text">Skala 0–10. Kosongkan jika belum ada rating.</div>
    @error('rating')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
