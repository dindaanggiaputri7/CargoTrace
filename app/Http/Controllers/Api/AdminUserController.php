<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'role' => ['nullable', Rule::in(User::ROLES)],
            'active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = User::query()->select(['id', 'name', 'email', 'role', 'is_active', 'created_at', 'updated_at']);
        $query->when(isset($data['role']), fn ($q) => $q->where('role', $data['role']));
        $query->when(array_key_exists('active', $data), fn ($q) => $q->where('is_active', $data['active']));
        $query->when(!empty($data['search']), fn ($q) => $q->where(fn ($s) => $s
            ->where('name', 'like', '%'.$data['search'].'%')
            ->orWhere('email', 'like', '%'.$data['search'].'%')));

        return response()->json($query->orderBy('name')->paginate($data['per_page'] ?? 20)->withQueryString());
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['email'] = strtolower($data['email']);
        $data['is_active'] ??= true;
        // User's hashed cast hashes plain text exactly once.
        $user = User::create($data);
        $audit->record($request->user(), 'USER_CREATED', 'User', $user->id, 'Akun baru dibuat.', null,
            $user->only(['name', 'email', 'role', 'is_active']));

        return response()->json(['data' => $user], 201);
    }

    public function update(Request $request, User $user, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if ($user->is($request->user()) && (($data['role'] ?? $user->role) !== $user->role || (array_key_exists('is_active', $data) && !$data['is_active']))) {
            throw ValidationException::withMessages(['user' => ['Admin tidak dapat menonaktifkan atau mengganti role akunnya sendiri.']]);
        }
        $this->protectLastActiveAdmin($user, $data);

        if (isset($data['email'])) $data['email'] = strtolower($data['email']);
        $auditFields = array_diff_key($data, ['password' => true]);
        $old = $user->only(array_keys($auditFields));
        if (empty($data['password'])) unset($data['password']);
        $user->update($data);
        if (array_key_exists('is_active', $data) && !$user->is_active) $user->tokens()->delete();
        $audit->record($request->user(), 'USER_UPDATED', 'User', $user->id, 'Data akun diperbarui.', $old,
            $user->fresh()->only(array_keys($auditFields)));

        return response()->json(['data' => $user->fresh()]);
    }

    public function toggleActive(Request $request, User $user, AuditService $audit)
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => ['Admin tidak dapat menonaktifkan akunnya sendiri.']]);
        }
        $next = !$user->is_active;
        $this->protectLastActiveAdmin($user, ['is_active' => $next]);
        $old = ['is_active' => $user->is_active];
        $user->update(['is_active' => $next]);
        if (!$next) $user->tokens()->delete();
        $audit->record($request->user(), 'USER_ACTIVE_TOGGLED', 'User', $user->id, 'Status akun diubah.', $old, ['is_active' => $next]);

        return response()->json(['data' => $user->fresh()->only(['id', 'name', 'email', 'role', 'is_active'])]);
    }

    private function protectLastActiveAdmin(User $target, array $changes): void
    {
        $willLoseAdmin = $target->role === User::ROLE_ADMIN
            && (($changes['role'] ?? User::ROLE_ADMIN) !== User::ROLE_ADMIN || (array_key_exists('is_active', $changes) && !$changes['is_active']));
        if ($willLoseAdmin && User::query()->where('role', User::ROLE_ADMIN)->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages(['user' => ['Minimal harus ada satu administrator aktif.']]);
        }
    }
}
