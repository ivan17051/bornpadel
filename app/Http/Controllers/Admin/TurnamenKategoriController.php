<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use App\Services\TurnamenKategoriService;
use Illuminate\Http\Request;
use RuntimeException;

class TurnamenKategoriController extends Controller
{
    protected $kategoriService;

    public function __construct(TurnamenKategoriService $kategoriService)
    {
        $this->kategoriService = $kategoriService;
    }

    public function store(Request $request, Turnamen $turnamen)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:255'],
            'harga' => ['nullable', 'numeric', 'min:0'],
            'maks_peserta' => ['nullable', 'integer', 'min:1'],
            'urutan' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('open_kategori_modal', true);
        }

        $data = $validator->validated();

        try {
            $kategori = $this->kategoriService->create($turnamen, $data);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->with('open_kategori_modal', true)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.turnamen.edit', $turnamen)
            ->with('success', 'Kategori “' . $kategori->nama . '” ditambahkan sebagai draft. Klik “Buka” untuk membuka pendaftaran.');
    }

    public function update(Request $request, Turnamen $turnamen, TurnamenKategori $kategori)
    {
        $this->assertBelongsToTurnamen($turnamen, $kategori);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:255'],
            'harga' => ['required', 'numeric', 'min:0'],
            'maks_peserta' => ['nullable', 'integer', 'min:1'],
            'urutan' => ['nullable', 'integer', 'min:1'],
            'players_per_group' => ['nullable', 'integer', 'min:' . Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('open_kategori_edit_id', $kategori->id);
        }

        $data = $validator->validated();

        try {
            $this->kategoriService->update($kategori, $data);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->with('open_kategori_edit_id', $kategori->id)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.turnamen.edit', $turnamen)
            ->with('success', 'Kategori diperbarui.');
    }

    public function destroy(Turnamen $turnamen, TurnamenKategori $kategori)
    {
        $this->assertBelongsToTurnamen($turnamen, $kategori);

        try {
            $this->kategoriService->delete($kategori);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.turnamen.edit', $turnamen)
            ->with('success', 'Kategori dihapus.');
    }

    public function updateStatus(Request $request, Turnamen $turnamen, TurnamenKategori $kategori)
    {
        $this->assertBelongsToTurnamen($turnamen, $kategori);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:draft,open'],
        ]);

        try {
            $this->kategoriService->transitionStatus($kategori, $data['status']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $labels = [
            'open' => 'dibuka (open)',
            'draft' => 'dikembalikan ke draft',
        ];
        $label = $labels[$data['status']] ?? $data['status'];

        return redirect()
            ->route('admin.turnamen.edit', $turnamen)
            ->with('success', 'Kategori “' . $kategori->fresh()->nama . '” ' . $label . '.');
    }

    protected function assertBelongsToTurnamen(Turnamen $turnamen, TurnamenKategori $kategori): void
    {
        if ((int) $kategori->id_turnamen !== (int) $turnamen->id) {
            abort(404);
        }
    }
}
