<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Read-only customer directory for Administrators and Logistics Operators.
 * This avoids granting Operators access to the Admin-only user-management endpoints.
 */
class CustomerDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()
            ->where('role', User::ROLE_CUSTOMER)
            ->where('is_active', true)
            ->select(['id', 'name', 'email', 'role', 'is_active'])
            ->orderBy('name');

        if (!empty($data['search'])) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', '%'.$data['search'].'%')
                ->orWhere('email', 'like', '%'.$data['search'].'%'));
        }

        return response()->json($query->paginate($data['per_page'] ?? 50)->withQueryString());
    }
}
