<?php

namespace App\Services;

use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class TournamentDeletionService
{
    public function assertAdminPassword(User $user, string $password): void
    {
        if (! $user->isAdmin()) {
            throw new RuntimeException('Hanya admin yang dapat menghapus turnamen.');
        }

        if (! Hash::check($password, $user->password)) {
            throw new RuntimeException('Password admin tidak valid.');
        }
    }

    public function delete(Turnamen $turnamen, User $user, string $password): void
    {
        $this->assertAdminPassword($user, $password);

        DB::transaction(function () use ($turnamen) {
            Pertandingan::query()
                ->where('id_turnamen', $turnamen->id)
                ->update([
                    'id_next_pertandingan' => null,
                    'id_next_pertandingan_kalah' => null,
                ]);

            $turnamen->delete();
        });
    }
}
