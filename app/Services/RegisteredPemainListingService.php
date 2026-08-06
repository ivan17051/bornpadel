<?php

namespace App\Services;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RegisteredPemainListingService
{
    public function paginate(Request $request, ?Turnamen $turnamen, $idKategori = null): array
    {
        $isDoubleView = $turnamen && $turnamen->playsAsPairs() && $turnamen->isRegistrationClosed();
        $kategoriId = $turnamen
            ? (int) $turnamen->resolveKategori($idKategori ?? ($request->filled('id_kategori') ? (int) $request->id_kategori : null))->id
            : null;

        if (! $turnamen) {
            return [
                'pemain' => Pemain::query()->whereRaw('0 = 1')->paginate(15)->withQueryString(),
                'peserta' => null,
                'isDoubleView' => false,
                'soloPesertaOptions' => collect(),
            ];
        }

        if ($isDoubleView) {
            $pesertaQuery = TurnamenPeserta::query()
                ->forKategori($kategoriId)
                ->whereHas('pasanganAsPeserta1')
                ->with(['pemain1', 'pasanganAsPeserta1.peserta2.pemain1']);

            if ($request->filled('status')) {
                $pesertaQuery->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $pesertaQuery->where(function ($builder) use ($search) {
                    $builder->whereHas('pemain1', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_hp', 'like', "%{$search}%");
                    })->orWhereHas('pasanganAsPeserta1.peserta2.pemain1', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_hp', 'like', "%{$search}%");
                    });
                });
            }

            $this->applyPesertaPairSort($pesertaQuery, $request);

