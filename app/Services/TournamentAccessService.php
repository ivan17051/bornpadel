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

    public function assignedTurnamenId(): ?int
    {
        $user = $this->user();

        return $user ? $user->id_turnamen : null;
    }

    public function assignedTurnamen(): ?Turnamen
    {
        $id = $this->assignedTurnamenId();

        return $id ? Turnamen::find($id) : null;
    }

    public function listForFilter(): Collection
    {
        if ($this->isAdmin()) {
            return Turnamen::query()->orderByDesc('doc')->get();
        }

        $turnamen = $this->assignedTurnamen();

        return $turnamen ? collect([$turnamen]) : collect();
    }

    public function resolveTurnamen(?int $id = null, ?GroupMatchmakingService $matchmakingService = null): ?Turnamen
    {
        if ($this->isPanitia()) {
            return $this->assignedTurnamen();
        }

        if ($id) {
            return Turnamen::find($id);
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

        if (! $user->id_turnamen) {
            abort(403, 'Akun panitia belum ditugaskan ke turnamen.');
        }

        if ($request->filled('id_turnamen') && (int) $request->id_turnamen !== (int) $user->id_turnamen) {
            abort(403, 'Anda tidak memiliki akses ke turnamen ini.');
        }

        $request->merge(['id_turnamen' => $user->id_turnamen]);
    }

    public function assertTurnamenId(int $turnamenId): void
    {
        if ($this->isAdmin()) {
            return;
        }

        if ((int) $turnamenId !== (int) $this->assignedTurnamenId()) {
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

        $exists = TurnamenPeserta::where('id_turnamen', $this->assignedTurnamenId())
            ->where('id_pemain', $pemain->id)
            ->exists();

        if (! $exists) {
            abort(403, 'Pemain tidak terdaftar pada turnamen Anda.');
        }
    }
}
