<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Support\Collection;

class LeaderboardService
{
    protected $mahjongRanker;

    public function __construct(MahjongStandingRanker $mahjongRanker)
    {
        $this->mahjongRanker = $mahjongRanker;
    }

    public function getActiveTournament(): ?Turnamen
    {
        return Turnamen::whereIn('status', ['open', 'ongoing', 'completed'])
            ->latest('doc')
            ->first();
    }

    public function getStandings(?int $turnamenId = null): Collection
    {
        $turnamen = $turnamenId
            ? Turnamen::find($turnamenId)
            : $this->getActiveTournament();

        if (! $turnamen) {
            return collect();
        }

        $grupQuery = $turnamen->isMahjong()
            ? $turnamen->activeGrup()
            : $turnamen->grup();

        return $grupQuery
            ->with(['members' => function ($query) use ($turnamen) {
                $query->with(array_merge(
                    ['pemain', 'turnamenPeserta.pemain1'],
                    TurnamenPeserta::partnerPemainEagerLoadsFor('turnamenPeserta')
                ));

                if ($turnamen->isMahjong()) {
                    $query->orderByDesc('poin_akumulasi')
                        ->orderByDesc('poin_didapat');
                } else {
                    $query->orderByDesc('poin_didapat')
                        ->orderByDesc('set_menang')
                        ->orderByDesc('games_menang');
                }
            }])
            ->orderBy('nama')
            ->get()
            ->map(function (Grup $grup) use ($turnamen) {
                return [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'babak' => $grup->babak,
                    'is_double' => $turnamen->isDouble(),
                    'is_mahjong' => $turnamen->isMahjong(),
                    'standings' => $grup->members->values()->map(function ($member, $index) use ($turnamen) {
                        $row = [
                            'rank' => $index + 1,
                            'id_pemain' => $member->id_pemain,
                            'id_peserta' => $member->id_turnamen_peserta,
                            'pemain_ids' => $this->resolveStandingPemainIds($member),
                            'nama' => $member->display_name,
                            'poin_didapat' => $member->poin_didapat,
                            'set_menang' => $member->set_menang,
                            'games_menang' => $member->games_menang,
                        ];

                        if ($turnamen->isMahjong()) {
                            $row['poin_akumulasi'] = (int) $member->poin_akumulasi;
                            $row['total_poin'] = $member->total_poin;
                        }

                        return $row;
                    }),
                ];
            });
    }

