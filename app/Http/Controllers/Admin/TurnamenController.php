<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTurnamenRequest;
use App\Http\Requests\Admin\UpdateTurnamenRequest;
use App\Models\Turnamen;
use Illuminate\Http\Request;

class TurnamenController extends Controller
{
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

    public function destroy(Turnamen $turnamen)
    {
        if ($turnamen->grup()->exists()) {
            return back()->with('error', 'Turnamen tidak dapat dihapus karena sudah memiliki grup dan pertandingan.');
        }

        $turnamen->delete();

        return redirect()
            ->route('admin.turnamen.index')
            ->with('success', 'Turnamen berhasil dihapus.');
    }
}