            return [
                'pemain' => null,
                'peserta' => $pesertaQuery->paginate(15)->withQueryString(),
                'isDoubleView' => true,
                'soloPesertaOptions' => collect(),
            ];
        }

        $query = Pemain::query();

        $query->where(function ($builder) use ($kategoriId, $request) {
            $builder->whereHas('turnamenPesertaAsPemain1', function ($q) use ($kategoriId, $request) {
                $q->where('id_kategori', $kategoriId);
                if ($request->filled('status')) {
                    $q->where('status', $request->status);
                }
            });
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        $query->with([
            'turnamenPesertaAsPemain1' => function ($q) use ($kategoriId, $request, $turnamen) {
                $q->where('id_kategori', $kategoriId);

                if ($request->filled('status')) {
                    $q->where('status', $request->status);
                }

                if ($turnamen->requiresPairRegistration()) {
                    $q->with(TurnamenPeserta::partnerPemainEagerLoads());
                }

                if ($turnamen->allowsGroupRegistration()) {
                    $q->with('grupPendaftaranMember.grupPendaftaran');
                }
            },
        ]);

        $this->applyPemainSort($query, $request, $turnamen, $kategoriId);

        return [
            'pemain' => $query->paginate(15)->withQueryString(),
            'peserta' => null,
            'isDoubleView' => false,
            'soloPesertaOptions' => $this->resolveSoloPesertaOptions($turnamen, $kategoriId),
        ];
    }

    protected function sortDirection(Request $request): string
    {
        return strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
    }

    protected function applyPemainSort(Builder $query, Request $request, Turnamen $turnamen, $kategoriId = null): void
    {
        $sort = (string) $request->query('sort', '');
        $dir = $this->sortDirection($request);
        $kategoriId = $kategoriId
            ? (int) $kategoriId
            : (int) $turnamen->resolveKategori()->id;

        $allowed = ['nama', 'no_hp', 'gender', 'rating', 'status'];

        if ($turnamen->requiresPairRegistration()) {
            $allowed[] = 'partner';
        }

        if (! in_array($sort, $allowed, true)) {
            if ($turnamen->allowsGroupRegistration()) {
                $this->applyFriendlyGroupDefaultSort($query, $turnamen, $kategoriId);
            } else {
                $query->latest();
            }

            return;
        }

        if ($sort === 'status') {
            $query->select('m_pemain.*')
                ->join('turnamen_peserta as tp_sort', function ($join) use ($kategoriId) {
                    $join->on('m_pemain.id', '=', 'tp_sort.id_pemain1')
                        ->where('tp_sort.id_kategori', '=', $kategoriId);
                })
                ->orderBy('tp_sort.status', $dir);

            return;
        }

        if ($sort === 'partner') {
            $query->select('m_pemain.*')
                ->join('turnamen_peserta as tp_partner', function ($join) use ($kategoriId) {
                    $join->on('m_pemain.id', '=', 'tp_partner.id_pemain1')
                        ->where('tp_partner.id_kategori', '=', $kategoriId);
                })
                ->leftJoin('turnamen_pasangan as tp_pair', 'tp_pair.id_peserta_1', '=', 'tp_partner.id')
                ->leftJoin('turnamen_peserta as tp_partner_row', 'tp_partner_row.id', '=', 'tp_pair.id_peserta_2')
                ->leftJoin('m_pemain as partner_pemain', 'partner_pemain.id', '=', 'tp_partner_row.id_pemain1')
                ->orderBy('partner_pemain.nama', $dir);

            return;
        }

        $query->orderBy($sort, $dir);
    }

    protected function applyFriendlyGroupDefaultSort(Builder $query, Turnamen $turnamen, $kategoriId = null): void
    {
        $kategoriId = $kategoriId
            ? (int) $kategoriId
            : (int) $turnamen->resolveKategori()->id;

        $query->select('m_pemain.*')
            ->join('turnamen_peserta as tp_fg', function ($join) use ($kategoriId) {
                $join->on('m_pemain.id', '=', 'tp_fg.id_pemain1')
                    ->where('tp_fg.id_kategori', '=', $kategoriId);
            })
            ->leftJoin('turnamen_grup_pendaftaran_member as tgm', 'tgm.id_peserta', '=', 'tp_fg.id')
            ->leftJoin('turnamen_grup_pendaftaran as tgp', 'tgp.id', '=', 'tgm.id_grup_pendaftaran')
            ->orderByRaw('CASE WHEN tgp.id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('tgp.nama')
            ->orderBy('tgm.urutan')
            ->orderBy('m_pemain.nama');
    }

    protected function applyPesertaPairSort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', '');
        $dir = $this->sortDirection($request);

        $allowed = [
            'pemain1_nama',
            'pemain1_gender',
            'pemain1_rating',
            'pemain2_nama',
            'pemain2_gender',
            'pemain2_rating',
            'status',
        ];

        if (! in_array($sort, $allowed, true)) {
            $query->latest('turnamen_peserta.id');

            return;
        }

        $query->select('turnamen_peserta.*');

        if (in_array($sort, ['pemain1_nama', 'pemain1_gender', 'pemain1_rating'], true)) {
            $query->join('m_pemain as sort_p1', 'sort_p1.id', '=', 'turnamen_peserta.id_pemain1');
            $field = $sort === 'pemain1_nama' ? 'nama' : substr($sort, strlen('pemain1_'));
            $query->orderBy('sort_p1.' . $field, $dir);

            return;
        }

        if (in_array($sort, ['pemain2_nama', 'pemain2_gender', 'pemain2_rating'], true)) {
            $query->join('turnamen_pasangan as sort_pair', 'sort_pair.id_peserta_1', '=', 'turnamen_peserta.id')
                ->join('turnamen_peserta as sort_tp2', 'sort_tp2.id', '=', 'sort_pair.id_peserta_2')
                ->join('m_pemain as sort_p2', 'sort_p2.id', '=', 'sort_tp2.id_pemain1');
            $field = $sort === 'pemain2_nama' ? 'nama' : substr($sort, strlen('pemain2_'));
            $query->orderBy('sort_p2.' . $field, $dir);

            return;
        }

        $query->orderBy('turnamen_peserta.status', $dir);
    }

    protected function resolveSoloPesertaOptions(Turnamen $turnamen, $idKategori = null)
    {
        if (! $turnamen->requiresPairRegistration() || $turnamen->isRegistrationClosed()) {
            return collect();
        }

        return app(PemainRegistrationService::class)->getSoloPesertaOptions($turnamen, $idKategori);
    }
}
