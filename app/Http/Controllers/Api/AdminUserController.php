<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->select(['id', 'name', 'email', 'role', 'is_active', 'created_at']);
        $query->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')));
        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$request->input('search').'%')->orWhere('email', 'like', '%'.$request->input('search').'%')));
        return response()->json($query->orderBy('name')->paginate(min($request->integer('per_page', 20), 100)));
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR, User::ROLE_CUSTOMER])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        $audit->record($request->user(), 'USER_CREATED', 'User', $user->id, 'Akun baru dibuat.');
        return response()->json(['data' => $user->makeHidden(['password', 'remember_token'])], 201);
    }

    public function update(Request $request, User $user, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR, User::ROLE_CUSTOMER])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if ($user->is($request->user()) && (($data['role'] ?? $user->role) !== $user->role || ($data['is_active'] ?? true) === false)) {
            return response()->json(['message' => 'Admin tidak dapat menonaktifkan atau mengganti role akunnya sendiri.'], 422);
        }
        if (array_key_exists('password', $data) && $data['password']) $data['password'] = Hash::make($data['password']);
        else unset($data['password']);
        $old = $user->only(array_keys($data));
        $user->update($data);
        $audit->record($request->user(), 'USER_UPDATED', 'User', $user->id, 'Data akun diperbarui.', $old, $data);
        return response()->json(['data' => $user->fresh()->makeHidden(['password', 'remember_token'])]);
    }

    public function toggleActive(Request $request, User $user, AuditService $audit)
    {
        abort_if($user->is($request->user()), 422, 'Admin tidak dapat menonaktifkan akunnya sendiri.');
        $user->update(['is_active' => !$user->is_active]);
        $audit->record($request->user(), 'USER_ACTIVE_TOGGLED', 'User', $user->id, 'Status akun diubah.');
        return response()->json(['data' => $user->fresh()->only(['id', 'name', 'email', 'role', 'is_active'])]);
    }
}
