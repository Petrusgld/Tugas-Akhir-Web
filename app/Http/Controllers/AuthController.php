<?php

namespace App\Http\Controllers;

use App\Services\ApiService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected ApiService $api;

    public function __construct(ApiService $api)
    {
        $this->api = $api;
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            $response = $this->api->post('/login', [
                'email'    => $request->email,
                'password' => $request->password,
            ]);

            // API mengembalikan token & user langsung di root (bukan dibungkus "data")
            $user  = $response['user']  ?? $response['data']['user']  ?? null;
            $token = $response['token'] ?? $response['data']['token'] ?? null;

            if (!$user || !$token) {
                return back()->with('error', 'Login gagal. Periksa kembali email dan password Anda.')->withInput();
            }

            // Hanya admin, owner, atau manajer yang boleh mengakses web admin
            $allowedRoles = ['admin', 'owner', 'manajer'];
            if (!in_array($user['role'] ?? '', $allowedRoles)) {
                return back()->with('error', 'Akun Anda tidak memiliki akses ke Web Admin. Hanya admin, owner, dan manajer yang diizinkan.')->withInput();
            }

            session([
                'api_token' => $token,
                'user'      => $user,
            ]);

            return redirect()->route('dashboard')->with('success', 'Selamat datang, ' . ($user['name'] ?? '') . '!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function logout(Request $request)
    {
        try {
            $this->api->post('/logout');
        } catch (\Exception $e) {
            // Abaikan error logout dari API, tetap flush session lokal
        }

        $request->session()->flush();

        return redirect()->route('login')->with('success', 'Anda telah keluar dari sistem.');
    }
}
