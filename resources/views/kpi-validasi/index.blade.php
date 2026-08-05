@extends('layouts.app')
@section('title', 'Validasi Input KPI')

@section('content')
@php 
    use App\Helpers\Format; 

    $formattedPeriods = array_map(function($p) {
        $p['created_at_formatted'] = '-';
        if (!empty($p['created_at'])) {
            $dt = \Carbon\Carbon::parse($p['created_at'])->setTimezone('Asia/Jakarta');
            $p['created_at_formatted'] = $dt->format('H:i') . ' - ' . $dt->format('d') . ' ' . Format::namaBulan($dt->month) . ' ' . $dt->format('Y');
        }
        return $p;
    }, array_values($periods));
@endphp

<div x-data="{
        rows: {{ Illuminate\Support\Js::from($formattedPeriods) }},
        editOpen: false,
        editData: {},
        search: '',
        filterStatus: '',
        page: 1,
        perPage: 10,
        get filtered() {
            return this.rows.filter(r => {
                const text = ((r.unit_bisnis_nama ?? '') + ' ' + (r.kpi_nama ?? '')).toLowerCase();
                const matchSearch = this.search === '' || text.includes(this.search.toLowerCase());
                const matchStatus = this.filterStatus === '' || r.status === this.filterStatus;
                return matchSearch && matchStatus;
            });
        },
        get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
        get paged() { return this.filtered.slice((this.page - 1) * this.perPage, this.page * this.perPage); },
     }"
     x-init="$watch('search', () => page = 1); $watch('filterStatus', () => page = 1)">

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Validasi Input KPI</h2>
        <p class="text-sm text-gray-500 mt-0.5">Tinjau seluruh input realisasi KPI dari karyawan dan koreksi bila ada kesalahan.</p>
    </div>

    @if ($error)
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
            Gagal memuat data KPI: {{ $error }}
        </div>
    @endif

    <div class="flex flex-wrap gap-3 mb-4">
        <input type="text" x-model="search" placeholder="Cari unit bisnis / KPI..."
               class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500 w-64">
        <select x-model="filterStatus" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option value="">Semua Status</option>
            <option value="hijau">Hijau</option>
            <option value="kuning">Kuning</option>
            <option value="merah">Merah</option>
        </select>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-6 py-3 w-14">No</th>
                    <th class="text-left px-6 py-3">User</th>
                    <th class="text-left px-6 py-3">Unit Bisnis</th>
                    <th class="text-left px-6 py-3">KPI</th>
                    <th class="text-left px-6 py-3">Periode</th>
                    <th class="text-left px-6 py-3">Target</th>
                    <th class="text-left px-6 py-3">Realisasi (Input User)</th>
                    <th class="text-left px-6 py-3">Waktu Input</th>
                    <th class="text-left px-6 py-3">Status</th>
                    <th class="text-left px-6 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-if="filtered.length === 0">
                    <tr><td colspan="10" class="px-6 py-8 text-center text-gray-400">Tidak ada input KPI yang cocok.</td></tr>
                </template>
                <template x-for="(p, i) in paged" :key="p.id">
                    <tr>
                        <td class="px-6 py-3 text-gray-400" x-text="(page - 1) * perPage + i + 1"></td>
                        <td class="px-6 py-3 text-gray-700" x-text="p.user_nama ?? '-'"></td>
                        <td class="px-6 py-3 font-medium text-gray-900" x-text="p.unit_bisnis_nama ?? '-'"></td>
                        <td class="px-6 py-3 text-gray-500" x-text="p.kpi_nama ?? '-'"></td>
                        <td class="px-6 py-3 text-gray-500" x-text="(p.periode_bulan ?? '-') + '/' + (p.periode_tahun ?? '-')"></td>
                        <td class="px-6 py-3 text-gray-500" x-text="(/rupiah/i.test(p.satuan ?? '') ? 'Rp ' : '') + Number(p.target ?? 0).toLocaleString('id-ID')"></td>
                        <td class="px-6 py-3 font-medium text-gray-900" x-text="(/rupiah/i.test(p.satuan ?? '') ? 'Rp ' : '') + Number(p.realisasi ?? 0).toLocaleString('id-ID')"></td>
                        <td class="px-6 py-3 text-gray-400" x-text="p.created_at_formatted ?? '-'"></td>
                        <td class="px-6 py-3">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium uppercase"
                                  :class="{
                                      'bg-green-100 text-green-700': p.status === 'hijau',
                                      'bg-yellow-100 text-yellow-700': p.status === 'kuning',
                                      'bg-red-100 text-red-700': p.status === 'merah',
                                      'bg-gray-100 text-gray-600': !['hijau','kuning','merah'].includes(p.status)
                                  }"
                                  x-text="p.status ?? '-'"></span>
                        </td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <button @click="editOpen = true; editData = { ...p, newRealisasi: p.realisasi }"
                                        class="text-brand-600 hover:text-brand-700 font-medium">Validasi / Koreksi</button>
                                <button @click="p._debugOpen = !p._debugOpen" class="text-gray-400 hover:text-gray-600 text-xs underline">data mentah</button>
                            </div>
                        </td>
                    </tr>
                </template>
                <template x-for="(p, i) in paged" :key="'debug-' + p.id">
                    <tr x-show="p._debugOpen" x-cloak>
                        <td colspan="10" class="px-6 py-3 bg-gray-50">
                            <p class="text-xs text-gray-400 mb-1">Data mentah dari API (sementara, untuk membantu memetakan nama field yang benar):</p>
                            <pre class="text-xs text-gray-600 whitespace-pre-wrap break-all" x-text="JSON.stringify(p._raw ?? {}, null, 2)"></pre>
                        </td>
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

    {{-- MODAL VALIDASI / KOREKSI --}}
    <div x-show="editOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="editOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-md">
            <h3 class="text-base font-semibold text-gray-900 mb-1">Validasi Input KPI</h3>
            <p class="text-sm text-gray-500 mb-1">
                <span x-text="editData.unit_bisnis_nama"></span> · <span x-text="editData.kpi_nama"></span>
            </p>
            <p class="text-xs text-gray-400 mb-4">
                Input oleh: <span x-text="editData.user_nama ?? '-'"></span>
                <span x-show="editData.created_at_formatted" x-cloak> · <span x-text="editData.created_at_formatted"></span></span>
            </p>
            <form :action="'/kpi-validasi/' + editData.kpi_period_id + '/realisasi'" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Target</label>
                    <input type="text" disabled :value="(/rupiah/i.test(editData.satuan ?? '') ? 'Rp ' : '') + Number(editData.target ?? 0).toLocaleString('id-ID')" class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Realisasi (input asli dari user)</label>
                    <input type="text" disabled :value="(/rupiah/i.test(editData.satuan ?? '') ? 'Rp ' : '') + Number(editData.realisasi ?? 0).toLocaleString('id-ID')" class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-500">
                </div>
                <div x-data="{
                        display: editData.newRealisasi ? Number(editData.newRealisasi).toLocaleString('id-ID') : '',
                        sync(e) {
                            const raw = e.target.value.replace(/[^0-9]/g, '');
                            editData.newRealisasi = raw;
                            this.display = raw ? Number(raw).toLocaleString('id-ID') : '';
                        }
                     }">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Realisasi Terkoreksi</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400" x-show="/rupiah/i.test(editData.satuan ?? '')" x-cloak>Rp</span>
                        <input type="text" inputmode="numeric" x-model="display" @input="sync($event)" required
                               :class="/rupiah/i.test(editData.satuan ?? '') ? 'pl-10' : ''"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <input type="hidden" name="realisasi" :value="editData.newRealisasi">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Catatan (opsional)</label>
                    <textarea name="catatan" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="editOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl">Simpan Validasi</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection