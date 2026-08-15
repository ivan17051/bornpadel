<?php

namespace App\Services;

use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TournamentAccessService
{
    public function user(): ?User
    {
        return auth()->user();
    }

    public function isAdmin(): bool
    {
        $user = $this->user();

        return $user && $user->isAdmin();
    }

    public function isPanitia(): bool
    {
        $user = $this->user();

        return $user && $user->isPanitia();
    }

    public function assignedTurnamenIds(): array
    {
        $user = $this->user();

        if (! $user || $user->isAdmin()) {
            return [];
        }

        return $user->assignedTurnamenIds();
    }

    public function assignedTurnamenId(): ?int
    {
        $ids = $this->assignedTurnamenIds();

        return $ids[0] ?? null;
    }

    public function assignedTurnamen(): ?Turnamen
    {
        $id = $this->assignedTurnamenId();

        return $id ? Turnamen::find($id) : null;
    }

    public function assignedTurnamenList(): Collection
    {
        return $this->listForFilter();
    }

    public function canAccessTurnamenId(int $turnamenId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($turnamenId, $this->assignedTurnamenIds(), true);
    }

    public function listForFilter(): Collection
    {
        if ($this->isAdmin()) {
            return Turnamen::query()->orderByDesc('doc')->get();
        }

        $ids = $this->assignedTurnamenIds();

        if ($ids === []) {
            return collect();
        }

        return Turnamen::query()
            ->whereIn('id', $ids)
            ->orderByDesc('doc')
            ->get();
    }

    public function resolveTurnamen(
        ?int $id = null,
        ?GroupMatchmakingService $matchmakingService = null,
        bool $fallbackToActive = true
    ): ?Turnamen {
        if ($this->isPanitia()) {
            $ids = $this->assignedTurnamenIds();

            if ($ids === []) {
                return null;
            }

            if ($id) {
                return $this->canAccessTurnamenId($id) ? Turnamen::find($id) : null;
            }

            if (count($ids) === 1) {
                return Turnamen::find($ids[0]);
            }

            if (! $fallbackToActive) {
                return null;
            }

            $matchmakingService = $matchmakingService ?? app(GroupMatchmakingService::class);
            $active = $matchmakingService->getActiveTournament();

            if ($active && $this->canAccessTurnamenId((int) $active->id)) {
                return $active;
            }

            return Turnamen::find($ids[0]);
        }

        if ($id) {
            return Turnamen::find($id);
        }

        if (! $fallbackToActive) {
            return null;
        }

        $matchmakingService = $matchmakingService ?? app(GroupMatchmakingService::class);

        return $matchmakingService->getActiveTournament();
    }

    public function enforceRequestTurnamen(Request $request): void
    {
        $user = $this->user();

        if (! $user || $user->isAdmin()) {
            return;
        }

        $ids = $this->assignedTurnamenIds();

        if ($ids === []) {
            abort(403, 'Akun panitia belum ditugaskan ke turnamen.');
        }

        if ($request->filled('id_turnamen')) {
            if (! $this->canAccessTurnamenId((int) $request->id_turnamen)) {
                abort(403, 'Anda tidak memiliki akses ke turnamen ini.');
            }

            return;
        }

        if (count($ids) === 1) {
            $request->merge(['id_turnamen' => $ids[0]]);
        }
    }

    public function assertTurnamenId(int $turnamenId): void
    {
        if ($this->isAdmin()) {
            return;
        }

        if (! $this->canAccessTurnamenId($turnamenId)) {
            abort(403, 'Anda tidak memiliki akses ke turnamen ini.');
        }
    }

    public function assertPertandinganAccess(Pertandingan $pertandingan): void
    {
        $this->assertTurnamenId((int) $pertandingan->id_turnamen);
    }

    public function assertPemainInAssignedTurnamen(Pemain $pemain): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $ids = $this->assignedTurnamenIds();

        if ($ids === []) {
            abort(403, 'Akun panitia belum ditugaskan ke turnamen.');
        }

        $exists = TurnamenPeserta::query()
            ->whereIn('id_turnamen', $ids)
            ->involvingPemain($pemain->id)
            ->exists();

        if (! $exists) {
            abort(403, 'Pemain tidak terdaftar pada turnamen Anda.');
        }
    }
}
