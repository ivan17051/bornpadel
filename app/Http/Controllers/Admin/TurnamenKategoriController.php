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
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'harga' => ['nullable', 'numeric', 'min:0'],
            'maks_peserta' => ['nullable', 'integer', 'min:1'],
            'urutan' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $kategori = $this->kategoriService->create($turnamen, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.turnamen.edit', $turnamen)
            ->with('success', 'Kategori “' . $kategori->nama . '” ditambahkan sebagai draft. Klik “Buka” untuk membuka pendaftaran.');
    }

    public function update(Request $request, Turnamen $turnamen, TurnamenKategori $kategori)
    {
        $this->assertBelongsToTurnamen($turnamen, $kategori);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'harga' => ['required', 'numeric', 'min:0'],
            'maks_peserta' => ['nullable', 'integer', 'min:1'],
            'urutan' => ['nullable', 'integer', 'min:1'],
            'players_per_group' => ['nullable', 'integer', 'min:' . Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP],
        ]);

        try {
            $this->kategoriService->update($kategori, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
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
