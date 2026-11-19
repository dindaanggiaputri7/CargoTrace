<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        User::query()->where('role', User::ROLE_CUSTOMER)->get(['id', 'name', 'email'])
            ->each(fn (User $user) => Customer::firstOrCreate(['user_id' => $user->id], ['name' => $user->name, 'email' => $user->email]));

        $query = Customer::query()
            ->with('user:id,email,is_active,role')
            ->orderBy('name');

        if (!empty($data['search'])) {
            $search = '%'.$data['search'].'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('company_name', 'like', $search)
                ->orWhere('email', 'like', $search)
                ->orWhere('phone', 'like', $search));
        }

        $page = $query->paginate($data['per_page'] ?? 50)->withQueryString();
        $page->getCollection()->transform(function (Customer $customer) {
            $customer->setAttribute('has_login', (bool) $customer->user_id && $customer->user?->role === User::ROLE_CUSTOMER && (bool) $customer->user?->is_active);
            $customer->setAttribute('login_email', $customer->user?->email);
            return $customer->makeHidden('user');
        });

        return response()->json($page);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_CUSTOMER))],
        ]);

        $customer = Customer::create($data + ['created_by' => $request->user()->id]);

        return response()->json(['message' => 'Customer record berhasil dibuat.', 'data' => $customer], 201);
    }
}
