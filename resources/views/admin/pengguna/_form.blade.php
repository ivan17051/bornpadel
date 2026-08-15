@php
    $userModel = isset($user) ? $user : null;
@endphp

<div class="mb-3">
    <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
    <input type="text"
           name="name"
           id="name"
           class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', optional($userModel)->name) }}"
           required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
    <input type="text"
           name="username"
           id="username"
           class="form-control @error('username') is-invalid @enderror"
           value="{{ old('username', optional($userModel)->username) }}"
           required>
    @error('username')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input type="email"
           name="email"
           id="email"
           class="form-control @error('email') is-invalid @enderror"
           value="{{ old('email', optional($userModel)->email) }}">
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="password" class="form-label">
        Password
        @if (! $userModel)
            <span class="text-danger">*</span>
        @endif
    </label>
    <input type="password"
           name="password"
           id="password"
           class="form-control @error('password') is-invalid @enderror"
           {{ $userModel ? '' : 'required' }}>
    @if ($userModel)
        <div class="form-text">Kosongkan jika tidak ingin mengubah password.</div>
    @endif
    @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="password_confirmation" class="form-label">
        Konfirmasi Password
        @if (! $userModel)
            <span class="text-danger">*</span>
        @endif
    </label>
    <input type="password"
           name="password_confirmation"
           id="password_confirmation"
           class="form-control"
           {{ $userModel ? '' : 'required' }}>
</div>

<div class="mb-3">
    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
    <select name="role" id="role" class="form-select @error('role') is-invalid @enderror" required>
        @foreach (['admin' => 'Admin (akses semua turnamen)', 'panitia' => 'Panitia (akses turnamen yang dipilih)'] as $value => $label)
            <option value="{{ $value }}" {{ old('role', optional($userModel)->role ?? 'panitia') === $value ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    @error('role')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3" id="turnamen-field">
    <label class="form-label">Turnamen <span class="text-danger turnamen-required">*</span></label>
    @php
        if (old('id_turnamen') !== null) {
            $selectedTurnamenIds = collect(old('id_turnamen'));
        } elseif ($userModel && $userModel->assignedTurnamen && $userModel->assignedTurnamen->isNotEmpty()) {
            $selectedTurnamenIds = $userModel->assignedTurnamen->pluck('id');
        } elseif ($userModel && $userModel->id_turnamen) {
            $selectedTurnamenIds = collect([$userModel->id_turnamen]);
        } else {
            $selectedTurnamenIds = collect();
        }
        $selectedTurnamenIds = $selectedTurnamenIds->map(function ($id) {
            return (string) $id;
        });
    @endphp
    <div class="border rounded p-3 {{ $errors->has('id_turnamen') || $errors->has('id_turnamen.*') ? 'is-invalid' : '' }}"
         id="turnamen-checkbox-list"
         style="max-height: 16rem; overflow-y: auto;">
        @forelse ($turnamenList as $turnamen)
            <div class="form-check">
                <input class="form-check-input"
                       type="checkbox"
                       name="id_turnamen[]"
                       id="id_turnamen_{{ $turnamen->id }}"
                       value="{{ $turnamen->id }}"
                       {{ $selectedTurnamenIds->contains((string) $turnamen->id) ? 'checked' : '' }}>
                <label class="form-check-label" for="id_turnamen_{{ $turnamen->id }}">
                    {{ $turnamen->nama }}
                    @if ($turnamen->status !== 'draft')
                        <span class="text-muted small">({{ ucfirst($turnamen->status) }})</span>
                    @endif
                </label>
            </div>
        @empty
            <div class="text-muted small">Belum ada turnamen.</div>
        @endforelse
    </div>
    <div class="form-text">Panitia hanya dapat mengakses turnamen yang dicentang. Boleh lebih dari satu.</div>
    @error('id_turnamen')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('id_turnamen.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
<script>
    (function () {
        const roleSelect = document.getElementById('role');
        const turnamenField = document.getElementById('turnamen-field');
        const turnamenBoxes = turnamenField.querySelectorAll('input[name="id_turnamen[]"]');

        function toggleTurnamenField() {
            const isPanitia = roleSelect.value === 'panitia';
            turnamenField.style.display = isPanitia ? '' : 'none';
            turnamenBoxes.forEach(function (box) {
                box.disabled = !isPanitia;
                if (!isPanitia) {
                    box.checked = false;
                }
            });
        }

        roleSelect.addEventListener('change', toggleTurnamenField);
        toggleTurnamenField();
    })();
</script>
@endpush
