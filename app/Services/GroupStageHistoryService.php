<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pertandingan;
use App\Models\TurnamenPeserta;
use RuntimeException;

class GroupStageHistoryService
{
    public function getHistory(int $grupId, int $pesertaId): array
    {
        $grup = Grup::query()->with('turnamen')->find($grupId);

        if (! $grup) {
            throw new RuntimeException('Grup tidak ditemukan.');
        }

        $member = GrupMember::query()
            ->where('id_grup', $grupId)
            ->where('id_turnamen_peserta', $pesertaId)
            ->with(['turnamenPeserta.pemain1', ...TurnamenPeserta::partnerPemainEagerLoadsFor('turnamenPeserta')])
            ->first();

        if (! $member) {
            throw new RuntimeException('Peserta tidak ditemukan di grup ini.');
        }

        if ($grup->turnamen && $grup->turnamen->isMahjong()) {
            throw new RuntimeException('Riwayat pertandingan tidak tersedia untuk turnamen Mahjong.');
        }

        $matches = Pertandingan::query()
            ->where('id_grup', $grupId)
            ->where('nama_ronde', 'Fase Grup')
            ->where(function ($query) use ($pesertaId) {
                $query->where('id_peserta1', $pesertaId)
                    ->orWhere('id_peserta2', $pesertaId);
            })
            ->with(array_merge([
                'peserta1.pemain1',
                'peserta2.pemain1',
                'skor',
                'pesertaPemenang.pemain1',
            ], TurnamenPeserta::partnerPemainEagerLoadsFor('peserta1'), TurnamenPeserta::partnerPemainEagerLoadsFor('peserta2'), TurnamenPeserta::partnerPemainEagerLoadsFor('pesertaPemenang')))
            ->orderBy('id')
            ->get();

        return [
            'participant' => [
                'id_peserta' => $pesertaId,
                'nama' => $member->display_name,
                'grup_id' => $grup->id,
                'grup_nama' => $grup->nama,
            ],
            'matches' => $matches->map(fn (Pertandingan $match) => $this->formatMatch($match, $pesertaId))->values()->all(),
        ];
    }

    protected function formatMatch(Pertandingan $match, int $pesertaId): array
    {
        $onSide1 = (int) $match->id_peserta1 === $pesertaId;
        $opponentPeserta = $onSide1 ? $match->peserta2 : $match->peserta1;
        $opponentLabel = $opponentPeserta
            ? $opponentPeserta->display_name
            : ($onSide1 ? $match->side2_label : $match->side1_label);

        $result = 'scheduled';
        if ($match->status === 'completed') {
            $won = (int) $match->id_peserta_pemenang === $pesertaId;
            $result = $won ? 'win' : 'loss';
        } elseif ($match->status === 'pending' || ! $match->isReadyForScoring()) {
            $result = 'pending';
        }

        $sets = $this->formatSets($match, $onSide1);

        return [
            'id' => $match->id,
            'opponent' => $opponentLabel,
            'status' => $match->status,
            'result' => $result,
            'result_label' => $this->resultLabel($result),
            'score' => $this->scoreSummary($sets, $match->status),
            'sets' => $sets,
        ];
    }

    /**
     * @return array<int, array{set:int, own:int, opponent:int}>
     */
    protected function formatSets(Pertandingan $match, bool $onSide1): array
    {
        return $match->skor->map(function ($row) use ($onSide1) {
            return [
                'set' => (int) $row->set_ke,
                'own' => $onSide1 ? (int) $row->skor_pemain1 : (int) $row->skor_pemain2,
                'opponent' => $onSide1 ? (int) $row->skor_pemain2 : (int) $row->skor_pemain1,
            ];
        })->values()->all();
    }

    protected function scoreSummary(array $sets, string $status): ?string
    {
        if ($sets === []) {
            return $status === 'completed' ? '—' : null;
        }

        return collect($sets)
            ->map(fn (array $set) => $set['own'] . '-' . $set['opponent'])
            ->implode(', ');
    }

    protected function resultLabel(string $result): string
    {
        $labels = [
            'win' => 'Menang',
            'loss' => 'Kalah',
            'pending' => 'Menunggu',
            'scheduled' => 'Terjadwal',
        ];

        return $labels[$result] ?? ucfirst($result);
    }
}
