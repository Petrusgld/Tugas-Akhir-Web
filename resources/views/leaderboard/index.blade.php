@extends('layouts.app')
@section('title', 'Leaderboard')

@section('content')
@php
    use App\Helpers\Format;
    $ranked = collect($leaderboard)->values();
    $top3 = $ranked->take(3);
@endphp

<div x-data="{
        rows: {{ Illuminate\Support\Js::from($ranked->values()->all()) }},
        page: 1,
        perPage: 10,
        get totalPages() { return Math.max(1, Math.ceil(this.rows.length / this.perPage)); },
        get paged() { return this.rows.slice((this.page - 1) * this.perPage, this.page * this.perPage); },
     }">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Leaderboard KPI</h2>
            <p class="text-sm text-gray-500 mt-0.5">Peringkat unit bisnis berdasarkan pencapaian KPI.</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <select name="bulan" onchange="this.form.submit()" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
                @foreach (range(1, 12) as $b)
                    <option value="{{ $b }}" @selected($b == $bulan)>{{ Format::namaBulan($b) }}</option>
                @endforeach
            </select>
            <select name="tahun" onchange="this.form.submit()" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
                @foreach (range(now()->year, now()->year - 3) as $y)
                    <option value="{{ $y }}" @selected($y == $tahun)>{{ $y }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($top3->count() > 0)
        {{-- PODIUM --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6">
            <div class="flex items-end justify-center gap-6">
                @php $order = [1, 0, 2]; @endphp
                @foreach ($order as $pos)
                    @if ($top3->has($pos))
                        @php $u = $top3[$pos]; @endphp
                        <div class="text-center {{ $pos === 0 ? 'order-2' : ($pos === 1 ? 'order-1' : 'order-3') }}">
                            <div class="mx-auto {{ $pos === 0 ? 'w-28 h-28 border-4 border-brand-400 shadow-lg' : 'w-20 h-20 border-2 border-gray-200' }} rounded-2xl bg-brand-50 flex items-center justify-center mb-2">
                                <span class="{{ $pos === 0 ? 'text-3xl text-brand-700' : 'text-xl text-gray-500' }} font-bold">
                                    #{{ $pos + 1 }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mb-0.5">{{ $pos === 0 ? 'CHAMPION' : 'RANK ' . ($pos + 1) }}</p>
                            <p class="font-semibold text-gray-900 text-sm">{{ $u['nama'] ?? $u['unit_bisnis_nama'] ?? '-' }}</p>
                            <p class="{{ $pos === 0 ? 'text-2xl text-brand-600' : 'text-lg text-gray-700' }} font-bold mt-1">
                                {{ number_format((float) ($u['achievement'] ?? 0), 0) }}%
                            </p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- TABEL RANKING LENGKAP (PAGINATED) --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900">Ranking Lengkap</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-6 py-3">#</th>
                    <th class="text-left px-6 py-3">Unit Bisnis</th>
                    <th class="text-left px-6 py-3">Kategori</th>
                    <th class="text-left px-6 py-3">Pencapaian</th>
                    <th class="text-left px-6 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-if="rows.length === 0">
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">Belum ada data leaderboard untuk periode ini.</td></tr>
                </template>
                <template x-for="(u, i) in paged" :key="u.id ?? i">
                    <tr>
                        <td class="px-6 py-3 font-semibold text-gray-700" x-text="(page - 1) * perPage + i + 1"></td>
                        <td class="px-6 py-3 font-medium text-gray-900" x-text="u.nama ?? u.unit_bisnis_nama ?? '-'"></td>
                        <td class="px-6 py-3 text-gray-500" x-text="u.kategori_nama ?? '-'"></td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-24 bg-gray-100 rounded-full h-2">
                                    <div class="h-2 rounded-full"
                                         :class="{
                                             'bg-green-500': u.status === 'hijau',
                                             'bg-yellow-500': u.status === 'kuning',
                                             'bg-red-500': u.status === 'merah',
                                             'bg-gray-400': !['hijau','kuning','merah'].includes(u.status)
                                         }"
                                         :style="'width: ' + Math.min(100, u.achievement ?? 0) + '%'"></div>
                                </div>
                                <span class="font-semibold text-gray-800" x-text="Math.round(u.achievement ?? 0) + '%'"></span>
                            </div>
                        </td>
                        <td class="px-6 py-3">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium uppercase"
                                  :class="{
                                      'bg-green-100 text-green-700': u.status === 'hijau',
                                      'bg-yellow-100 text-yellow-700': u.status === 'kuning',
                                      'bg-red-100 text-red-700': u.status === 'merah',
                                      'bg-gray-100 text-gray-600': !['hijau','kuning','merah'].includes(u.status)
                                  }"
                                  x-text="u.status ?? '-'"></span>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div class="flex items-center justify-between px-6 py-3 border-t border-gray-100 text-sm text-gray-500" x-show="rows.length > 0">
            <p>Menampilkan <span x-text="paged.length"></span> dari <span x-text="rows.length"></span> data</p>
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
