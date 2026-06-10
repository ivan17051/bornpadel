<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePemainRequest;
use App\Models\Pemain;
use App\Models\Pertandingan;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PemainController extends Controller
{
    public function index(Request $request)
    {
        $query = Pemain::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        $pemain = $query->paginate(15)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $pemain,
            ]);
        }

        return view('admin.pemain.index', compact('pemain'));
    }

    public function update(UpdatePemainRequest $request, Pemain $pemain)
    {
        $data = $request->validated();

        if (isset($data['tgl_lahir'])) {
            $data['usia'] = Carbon::parse($data['tgl_lahir'])->age;
        }

        $pemain->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data pemain berhasil diperbarui.',
                'data' => $pemain->fresh(),
            ]);
        }

        return back()->with('success', 'Data pemain berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Pemain $pemain)
    {
        $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
        ]);

        $pemain->update(['status' => $request->status]);

        $messages = [
            'approved' => 'Pemain berhasil disetujui.',
            'rejected' => 'Pemain ditolak.',
            'pending' => 'Status pemain dikembalikan ke pending.',
        ];

        return response()->json([
            'success' => true,
            'message' => $messages[$request->status],
            'data' => $pemain->fresh(),
        ]);
    }

    public function destroy(Pemain $pemain)
    {
        $inMatches = Pertandingan::where('id_pemain1', $pemain->id)
            ->orWhere('id_pemain2', $pemain->id)
            ->orWhere('id_pemenang', $pemain->id)
            ->exists();

        if ($inMatches) {
            return response()->json([
                'success' => false,
                'message' => 'Pemain tidak dapat dihapus karena sudah terdaftar dalam pertandingan.',
            ], 422);
        }

        $pemain->delete();

        return response()->json([
            'success' => true,
            'message' => 'Profil pemain berhasil dihapus.',
        ]);
    }
}
