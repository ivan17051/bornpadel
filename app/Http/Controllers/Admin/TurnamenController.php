<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyTurnamenRequest;
use App\Http\Requests\Admin\StoreTurnamenRequest;
use App\Http\Requests\Admin\UpdateTurnamenRequest;
use App\Models\Turnamen;
use App\Services\TournamentDeletionService;
use App\Services\TurnamenPhotoService;
use Illuminate\Http\Request;
use RuntimeException;

class TurnamenController extends Controller
{
    protected $deletionService;
    protected $photoService;

    public function __construct(TournamentDeletionService $deletionService, TurnamenPhotoService $photoService)
    {
        $this->deletionService = $deletionService;
        $this->photoService = $photoService;
    }

    public function index(Request $request)
    {
        $query = Turnamen::query()->latest('doc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('nama', 'like', "%{$search}%");
        }

        $turnamen = $query->paginate(15)->withQueryString();

        return view('admin.turnamen.index', compact('turnamen'));
    }

    public function create()
    {
        return view('admin.turnamen.create');
    }

    public function store(StoreTurnamenRequest $request)
    {
        $data = collect($request->validated())->except(['foto'])->all();
        $data = $this->normalizePlayersPerGroup($data);

        if ($request->hasFile('foto')) {
            try {
                $data['foto'] = $this->photoService->storeAsJpeg($request->file('foto'));
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors(['foto' => $e->getMessage()]);
            }
        }

        Turnamen::create($data);

        return redirect()
            ->route('admin.turnamen.index')
            ->with('success', 'Turnamen berhasil ditambahkan.');
    }

    public function edit(Turnamen $turnamen)
    {
        return view('admin.turnamen.edit', compact('turnamen'));
    }

    public function update(UpdateTurnamenRequest $request, Turnamen $turnamen)
    {
        $data = collect($request->validated())->except(['foto', 'remove_foto'])->all();

        if (($data['jenis'] ?? $turnamen->jenis) !== 'friendly') {
            $data['players_per_group'] = null;
        } elseif ($turnamen->canEditFriendlyPlayersPerGroup()) {
            $data = $this->normalizePlayersPerGroup($data);
        } else {
            unset($data['players_per_group']);
        }

        if ($request->boolean('remove_foto') && ! $request->hasFile('foto')) {
            $this->photoService->delete($turnamen->foto);
            $data['foto'] = null;
        }

        if ($request->hasFile('foto')) {
            try {
                $newFoto = $this->photoService->storeAsJpeg($request->file('foto'));
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors(['foto' => $e->getMessage()]);
            }

            $this->photoService->delete($turnamen->foto);
            $data['foto'] = $newFoto;
        }

        $turnamen->update($data);

        return redirect()
            ->route('admin.turnamen.index')
            ->with('success', 'Turnamen berhasil diperbarui.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizePlayersPerGroup(array $data): array
    {
        if (($data['jenis'] ?? null) === 'friendly') {
            $data['players_per_group'] = max(
                Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP,
                (int) ($data['players_per_group'] ?? Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP)
            );
        } else {
            $data['players_per_group'] = null;
        }

        return $data;
    }

    public function destroy(DestroyTurnamenRequest $request, Turnamen $turnamen)
    {
        try {
            $this->deletionService->delete($turnamen, $request->user(), $request->input('password'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.turnamen.index')
            ->with('success', 'Turnamen berhasil dihapus.');
    }
}
