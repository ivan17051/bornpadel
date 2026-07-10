<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyTurnamenRequest;
use App\Http\Requests\Admin\StoreTurnamenRequest;
use App\Http\Requests\Admin\UpdateTurnamenRequest;
use App\Models\Turnamen;
use App\Services\TournamentDeletionService;
use Illuminate\Http\Request;
use RuntimeException;

class TurnamenController extends Controller
{
    protected $deletionService;

    public function __construct(TournamentDeletionService $deletionService)
    {
        $this->deletionService = $deletionService;
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
        Turnamen::create($request->validated());

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
        $turnamen->update($request->validated());

        return redirect()
            ->route('admin.turnamen.index')
            ->with('success', 'Turnamen berhasil diperbarui.');
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
