<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Turnamen;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->with(['turnamen', 'assignedTurnamen'])
            ->where('username', '!=', 'testacc')->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->paginate(15)->withQueryString();

        return view('admin.pengguna.index', compact('users'));
    }

    public function create()
    {
        $turnamenList = Turnamen::orderByDesc('doc')->get();

        return view('admin.pengguna.create', compact('turnamenList'));
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $turnamenIds = $this->extractTurnamenIds($data);

        unset($data['id_turnamen']);
        $data['id_turnamen'] = $data['role'] === 'admin' ? null : ($turnamenIds[0] ?? null);

        $user = User::create($data);
        $user->syncAssignedTurnamen($turnamenIds);

        return redirect()
            ->route('admin.pengguna.index')
            ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $user->load('assignedTurnamen');
        $turnamenList = Turnamen::orderByDesc('doc')->get();

        return view('admin.pengguna.edit', [
            'user' => $user,
            'turnamenList' => $turnamenList,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $turnamenIds = $this->extractTurnamenIds($data);
        unset($data['id_turnamen']);
        $data['id_turnamen'] = $data['role'] === 'admin' ? null : ($turnamenIds[0] ?? null);

        $user->update($data);
        $user->syncAssignedTurnamen($turnamenIds);

        return redirect()
            ->route('admin.pengguna.index')
            ->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if (Auth::id() === $user->id) {
            return back()->with('error', 'Tidak dapat menghapus akun yang sedang login.');
        }

        $user->delete();

        return redirect()
            ->route('admin.pengguna.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }

    protected function extractTurnamenIds(array $data): array
    {
        if (($data['role'] ?? null) !== 'panitia') {
            return [];
        }

        $ids = $data['id_turnamen'] ?? [];

        if (! is_array($ids)) {
            $ids = $ids ? [(int) $ids] : [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}
