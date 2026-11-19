<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Public registration can create CUSTOMER accounts only.
        $data['email'] = strtolower($data['email']);
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data + ['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
            Customer::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
            return $user;
        });
        $token = $user->createToken('cargotrace-client')->plainTextToken;

        return response()->json(['message' => 'Registrasi berhasil.', 'user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', strtolower($data['email']))->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Email atau password tidak sesuai.']]);
        }
        if (!$user->is_active) {
            return response()->json(['message' => 'Akun sedang dinonaktifkan.'], 403);
        }

        $token = $user->createToken('cargotrace-client')->plainTextToken;
        return response()->json(['message' => 'Login berhasil.', 'user' => $user, 'token' => $token]);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => $request->user()]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logout berhasil.']);
    }
}