    public function getMahjongGlobalStandings(?int $turnamenId = null): Collection
    {
        $turnamen = $turnamenId
            ? Turnamen::find($turnamenId)
            : $this->getActiveTournament();

        if (! $turnamen || ! $turnamen->isMahjong()) {
            return collect();
        }

        $members = $this->collectMahjongStandingMembers($turnamen);

        if ($members->isNotEmpty()) {
            return $members
                ->sortByDesc(function (GrupMember $member) {
                    return $member->total_poin;
                })
                ->values()
                ->map(function (GrupMember $member, $index) {
                    return $this->formatMahjongStandingRow($member, $index + 1);
                });
        }

        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->approved()
            ->with('pemain1')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (TurnamenPeserta $peserta, $index) {
                return [
                    'rank' => $index + 1,
                    'id_pemain' => $peserta->id_pemain1,
                    'id_peserta' => $peserta->id,
                    'pemain_ids' => $peserta->pemainIds(),
                    'nama' => $peserta->display_name,
                    'grup_nama' => null,
                    'poin_akumulasi' => 0,
                    'poin_didapat' => 0,
                    'poin_babak' => 0,
                    'total_poin' => 0,
                ];
            });
    }

    public function getMahjongStandingsByBabak(?int $turnamenId = null): array
    {
        $turnamen = $turnamenId
            ? Turnamen::find($turnamenId)
            : $this->getActiveTournament();

        if (! $turnamen || ! $turnamen->isMahjong()) {
            return [
                'sections' => collect(),
                'recap' => collect(),
                'babak_numbers' => collect(),
            ];
        }

        $babakNumbers = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->distinct()
            ->orderByDesc('babak')
            ->pluck('babak');

        $sections = $babakNumbers->map(function ($babak) use ($turnamen) {
            $table = $this->buildMahjongBabakTable($turnamen, (int) $babak);
            $groups = $this->resolveMahjongGrupBatchForBabak($turnamen, (int) $babak);

            return [
                'babak' => (int) $babak,
                'is_active' => $groups->contains(function (Grup $grup) {
                    return $grup->is_aktif;
                }),
                'rounds' => $table['rounds'],
                'rows' => $table['rows'],
                'groups' => collect(),
                'recap' => $table['rows'],
            ];
        })->sortByDesc('babak')->values();

        return [
            'sections' => $sections,
            'recap' => $sections->map(function (array $section) {
                return [
                    'babak' => $section['babak'],
                    'is_active' => $section['is_active'],
                    'rounds' => $section['rounds'],
                    'standings' => $section['rows'],
                ];
            })->values(),
            'babak_numbers' => $babakNumbers->values(),
        ];
    }

    public function buildMahjongBabakTable(Turnamen $turnamen, int $babak): array
    {
        $roundBatches = $this->getMahjongRoundBatchesForBabak($turnamen, $babak);

        if ($roundBatches->isEmpty()) {
            return [
                'rounds' => collect(),
                'rows' => collect(),
            ];
        }

        $rounds = $roundBatches->values()->map(function ($batch, int $index) {
            return [
                'round' => $index + 1,
                'label' => 'Ronde ' . ($index + 1),
            ];
        });

        $pesertaIds = $roundBatches
            ->flatMap(function (Collection $batch) {
                return $batch->flatMap(function (Grup $grup) {
                    return $grup->members->pluck('id_turnamen_peserta');
                });
            })
            ->unique()
            ->filter()
            ->values();

        $rows = $pesertaIds->map(function ($pesertaId) use ($roundBatches, $babak, $turnamen) {
            $roundScores = [];
            $latestMember = null;

            foreach ($roundBatches as $roundIndex => $batch) {
                $member = $this->findMahjongMemberInBatch($batch, (int) $pesertaId);

                if ($member) {
                    $latestMember = $member;
                    $roundScores[] = $this->resolveMahjongRoundPoints(
                        $member,
                        $roundBatches,
                        (int) $roundIndex,
                        $babak,
                        $turnamen
                    );
                } else {
                    $roundScores[] = 0;
                }
            }

            if (! $latestMember) {
                return null;
            }

            $totalBabak = array_sum($roundScores);

            return array_merge($this->formatMahjongStandingRow($latestMember, 0), [
                'round_scores' => $roundScores,
                'total_babak' => $totalBabak,
                'poin_babak' => $totalBabak,
                'total_poin' => $this->resolveMahjongTotalPoints(
                    $latestMember,
                    $totalBabak,
                    $babak,
                    $turnamen
                ),
            ]);
        })
            ->filter()
            ->pipe(function (Collection $rows) {
                return $this->mahjongRanker->rankRows($rows);
            });

        return [
            'rounds' => $rounds,
            'rows' => $rows,
        ];
    }

    public function buildMahjongBabakRecap(Collection $groups): Collection
    {
        return $groups
            ->flatMap(function (array $grup) {
                return $grup['standings'];
            })
            ->sortByDesc(function (array $row) {
                return (int) ($row['poin_babak'] ?? $row['poin_didapat'] ?? 0);
            })
            ->values()
            ->map(function (array $row, $index) {
                return [
                    'rank' => $index + 1,
                    'id_pemain' => $row['id_pemain'],
                    'id_peserta' => $row['id_peserta'],
                    'pemain_ids' => $row['pemain_ids'] ?? [],
                    'nama' => $row['nama'],
                    'grup_nama' => $row['grup_nama'] ?? null,
                    'poin_babak' => (int) ($row['poin_babak'] ?? $row['poin_didapat'] ?? 0),
                    'total_poin' => (int) ($row['total_poin'] ?? 0),
                ];
            });
    }

    protected function getMahjongRoundBatchesForBabak(Turnamen $turnamen, int $babak): Collection
    {
        $groups = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('babak', $babak)
            ->with(array_merge(
                ['members.pemain', 'members.turnamenPeserta.pemain1'],
                TurnamenPeserta::partnerPemainEagerLoadsFor('members.turnamenPeserta')
            ))
            ->orderBy('ronde')
            ->orderBy('id')
            ->get();

        return $groups
            ->groupBy(function (Grup $grup) {
                return (int) ($grup->ronde ?: 1);
            })
            ->sortKeys()
            ->values();
    }

    protected function findMahjongMemberInBatch(Collection $batch, int $pesertaId): ?GrupMember
    {
        foreach ($batch as $grup) {
            $member = $grup->members->firstWhere('id_turnamen_peserta', $pesertaId);

            if ($member) {
                return $member;
            }
        }

        return null;
    }

    protected function resolveMahjongRoundPoints(
        GrupMember $member,
        Collection $roundBatches,
        int $roundIndex,
        int $babak,
        Turnamen $turnamen
    ): int {
        if ($member->grup && $member->grup->is_aktif) {
            return (int) $member->poin_didapat;
        }

        if ((int) $member->poin_didapat !== 0) {
            return (int) $member->poin_didapat;
        }

        $startTotal = $this->resolveMahjongRoundStartTotal(
            $member->id_turnamen_peserta,
            $roundBatches,
            $roundIndex,
            $babak,
            $turnamen
        );

        return (int) $member->poin_akumulasi - $startTotal;
    }

    protected function resolveMahjongRoundStartTotal(
        ?int $pesertaId,
        Collection $roundBatches,
        int $roundIndex,
        int $babak,
        Turnamen $turnamen
    ): int {
        // Each babak resets the scoreboard. Ronde 1 always starts from 0.
        if ($roundIndex <= 0) {
            return 0;
        }

        $prevBatch = $roundBatches->get($roundIndex - 1);

        if ($prevBatch && $pesertaId) {
            $prevMember = $this->findMahjongMemberInBatch($prevBatch, $pesertaId);

            if ($prevMember) {
                // After commit, total sits in poin_akumulasi (poin_didapat = 0).
                // Before commit on a historical row, total is akumulasi + didapat.
                return (int) $prevMember->poin_akumulasi + (int) $prevMember->poin_didapat;
            }
        }

        return 0;
    }

    protected function resolveMahjongGrupBatchForBabak(Turnamen $turnamen, int $babak): Collection
    {
        $relations = array_merge(
            ['members.pemain', 'members.turnamenPeserta.pemain1'],
            TurnamenPeserta::partnerPemainEagerLoadsFor('members.turnamenPeserta')
        );

        $active = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('babak', $babak)
            ->where('is_aktif', true)
            ->with($relations)
            ->orderBy('nama')
            ->get();

        if ($active->isNotEmpty()) {
            return $active;
        }

        $latestCreatedAt = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('babak', $babak)
            ->where('is_aktif', false)
            ->max('created_at');

        if (! $latestCreatedAt) {
            return collect();
        }

        return Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('babak', $babak)
            ->where('is_aktif', false)
            ->where('created_at', $latestCreatedAt)
            ->with($relations)
            ->orderBy('nama')
            ->get();
    }

    protected function resolveMahjongTotalPoints(GrupMember $member, int $babakPoints, int $babak, Turnamen $turnamen): int
    {
        if ($member->grup && $member->grup->is_aktif) {
            return $member->total_poin;
        }

        if ((int) $member->poin_didapat !== 0) {
            return (int) $member->poin_akumulasi + (int) $member->poin_didapat;
        }

        return (int) $member->poin_akumulasi;
    }

    protected function collectMahjongStandingMembers(Turnamen $turnamen): Collection
    {
        $activeMembers = GrupMember::query()
            ->whereHas('grup', function ($query) use ($turnamen) {
                $query->where('id_turnamen', $turnamen->id)->where('is_aktif', true);
            })
            ->with(array_merge(
                ['pemain', 'turnamenPeserta.pemain1', 'grup'],
                TurnamenPeserta::partnerPemainEagerLoadsFor('turnamenPeserta')
            ))
            ->get();

        if ($activeMembers->isNotEmpty()) {
            return $activeMembers;
        }

        $latestBabak = $turnamen->grup()->max('babak');

        if (! $latestBabak) {
            return collect();
        }

        return GrupMember::query()
            ->whereHas('grup', function ($query) use ($turnamen, $latestBabak) {
                $query->where('id_turnamen', $turnamen->id)->where('babak', $latestBabak);
            })
            ->with(array_merge(
                ['pemain', 'turnamenPeserta.pemain1', 'grup'],
                TurnamenPeserta::partnerPemainEagerLoadsFor('turnamenPeserta')
            ))
            ->get();
    }

    protected function formatMahjongStandingRow(GrupMember $member, int $rank): array
    {
        return [
            'rank' => $rank,
            'id_pemain' => $member->id_pemain,
            'id_peserta' => $member->id_turnamen_peserta,
            'pemain_ids' => $this->resolveStandingPemainIds($member),
            'nama' => $member->display_name,
            'grup_nama' => optional($member->grup)->nama,
            'poin_akumulasi' => (int) $member->poin_akumulasi,
            'poin_didapat' => (int) $member->poin_didapat,
            'poin_babak' => (int) $member->poin_didapat,
            'total_poin' => $member->total_poin,
        ];
    }

    protected function resolveStandingPemainIds($member): array
    {
        if ($member->turnamenPeserta) {
            return $member->turnamenPeserta->pemainIds();
        }

        return $member->id_pemain ? [(int) $member->id_pemain] : [];
    }
}
