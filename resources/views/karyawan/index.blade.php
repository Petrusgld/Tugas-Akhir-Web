@extends('layouts.app')
@section('title', 'Karyawan')

@section('content')
<div x-data="{
        rows: {{ Illuminate\Support\Js::from(array_values($users)) }},
        addOpen: {{ $errors->any() && old('_form') === 'add' ? 'true' : 'false' }},
        editOpen: false,
        deleteOpen: false,
        toggleOpen: false,
        editData: {},
        deleteData: {},
        toggleData: {},
        search: '',
        filterRole: '',
        filterUnit: '',
        filterStatus: '',
        page: 1,
        perPage: 10,
        isAktif(row) {
            if (row.is_aktif !== undefined) return row.is_aktif === true;
            if (row.status !== undefined && row.status !== null) {
                return ['aktif', 'active', '1', 1, true].includes(row.status);
            }
            if (row.aktif !== undefined && row.aktif !== null) return row.aktif === true;
            if (row.is_active !== undefined && row.is_active !== null) return row.is_active === true;
            return true;
        },
        get filtered() {
            return this.rows.filter(r => {
                const text = ((r.name ?? '') + ' ' + (r.email ?? '')).toLowerCase();
                const matchSearch = this.search === '' || text.includes(this.search.toLowerCase());
                const matchRole = this.filterRole === '' || r.role === this.filterRole;
                const matchUnit = this.filterUnit === '' || r.unit_bisnis_nama === this.filterUnit;
                const status = this.isAktif(r) ? 'aktif' : 'nonaktif';
                const matchStatus = this.filterStatus === '' || this.filterStatus === status;
                return matchSearch && matchRole && matchUnit && matchStatus;
            });
        },
        get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
        get paged() { return this.filtered.slice((this.page - 1) * this.perPage, this.page * this.perPage); },
     }"
     x-init="$watch('search', () => page = 1); $watch('filterRole', () => page = 1); $watch('filterUnit', () => page = 1); $watch('filterStatus', () => page = 1)">

    {{-- HEADER --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Manajemen Karyawan</h2>
            <p class="text-sm text-gray-500 mt-0.5">Kelola akun pengguna sistem KPI Master.</p>
        </div>
        <button @click="addOpen = true"
                class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
            Tambah Akun
        </button>
    </div>

    {{-- FILTER BAR --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <input type="text" x-model="search" placeholder="Cari nama / email..."
               class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500 w-56">
        <select x-model="filterRole" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option value="">Semua Role</option>
            <option value="admin">Admin</option>
            <option value="owner">Owner</option>
            <option value="manajer">Manajer</option>
            <option value="karyawan">Karyawan</option>
        </select>
        <select x-model="filterUnit" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option value="">Semua Unit Bisnis</option>
            @foreach ($unitBisnis as $ub)
                <option value="{{ $ub['nama'] ?? '' }}">{{ $ub['nama'] ?? '-' }}</option>
            @endforeach
        </select>
        <select x-model="filterStatus" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option value="">Semua Status</option>
            <option value="aktif">Aktif</option>
            <option value="nonaktif">Nonaktif</option>
        </select>
    </div>

    {{-- TABEL --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-6 py-3 w-14">No</th>
                    <th class="text-left px-6 py-3">Nama</th>
                    <th class="text-left px-6 py-3">Email</th>
                    <th class="text-left px-6 py-3">Role</th>
                    <th class="text-left px-6 py-3">Unit Bisnis</th>
                    <th class="text-left px-6 py-3">Status</th>
                    <th class="text-left px-6 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-if="filtered.length === 0">
                    <tr><td colspan="7" class="px-6 py-8 text-center text-gray-400">Tidak ada data karyawan yang cocok.</td></tr>
                </template>
                <template x-for="(u, i) in paged" :key="u.id">
                    <tr>
                        <td class="px-6 py-3 text-gray-400" x-text="(page - 1) * perPage + i + 1"></td>
                        <td class="px-6 py-3 font-medium text-gray-900" x-text="u.name ?? '-'"></td>
                        <td class="px-6 py-3 text-gray-500" x-text="u.email ?? '-'"></td>
                        <td class="px-6 py-3 capitalize text-gray-500" x-text="u.role ?? '-'"></td>
                        <td class="px-6 py-3 text-gray-500" x-text="u.unit_bisnis_nama ?? '-'"></td>
                        <td class="px-6 py-3">
                            <template x-if="['admin', 'owner'].includes((u.role ?? '').toLowerCase())">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Aktif
                                </span>
                            </template>
                            <template x-if="!['admin', 'owner'].includes((u.role ?? '').toLowerCase())">
                                <button type="button"
                                        @click="toggleOpen = true; toggleData = u"
                                        :class="isAktif(u) ? 'bg-brand-500' : 'bg-red-400'"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                                    <span :class="isAktif(u) ? 'translate-x-5' : 'translate-x-1'"
                                          class="inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
                                </button>
                            </template>
                        </td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3 text-sm">
                                <button @click="editOpen = true; editData = u" class="text-gray-500 hover:text-gray-800 font-medium">Edit</button>
                                <button @click="deleteOpen = true; deleteData = u" class="text-red-500 hover:text-red-700 font-medium">Hapus</button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        {{-- PAGINATION --}}
        <div class="flex items-center justify-between px-6 py-3 border-t border-gray-100 text-sm text-gray-500" x-show="filtered.length > 0">
            <p>Menampilkan <span x-text="paged.length"></span> dari <span x-text="filtered.length"></span> data</p>
            <div class="flex items-center gap-1">
                <button type="button" @click="page = Math.max(1, page - 1)" :disabled="page === 1"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 hover:bg-gray-50">Sebelumnya</button>
                <span class="px-3 text-gray-600">Halaman <span x-text="page"></span> / <span x-text="totalPages"></span></span>
                <button type="button" @click="page = Math.min(totalPages, page + 1)" :disabled="page === totalPages"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 hover:bg-gray-50">Berikutnya</button>
            </div>
        </div>
    </div>

    {{-- MODAL TAMBAH AKUN --}}
    <div x-show="addOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="addOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-md"
             x-data="{
                role: '{{ old('role', '') }}',
                name: '{{ old('name', '') }}',
                email: '{{ old('email', '') }}',
                password: '',
                passwordConfirm: '',
                showPassword: false,
                showPasswordConfirm: false,
                get nameValid() { return this.name.trim().length > 0; },
                get emailValid() { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email); },
                get passwordValid() {
                    return this.password.length >= 8
                        && /[A-Z]/.test(this.password)
                        && /[a-z]/.test(this.password)
                        && /[0-9]/.test(this.password)
                        && /[^A-Za-z0-9]/.test(this.password);
                },
                get passwordMatch() { return this.passwordConfirm.length > 0 && this.password === this.passwordConfirm; },
             }">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Tambah Akun</h3>
            <form action="{{ route('karyawan.store') }}" method="POST" class="space-y-4" novalidate>
                @csrf
                <input type="hidden" name="_form" value="add">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama</label>
                    <input type="text" name="name" x-model="name" required placeholder="Contoh: Andi Wijaya"
                           class="w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                           :class="name.length === 0 ? 'border-gray-300' : (nameValid ? 'border-green-400' : 'border-red-400')">
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-red-500 mt-1" x-show="name.length > 0 && !nameValid" x-cloak>Nama tidak boleh kosong.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email" x-model="email" required placeholder="contoh@emanuelcorp.com"
                           class="w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                           :class="email.length === 0 ? 'border-gray-300' : (emailValid ? 'border-green-400' : 'border-red-400')">
                    @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-red-500 mt-1" x-show="email.length > 0 && !emailValid" x-cloak>Format email belum valid.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" name="password" x-model="password" required placeholder="Minimal 8 karakter"
                                   class="w-full pl-4 pr-10 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                                   :class="password.length === 0 ? 'border-gray-300' : (passwordValid ? 'border-green-400' : 'border-red-400')">
                            <button type="button" @click="showPassword = !showPassword" tabindex="-1"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.7 18.7 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Konfirmasi</label>
                        <div class="relative">
                            <input :type="showPasswordConfirm ? 'text' : 'password'" name="password_confirmation" x-model="passwordConfirm" required placeholder="Ulangi password"
                                   class="w-full pl-4 pr-10 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                                   :class="passwordConfirm.length === 0 ? 'border-gray-300' : (passwordMatch ? 'border-green-400' : 'border-red-400')">
                            <button type="button" @click="showPasswordConfirm = !showPasswordConfirm" tabindex="-1"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg x-show="!showPasswordConfirm" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="showPasswordConfirm" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.7 18.7 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <p class="text-xs -mt-2" :class="password.length === 0 ? 'text-gray-400' : (passwordValid ? 'text-green-600' : 'text-red-500')">
                    Password harus terdiri dari minimal 8 karakter, kombinasi huruf besar, huruf kecil, angka, dan simbol khusus (misal: Kpi@2024).
                </p>
                @error('password')<p class="text-xs text-red-500 -mt-2">{{ $message }}</p>@enderror
                <p class="text-xs text-red-500 -mt-2" x-show="passwordConfirm.length > 0 && !passwordMatch" x-cloak>Konfirmasi password tidak sama.</p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Role</label>
                    <select name="role" x-model="role" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Pilih role</option>
                        <option value="admin">Admin</option>
                        <option value="owner">Owner</option>
                        <option value="manajer">Manajer</option>
                        <option value="karyawan">Karyawan</option>
                    </select>
                    @error('role')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div x-show="role === 'karyawan' || role === 'manajer'" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Unit Bisnis</label>
                    <select name="unit_bisnis_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Pilih unit bisnis</option>
                        @foreach ($unitBisnis as $ub)
                            <option value="{{ $ub['id'] }}">{{ $ub['nama'] ?? '-' }}</option>
                        @endforeach
                    </select>
                    @if (empty($unitBisnis))
                        <p class="text-xs text-yellow-600 mt-1">Belum ada unit bisnis yang bisa dipilih. Tambahkan unit bisnis terlebih dahulu.</p>
                    @endif
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="addOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                    <button type="submit"
                            :disabled="!nameValid || !emailValid || !passwordValid || !passwordMatch || role === ''"
                            :class="(!nameValid || !emailValid || !passwordValid || !passwordMatch || role === '') ? 'opacity-40 cursor-not-allowed' : ''"
                            class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL EDIT AKUN --}}
    <div x-show="editOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="editOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-md">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Edit Akun</h3>
            <form :action="'/karyawan/' + editData.id" method="POST" class="space-y-4" x-data="{ newPassword: '', showPassword: false }">
                @csrf
                @method('PUT')
                <input type="hidden" name="_form" value="edit">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama</label>
                    <input type="text" name="name" :value="editData.name" required placeholder="Contoh: Andi Wijaya" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email" :value="editData.email" required placeholder="contoh@emanuelcorp.com" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Password Baru (opsional)</label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" name="password" x-model="newPassword" placeholder="Kosongkan jika tidak diubah"
                               class="w-full pl-4 pr-10 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                               :class="newPassword.length === 0 ? 'border-gray-300' : (/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}$/.test(newPassword) ? 'border-green-400' : 'border-red-400')">
                        <button type="button" @click="showPassword = !showPassword" tabindex="-1"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.7 18.7 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    <p class="text-xs mt-1" :class="newPassword.length === 0 ? 'text-gray-400' : (/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}$/.test(newPassword) ? 'text-green-600' : 'text-red-500')">
                        Jika diisi, password harus minimal 8 karakter, kombinasi huruf besar, huruf kecil, angka, dan simbol khusus.
                    </p>
                    @error('password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Role</label>
                    <select name="role" x-model="editData.role" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="admin">Admin</option>
                        <option value="owner">Owner</option>
                        <option value="manajer">Manajer</option>
                        <option value="karyawan">Karyawan</option>
                    </select>
                </div>
                <div x-show="editData.role === 'karyawan' || editData.role === 'manajer'" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Unit Bisnis</label>
                    <select name="unit_bisnis_id" x-model="editData.unit_bisnis_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Pilih unit bisnis</option>
                        @foreach ($unitBisnis as $ub)
                            <option value="{{ $ub['id'] }}">{{ $ub['nama'] ?? '-' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="editOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL KONFIRMASI TOGGLE STATUS --}}
    <div x-show="toggleOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="toggleOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-sm text-center">
            <h3 class="text-base font-semibold text-gray-900 mb-2">Ubah Status Akun?</h3>
            <p class="text-sm text-gray-500 mb-6">
                <span x-text="isAktif(toggleData) ? 'Nonaktifkan' : 'Aktifkan'"></span> akun <span class="font-medium" x-text="toggleData.name"></span>?
            </p>
            <form :action="'/karyawan/' + toggleData.id + '/toggle'" method="POST" class="flex justify-center gap-3">
                @csrf
                @method('PATCH')
                <button type="button" @click="toggleOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl">Ya, Ubah</button>
            </form>
        </div>
    </div>

    {{-- MODAL KONFIRMASI HAPUS --}}
    <div x-show="deleteOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="deleteOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-sm text-center">
            <h3 class="text-base font-semibold text-gray-900 mb-2">Hapus Akun?</h3>
            <p class="text-sm text-gray-500 mb-6">
                Akun <span class="font-medium" x-text="deleteData.name"></span> akan di-nonaktifkan permanen. Histori data tetap tersimpan.
            </p>
            <form :action="'/karyawan/' + deleteData.id" method="POST" class="flex justify-center gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="deleteOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                <button type="submit" class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-xl">Hapus</button>
            </form>
        </div>
    </div>

</div>
@endsection