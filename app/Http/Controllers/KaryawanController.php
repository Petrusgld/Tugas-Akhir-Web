<?php

namespace App\Http\Controllers;

use App\Helpers\Format;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class KaryawanController extends Controller
{
    protected ApiService $api;

    /**
     * Aturan password: minimal 8 karakter, kombinasi huruf besar, huruf kecil,
     * angka, dan simbol khusus.
     */
    public const PASSWORD_HINT = 'Password harus terdiri dari minimal 8 karakter, kombinasi huruf besar, huruf kecil, angka, dan simbol khusus (misal: Kpi@2024).';

    public function __construct(ApiService $api)
    {
        $this->api = $api;
    }

    protected function passwordRule(bool $required = true): array
    {
        $rule = Password::min(8)->mixedCase()->numbers()->symbols();

        return $required ? ['required', 'string', $rule] : ['nullable', 'string', $rule];
    }

    public function index(Request $request)
    {
        $users      = [];
        $unitBisnis = [];
        $error      = null;

        try {
            $unitBisnis = $this->api->get('/unit-bisnis')['data'] ?? [];
        } catch (\Exception $e) {
            //
        }

        $unitById = collect($unitBisnis)->keyBy(fn ($u) => Format::pick($u, ['id', 'unit_bisnis_id']));

        try {
            $rawUsers = $this->api->get('/users')['data'] ?? [];

            // Normalisasi: field API terkadang berbeda nama (name/nama, unit_bisnis.nama/
            // unit_bisnis_nama, status/aktif/is_active). Kita samakan di sini supaya view
            // tidak perlu menebak-nebak, dan supaya toggle status ditampilkan dengan benar.
            $users = array_map(function ($u) use ($unitById) {
                $unitId = Format::pick($u, ['unit_bisnis_id', 'unit_bisnis.id']);
                $unit   = $unitId !== null ? $unitById->get($unitId) : null;

                $u['name']           = Format::pick($u, ['name', 'nama']);
                $u['unit_bisnis_id'] = $unitId;
                $u['unit_bisnis_nama'] = Format::pick($u, ['unit_bisnis_nama', 'unit_bisnis.nama'])
                    ?? Format::pick($unit, ['nama', 'unit_bisnis_nama']);
                $u['is_aktif'] = Format::isAktif($u);

                return $u;
            }, $rawUsers);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return view('karyawan.index', compact('users', 'unitBisnis', 'error'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email',
            'password' => $this->passwordRule(true),
            'role'     => 'required|in:admin,owner,karyawan,manajer',
            'unit_bisnis_id' => 'nullable',
        ], [
            'password.min'    => self::PASSWORD_HINT,
            'password.mixed'  => self::PASSWORD_HINT,
            'password.letters'=> self::PASSWORD_HINT,
            'password.numbers'=> self::PASSWORD_HINT,
            'password.symbols'=> self::PASSWORD_HINT,
        ]);

        try {
            $this->api->post('/users', $request->only('name', 'email', 'password', 'role', 'unit_bisnis_id'));
            return back()->with('success', 'Akun berhasil dibuat.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email',
            'password' => $this->passwordRule(false),
            'role'     => 'required|in:admin,owner,karyawan,manajer',
            'unit_bisnis_id' => 'nullable',
        ], [
            'password.min'    => self::PASSWORD_HINT,
            'password.mixed'  => self::PASSWORD_HINT,
            'password.letters'=> self::PASSWORD_HINT,
            'password.numbers'=> self::PASSWORD_HINT,
            'password.symbols'=> self::PASSWORD_HINT,
        ]);

        try {
            $payload = $request->only('name', 'email', 'role', 'unit_bisnis_id');
            if ($request->filled('password')) {
                $payload['password'] = $request->password;
            }
            $this->api->put("/users/{$id}", $payload);
            return back()->with('success', 'Akun berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->api->delete("/users/{$id}");
            return back()->with('success', 'Akun berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function toggle($id)
    {
        try {
            $this->api->patch("/users/{$id}/toggle");
            return back()->with('success', 'Status akun berhasil diubah.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
