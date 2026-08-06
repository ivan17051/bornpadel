<?php

namespace App\Services;

use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TurnamenKategoriService
{
    public function listForTurnamen(Turnamen $turnamen)
    {
        return $turnamen->kategori()->ordered()->get();
    }

    public function create(Turnamen $turnamen, array $data): TurnamenKategori
    {
        $nama = trim((string) ($data['nama'] ?? ''));

        if ($nama === '') {
            throw new RuntimeException('Nama kategori wajib diisi.');
        }

        $this->assertUniqueName($turnamen, $nama);

        $default = $turnamen->ensureDefaultKategori();
        $maxUrutan = (int) $turnamen->kategori()->max('urutan');

        $kategori = $turnamen->kategori()->create([
            'nama' => $nama,
            'is_default' => false,
            'urutan' => (int) ($data['urutan'] ?? ($maxUrutan + 1)),
            'harga' => array_key_exists('harga', $data) && $data['harga'] !== null && $data['harga'] !== ''
                ? $data['harga']
                : $default->harga,
            'maks_peserta' => array_key_exists('maks_peserta', $data) && $data['maks_peserta'] !== null && $data['maks_peserta'] !== ''
                ? (int) $data['maks_peserta']
                : $default->maks_peserta,
            'status' => 'draft',
            'players_per_group' => $default->players_per_group,
        ]);

        return $kategori->fresh();
    }

    /**
     * Manual status transitions for categories (draft <-> open).
     * ongoing / completed remain driven by matchmaking lifecycle.
     */
    public function transitionStatus(TurnamenKategori $kategori, string $status): TurnamenKategori
    {
        $status = strtolower(trim($status));
        $from = (string) $kategori->status;

        $allowed = [
            'draft' => ['open'],
            'open' => ['draft'],
        ];

        if (! isset($allowed[$from]) || ! in_array($status, $allowed[$from], true)) {
            throw new RuntimeException(
                'Status kategori “' . $from . '” tidak dapat diubah ke “' . $status . '”. '
                . 'Gunakan Tutup Pendaftaran / Selesaikan Turnamen di matchmaking untuk status selanjutnya.'
            );
        }

        if ($from === 'open' && $status === 'draft' && ! $this->canUnpublish($kategori)) {
            throw new RuntimeException(
                'Kategori sudah memiliki grup, pertandingan, atau pemenang dan tidak dapat dikembalikan ke draft.'
            );
        }

        $kategori->status = $status;
        $kategori->save();

        $turnamen = $kategori->turnamen ?: Turnamen::find($kategori->id_turnamen);

        if ($turnamen) {
            // Publishing at least one category opens a draft event so guests can see it.
            if ($status === 'open' && $turnamen->status === 'draft') {
                $turnamen->update(['status' => 'open']);
            }

            // Single-category event stays in sync when unpublishing.
            if ($status === 'draft'
                && $turnamen->kategori()->count() <= 1
                && $turnamen->status === 'open') {
                $turnamen->update(['status' => 'draft']);
            }
        }

        return $kategori->fresh();
    }

    public function canPublish(TurnamenKategori $kategori): bool
    {
        return $kategori->status === 'draft';
    }

    public function canUnpublish(TurnamenKategori $kategori): bool
    {
        if ($kategori->status !== 'open') {
            return false;
        }

        return ! $kategori->grup()->exists()
            && ! $kategori->pertandingan()->exists()
            && ! $kategori->pemenang()->exists();
    }

    public function update(TurnamenKategori $kategori, array $data): TurnamenKategori
    {
        $turnamen = $kategori->turnamen ?: Turnamen::findOrFail($kategori->id_turnamen);

        if (array_key_exists('nama', $data)) {
            $nama = trim((string) $data['nama']);
            if ($nama === '') {
                throw new RuntimeException('Nama kategori wajib diisi.');
            }
            $this->assertUniqueName($turnamen, $nama, $kategori->id);
            $kategori->nama = $nama;
        }

        if (array_key_exists('harga', $data) && $data['harga'] !== null && $data['harga'] !== '') {
            $kategori->harga = $data['harga'];
        }

        if (array_key_exists('maks_peserta', $data)) {
            $kategori->maks_peserta = $data['maks_peserta'] === null || $data['maks_peserta'] === ''
                ? null
                : (int) $data['maks_peserta'];
        }

        if (array_key_exists('urutan', $data) && $data['urutan'] !== null && $data['urutan'] !== '') {
            $kategori->urutan = max(1, (int) $data['urutan']);
        }

        if ($turnamen->isFriendly()
            && array_key_exists('players_per_group', $data)
            && $data['players_per_group'] !== null
            && $data['players_per_group'] !== ''
            && $this->canEditPlayersPerGroup($kategori)) {
            $kategori->players_per_group = max(
                Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP,
                (int) $data['players_per_group']
            );
        }

        $kategori->save();

        if ($kategori->is_default && $turnamen->kategori()->count() <= 1) {
            $turnamen->forceFill([
                'harga' => $kategori->harga,
                'maks_peserta' => $kategori->maks_peserta,
                'players_per_group' => $kategori->players_per_group,
            ])->save();
        }

        return $kategori->fresh();
    }

    public function delete(TurnamenKategori $kategori): void
    {
        if ($kategori->is_default) {
            throw new RuntimeException('Kategori default tidak dapat dihapus.');
        }

        if (! $this->canDelete($kategori)) {
            throw new RuntimeException('Kategori masih memiliki peserta, grup, atau pertandingan dan tidak dapat dihapus.');
        }

        $kategori->delete();
    }

    public function canDelete(TurnamenKategori $kategori): bool
    {
        if ($kategori->is_default) {
            return false;
        }

        return ! $this->hasCompetitionData($kategori);
    }

    public function hasCompetitionData(TurnamenKategori $kategori): bool
    {
        return $kategori->peserta()->exists()
            || $kategori->grup()->exists()
            || $kategori->pertandingan()->exists()
            || $kategori->pasangan()->exists()
            || $kategori->grupPendaftaran()->exists()
            || $kategori->pemenang()->exists();
    }

    public function canEditPlayersPerGroup(TurnamenKategori $kategori): bool
    {
        if (! in_array($kategori->status, ['draft', 'open'], true)) {
            return false;
        }

        return ! $kategori->peserta()->exists()
            && ! $kategori->grupPendaftaran()->exists()
            && ! $kategori->grup()->exists();
    }

    public function reorder(Turnamen $turnamen, array $orderedIds): void
    {
        $ids = array_values(array_map('intval', $orderedIds));
        $owned = $turnamen->kategori()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($ids) !== count($owned) || array_diff($ids, $owned) !== []) {
            throw new RuntimeException('Urutan kategori tidak valid.');
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                TurnamenKategori::whereKey($id)->update(['urutan' => $index + 1]);
            }
        });
    }

    protected function assertUniqueName(Turnamen $turnamen, string $nama, ?int $ignoreId = null): void
    {
        $query = $turnamen->kategori()
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)]);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw new RuntimeException('Nama kategori sudah digunakan pada turnamen ini.');
        }
    }
}
