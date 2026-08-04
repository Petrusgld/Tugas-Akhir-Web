@extends('layouts.app')
@section('title', 'Laporan')

@section('content')
<div x-data="{
        tab: 'harian',
        harianRows: {{ Illuminate\Support\Js::from(array_values($laporanHarian)) }},
        sopRows: {{ Illuminate\Support\Js::from(array_values($laporanSop)) }},
        reviewOpen: false,
        deleteHarianOpen: false,
        deleteSopOpen: false,
        reviewData: {},
        deleteHarianData: {},
        deleteSopData: {},
        harianPage: 1,
        sopPage: 1,
        perPage: 10,
        get harianTotalPages() { return Math.max(1, Math.ceil(this.harianRows.length / this.perPage)); },
        get harianPaged() { return this.harianRows.slice((this.harianPage - 1) * this.perPage, this.harianPage * this.perPage); },
        get sopTotalPages() { return Math.max(1, Math.ceil(this.sopRows.length / this.perPage)); },
        get sopPaged() { return this.sopRows.slice((this.sopPage - 1) * this.perPage, this.sopPage * this.perPage); },
     }">

    {{-- TAB NAVIGATION --}}
    <div class="flex items-center gap-2 mb-6 border-b border-gray-200">
        <button @click="tab = 'harian'"
                :class="tab === 'harian' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition">
            Laporan Harian
        </button>
        <button @click="tab = 'sop'"
                :class="tab === 'sop' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition">
            Laporan SOP
        </button>
    </div>

    {{-- TAB LAPORAN HARIAN --}}
    <div x-show="tab === 'harian'">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-6 py-3 w-14">No</th>
                        <th class="text-left px-6 py-3">Karyawan</th>
                        <th class="text-left px-6 py-3">Unit Bisnis</th>
                        <th class="text-left px-6 py-3">Tanggal</th>
                        <th class="text-left px-6 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-if="harianRows.length === 0">
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">Belum ada laporan harian.</td></tr>
                    </template>
                    <template x-for="(l, i) in harianPaged" :key="l.id">
                        <tr>
                            <td class="px-6 py-3 text-gray-400" x-text="(harianPage - 1) * perPage + i + 1"></td>
                            <td class="px-6 py-3 font-medium text-gray-900" x-text="l.user_name ?? l.nama ?? '-'"></td>
                            <td class="px-6 py-3 text-gray-500" x-text="l.unit_bisnis_nama ?? '-'"></td>
                            <td class="px-6 py-3 text-gray-500" x-text="l.tanggal ?? l.created_at ?? '-'"></td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="text-gray-400 font-medium">Lihat</span>
                                    <button @click="deleteHarianOpen = true; deleteHarianData = l" class="text-red-500 hover:text-red-700 font-medium">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <div class="flex items-center justify-between px-6 py-3 border-t border-gray-100 text-sm text-gray-500" x-show="harianRows.length > 0">
                <p>Menampilkan <span x-text="harianPaged.length"></span> dari <span x-text="harianRows.length"></span> data</p>
                <div class="flex items-center gap-1">
                    <button type="button" @click="harianPage = Math.max(1, harianPage - 1)" :disabled="harianPage === 1"
                            class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 hover:bg-gray-50">Sebelumnya</button>
                    <span class="px-3 text-gray-600">Halaman <span x-text="harianPage"></span> / <span x-text="harianTotalPages"></span></span>
                    <button type="button" @click="harianPage = Math.min(harianTotalPages, harianPage + 1)" :disabled="harianPage === harianTotalPages"
                            class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 hover:bg-gray-50">Berikutnya</button>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB LAPORAN SOP --}}
    <div x-show="tab === 'sop'" x-cloak>
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-6 py-3 w-14">No</th>
                        <th class="text-left px-6 py-3">Karyawan</th>
                        <th class="text-left px-6 py-3">SOP</th>
                        <th class="text-left px-6 py-3">Tanggal</th>
                        <th class="text-left px-6 py-3">Status</th>
                        <th class="text-left px-6 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-if="sopRows.length === 0">
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400">Belum ada laporan SOP.</td></tr>
                    </template>
                    <template x-for="(s, i) in sopPaged" :key="s.id">
                        <tr>
                            <td class="px-6 py-3 text-gray-400" x-text="(sopPage - 1) * perPage + i + 1"></td>
                            <td class="px-6 py-3 font-medium text-gray-900" x-text="s.user_name ?? s.nama ?? '-'"></td>
                            <td class="px-6 py-3 text-gray-500" x-text="s.sop_nama ?? '-'"></td>
                            <td class="px-6 py-3 text-gray-500" x-text="s.tanggal ?? s.created_at ?? '-'"></td>
                            <td class="px-6 py-3">
                                <span :class="{
                                        'bg-green-100 text-green-700': s.status === 'approved',
                                        'bg-red-100 text-red-700': s.status === 'rejected',
                                        'bg-gray-100 text-gray-500': !s.status || (s.status !== 'approved' && s.status !== 'rejected')
                                      }"
                                      class="px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" x-text="s.status ?? 'pending'"></span>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3 text-sm">
                                    <button @click="reviewOpen = true; reviewData = s" class="text-brand-600 hover:text-brand-700 font-medium">Review</button>
                                    <button @click="deleteSopOpen = true; deleteSopData = s" class="text-red-500 hover:text-red-700 font-medium">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <div class="flex items-center justify-between px-6 py-3 border-t border-gray-100 text-sm text-gray-500" x-show="sopRows.length > 0">
                <p>Menampilkan <span x-text="sopPaged.length"></span> dari <span x-text="sopRows.length"></span> data</p>
                <div class="flex items-center gap-1">
                    <button type="button" @click="sopPage = Math.max(1, sopPage - 1)" :disabled="sopPage === 1"
                            class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 hover:bg-gray-50">Sebelumnya</button>
                    <span class="px-3 text-gray-600">Halaman <span x-text="sopPage"></span> / <span x-text="sopTotalPages"></span></span>
                    <button type="button" @click="sopPage = Math.min(sopTotalPages, sopPage + 1)" :disabled="sopPage === sopTotalPages"
                            class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 hover:bg-gray-50">Berikutnya</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL REVIEW SOP --}}
    <div x-show="reviewOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="reviewOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-md" x-data="{ status: 'approved' }">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Review Laporan SOP</h3>
            <form :action="'/laporan/sop/' + reviewData.id + '/review'" method="POST" class="space-y-4">
                @csrf
                @method('PATCH')
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="status" value="approved" x-model="status" class="accent-green-500"> Approved
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="status" value="rejected" x-model="status" class="accent-red-500"> Rejected
                    </label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Catatan (opsional)</label>
                    <textarea name="catatan" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="reviewOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl">Simpan Review</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL HAPUS LAPORAN HARIAN --}}
    <div x-show="deleteHarianOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="deleteHarianOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-sm text-center">
            <h3 class="text-base font-semibold text-gray-900 mb-2">Hapus Laporan Harian?</h3>
            <p class="text-sm text-gray-500 mb-6">Laporan ini akan dihapus secara permanen.</p>
            <form :action="'/laporan/harian/' + deleteHarianData.id" method="POST" class="flex justify-center gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="deleteHarianOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                <button type="submit" class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-xl">Hapus</button>
            </form>
        </div>
    </div>

    {{-- MODAL HAPUS LAPORAN SOP --}}
    <div x-show="deleteSopOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="deleteSopOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-sm text-center">
            <h3 class="text-base font-semibold text-gray-900 mb-2">Hapus Laporan SOP?</h3>
            <p class="text-sm text-gray-500 mb-6">Laporan ini akan dihapus secara permanen.</p>
            <form :action="'/laporan/sop/' + deleteSopData.id" method="POST" class="flex justify-center gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="deleteSopOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                <button type="submit" class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-xl">Hapus</button>
            </form>
        </div>
    </div>

</div>
@endsection
