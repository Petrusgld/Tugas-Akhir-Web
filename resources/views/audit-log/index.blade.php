@extends('layouts.app')
@section('title', 'Audit Log')

@section('content')
<div x-data="{
        rows: {{ Illuminate\Support\Js::from(array_values($logs)) }},
        search: '',
        dari: '',
        sampai: '',
        page: 1,
        perPage: 10,
        get filtered() {
            return this.rows.filter(r => {
                const name = (r.user_name ?? '').toLowerCase();
                const matchSearch = this.search === '' || name.includes(this.search.toLowerCase());
                const waktu = r.waktu ?? r.created_at ?? '';
                const matchDari = this.dari === '' || waktu >= this.dari;
                const matchSampai = this.sampai === '' || waktu <= this.sampai + 'T23:59:59';
                return matchSearch && matchDari && matchSampai;
            });
        },
        get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
        get paged() { return this.filtered.slice((this.page - 1) * this.perPage, this.page * this.perPage); },
     }"
     x-init="$watch('search', () => page = 1); $watch('dari', () => page = 1); $watch('sampai', () => page = 1)">

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Audit Log</h2>
        <p class="text-sm text-gray-500 mt-0.5">Riwayat aktivitas seluruh pengguna sistem.</p>
    </div>

    @if ($error)
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
            Gagal memuat audit log: {{ $error }}
        </div>
    @endif

    <div class="flex flex-wrap gap-3 mb-4">
        <input type="text" x-model="search" placeholder="Cari user..."
               class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500 w-56">
        <input type="date" x-model="dari" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
        <input type="date" x-model="sampai" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-6 py-3 w-14">No</th>
                    <th class="text-left px-6 py-3">User</th>
                    <th class="text-left px-6 py-3">Aktivitas</th>
                    <th class="text-left px-6 py-3">Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-if="filtered.length === 0">
                    <tr><td colspan="4" class="px-6 py-8 text-center text-gray-400">Belum ada audit log.</td></tr>
                </template>
                <template x-for="(log, i) in paged" :key="log.id ?? i">
                    <tr>
                        <td class="px-6 py-3 text-gray-400" x-text="(page - 1) * perPage + i + 1"></td>
                        <td class="px-6 py-3 font-medium text-gray-900" x-text="log.user_name ?? '-'"></td>
                        <td class="px-6 py-3 text-gray-500" x-text="log.aktivitas ?? log.deskripsi ?? '-'"></td>
                        <td class="px-6 py-3 text-gray-400" x-text="log.waktu ?? log.created_at ?? '-'"></td>
                    </tr>
                </template>
            </tbody>
        </table>

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
</div>
@endsection
