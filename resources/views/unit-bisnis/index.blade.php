@extends('layouts.app')
@section('title', 'Unit Bisnis')

@section('content')
<div x-data="{
        rows: {{ Illuminate\Support\Js::from(array_values($unitBisnis)) }},
        addOpen: false,
        editOpen: false,
        deleteOpen: false,
        editData: {},
        deleteData: {},
        filterKategori: '',
        page: 1,
        perPage: 10,
        get filtered() {
            return this.rows.filter(r => this.filterKategori === '' || (r.kategori_nama ?? '') === this.filterKategori);
        },
        get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
        get paged() { return this.filtered.slice((this.page - 1) * this.perPage, this.page * this.perPage); },
     }"
     x-init="$watch('filterKategori', () => page = 1)">

    {{-- HEADER --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Unit Bisnis</h2>
            <p class="text-sm text-gray-500 mt-0.5">Kelola seluruh unit bisnis Emanuel Corp Holding.</p>
        </div>
        <button @click="addOpen = true"
                class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
            Tambah Unit Bisnis
        </button>
    </div>

    {{-- FILTER --}}
    <div class="mb-4">
        <select x-model="filterKategori"
                class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option value="">Semua Kategori</option>
            @foreach ($kategori as $k)
                <option value="{{ $k['nama'] ?? '' }}">{{ $k['nama'] ?? '-' }}</option>
            @endforeach
        </select>
    </div>

    {{-- TABEL --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-6 py-3 w-14">No</th>
                    <th class="text-left px-6 py-3">Nama Unit Bisnis</th>
                    <th class="text-left px-6 py-3">Kategori</th>
                    <th class="text-left px-6 py-3">Status</th>
                    <th class="text-left px-6 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-if="filtered.length === 0">
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">Belum ada unit bisnis.</td></tr>
                </template>
                <template x-for="(u, i) in paged" :key="u.id">
                    <tr>
                        <td class="px-6 py-3 text-gray-400" x-text="(page - 1) * perPage + i + 1"></td>
                        <td class="px-6 py-3 font-medium text-gray-900" x-text="u.nama ?? '-'"></td>
                        <td class="px-6 py-3 text-gray-500" x-text="u.kategori_nama ?? '-'"></td>
                        <td class="px-6 py-3">
                            <span :class="(u.status ?? 'aktif') === 'aktif' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" x-text="u.status ?? 'aktif'"></span>
                        </td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3 text-sm">
                                <a :href="'/unit-bisnis/' + u.id" class="text-brand-600 hover:text-brand-700 font-medium">Lihat</a>
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

    {{-- MODAL TAMBAH --}}
    <div x-show="addOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="addOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-md">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Tambah Unit Bisnis</h3>
            <form action="{{ route('unit-bisnis.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Unit Bisnis</label>
                    <input type="text" name="nama" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Kategori</label>
                    <select name="kategori_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Pilih kategori</option>
                        @foreach ($kategori as $k)
                            <option value="{{ $k['id'] }}">{{ $k['nama'] ?? '-' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
                    <textarea name="deskripsi" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="addOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL EDIT --}}
    <div x-show="editOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="editOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-md">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Edit Unit Bisnis</h3>
            <form :action="'/unit-bisnis/' + editData.id" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Unit Bisnis</label>
                    <input type="text" name="nama" :value="editData.nama" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Kategori</label>
                    <select name="kategori_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Pilih kategori</option>
                        @foreach ($kategori as $k)
                            <option value="{{ $k['id'] }}" x-bind:selected="editData.kategori_id === {{ $k['id'] }}">{{ $k['nama'] ?? '-' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
                    <textarea name="deskripsi" rows="3" x-text="editData.deskripsi" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="editOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL KONFIRMASI HAPUS --}}
    <div x-show="deleteOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="deleteOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-sm text-center">
            <h3 class="text-base font-semibold text-gray-900 mb-2">Hapus Unit Bisnis?</h3>
            <p class="text-sm text-gray-500 mb-6">
                Yakin ingin menghapus <span class="font-medium" x-text="deleteData.nama"></span>? Semua KPI terkait juga akan terhapus.
            </p>
            <form :action="'/unit-bisnis/' + deleteData.id" method="POST" class="flex justify-center gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="deleteOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                <button type="submit" class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-xl">Hapus</button>
            </form>
        </div>
    </div>

</div>
@endsection
