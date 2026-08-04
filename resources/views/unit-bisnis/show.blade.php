@extends('layouts.app')
@section('title', $unit['nama'] ?? 'Detail Unit Bisnis')

@section('content')
@php
    use App\Helpers\Format;

    // Gabungkan template KPI dengan data period (target/realisasi) periode aktif
    $bulanAktif = $periodeAktif['bulan'] ?? now()->month;
    $tahunAktif = $periodeAktif['tahun'] ?? now()->year;

    $periodsByTemplate = collect($periods)->keyBy('kpi_template_id');
@endphp

<div x-data="{
        addKpiOpen: false,
        targetOpen: false,
        deleteKpiOpen: false,
        formOpen: false,
        targetData: {},
        deleteKpiData: {},
        formData: {}
     }">

    <a href="{{ route('unit-bisnis.index') }}" class="text-sm text-gray-500 hover:text-gray-700 inline-flex items-center gap-1 mb-4">
        ← Kembali ke Unit Bisnis
    </a>

    {{-- HEADER --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-brand-100 text-brand-700 flex items-center justify-center text-xl font-bold shrink-0">
            {{ strtoupper(substr($unit['nama'] ?? '?', 0, 2)) }}
        </div>
        <div>
            <h2 class="text-lg font-semibold text-gray-900">{{ $unit['nama'] ?? '-' }}</h2>
            <p class="text-sm text-gray-500">Kategori: {{ $unit['kategori_nama'] ?? '-' }}</p>
            <p class="text-sm text-gray-500">Periode Aktif: {{ Format::namaBulan($bulanAktif) }} {{ $tahunAktif }}</p>
        </div>
    </div>

    {{-- SECTION KPI AKTIF --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">KPI Aktif ({{ count($templates) }})</h3>
            <button @click="addKpiOpen = true" class="bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                + Tambah KPI
            </button>
        </div>

        <div class="p-6 space-y-4">
            @forelse ($templates as $t)
                @php
                    $period = $periodsByTemplate->get($t['id']);
                    $target = (float) ($period['target'] ?? 0);
                    $realisasi = (float) ($period['realisasi'] ?? 0);
                    $achievement = $target > 0 ? min(100, round(($realisasi / $target) * 100)) : 0;
                    $thHijau = (float) ($period['threshold_hijau'] ?? 90);
                    $thKuning = (float) ($period['threshold_kuning'] ?? 70);
                @endphp
                <div class="border border-gray-200 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-semibold text-gray-900">{{ $t['nama'] ?? '-' }}</p>
                        <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full">
                            {{ $t['satuan'] ?? '' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mb-2">
                        Realisasi: {{ Format::angka($realisasi, $t['satuan'] ?? null) }} / Target: {{ Format::angka($target, $t['satuan'] ?? null) }}
                    </p>
                    @if (!$period)
                        <p class="text-xs text-yellow-600 mb-2">Belum ada target untuk periode ini — klik "Set Target" untuk mengaturnya.</p>
                        @if (count($periods) > 0)
                            <details class="mb-2">
                                <summary class="text-xs text-gray-400 underline cursor-pointer">Data API mentah (debug — kenapa target tidak match)</summary>
                                <pre class="text-xs text-gray-600 whitespace-pre-wrap break-all bg-gray-50 rounded-lg p-2 mt-1">{{ json_encode($periods, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                    @endif
                    <div class="w-full bg-gray-100 rounded-full h-2.5 mb-1">
                        <div class="h-2.5 rounded-full {{ Format::progressColor($achievement, $thHijau, $thKuning) }}" style="width: {{ $achievement }}%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mb-3">{{ $achievement }}%</p>

                    <div class="flex items-center gap-4 text-sm">
                        <button @click="targetOpen = true; targetData = {
                                    kpi_id: '{{ $t['id'] }}',
                                    period_id: '{{ $period['id'] ?? '' }}',
                                    target: '{{ $period['target'] ?? '' }}',
                                    satuan: '{{ $t['satuan'] ?? '' }}',
                                    threshold_hijau: '{{ $period['threshold_hijau'] ?? 90 }}',
                                    threshold_kuning: '{{ $period['threshold_kuning'] ?? 70 }}'
                                 }"
                                class="text-brand-600 hover:text-brand-700 font-medium">Set Target</button>

                        @if (!empty($t['form_template']))
                            <button type="button"
                                    @click="formOpen = true; formData = { nama: '{{ $t['nama'] ?? '-' }}', fields: {{ Illuminate\Support\Js::from($t['form_template']) }} }"
                                    class="text-brand-600 hover:text-brand-700 font-medium">Lihat Form</button>
                        @else
                            <span class="text-gray-300 font-medium cursor-not-allowed" title="Form belum tersedia untuk KPI ini">Lihat Form</span>
                        @endif

                        <button @click="deleteKpiOpen = true; deleteKpiData = { id: '{{ $t['id'] }}', nama: '{{ $t['nama'] ?? '-' }}' }"
                                class="text-red-500 hover:text-red-700 font-medium">Hapus</button>
                    </div>
                </div>
            @empty
                <div class="text-center text-sm text-gray-400 py-10">Belum ada KPI aktif untuk unit bisnis ini.</div>
            @endforelse
        </div>
    </div>

    {{-- MODAL TAMBAH KPI --}}
    <div x-show="addKpiOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="addKpiOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-md">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Tambah KPI</h3>
            <form action="{{ route('unit-bisnis.kpi.store', $id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenis KPI</label>
                    <select name="kpi_jenis_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">Pilih jenis KPI</option>
                        @foreach ($kpiJenis as $j)
                            <option value="{{ $j['id'] }}">{{ $j['nama'] ?? '-' }}</option>
                        @endforeach
                    </select>
                </div>
                <p class="text-xs text-gray-400">Form input karyawan akan dibuat otomatis oleh sistem.</p>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="addKpiOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl">Tambah KPI</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL SET TARGET --}}
    <div x-show="targetOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="targetOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-md">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Set Target KPI</h3>
            <form :action="'/unit-bisnis/kpi/' + targetData.kpi_id + '/target'" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="unit_bisnis_id" value="{{ $id }}">
                <input type="hidden" name="periode_bulan" value="{{ $bulanAktif }}">
                <input type="hidden" name="periode_tahun" value="{{ $tahunAktif }}">
                <input type="hidden" name="period_id" :value="targetData.period_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Periode</label>
                    <input type="text" disabled value="{{ Format::namaBulan($bulanAktif) }} {{ $tahunAktif }}"
                           class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-500">
                </div>

                <div x-data="{
                        display: targetData.target ? Number(targetData.target).toLocaleString('id-ID') : '',
                        sync(e) {
                            const raw = e.target.value.replace(/[^0-9]/g, '');
                            targetData.target = raw;
                            this.display = raw ? Number(raw).toLocaleString('id-ID') : '';
                        }
                     }">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Target <span x-show="targetData.satuan && targetData.satuan.toLowerCase().includes('rupiah')" x-cloak class="text-gray-400 font-normal">(Rp)</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400"
                              x-show="targetData.satuan && targetData.satuan.toLowerCase().includes('rupiah')" x-cloak>Rp</span>
                        <input type="text" inputmode="numeric" x-model="display" @input="sync($event)" required
                               :class="(targetData.satuan && targetData.satuan.toLowerCase().includes('rupiah')) ? 'pl-10' : ''"
                               placeholder="Contoh: 1.000.000"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <input type="hidden" name="target" :value="targetData.target">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Threshold Hijau (<span x-text="targetData.threshold_hijau"></span>%)
                    </label>
                    <input type="range" min="0" max="100" name="threshold_hijau" x-model="targetData.threshold_hijau" class="w-full accent-green-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Threshold Kuning (<span x-text="targetData.threshold_kuning"></span>%)
                    </label>
                    <input type="range" min="0" max="100" name="threshold_kuning" x-model="targetData.threshold_kuning" class="w-full accent-yellow-500">
                </div>

                <p class="text-xs text-gray-400">Jika sudah ada target untuk periode ini, target tersebut akan diperbarui.</p>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="targetOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl">Simpan Target</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL LIHAT FORM --}}
    <div x-show="formOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="formOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-md">
            <h3 class="text-base font-semibold text-gray-900 mb-1">Form Input Karyawan</h3>
            <p class="text-sm text-gray-500 mb-4" x-text="formData.nama"></p>
            <div class="space-y-3 max-h-80 overflow-y-auto">
                <template x-if="!formData.fields || (Array.isArray(formData.fields) && formData.fields.length === 0)">
                    <p class="text-sm text-gray-400 text-center py-6">Form ini belum memiliki field yang terdefinisi.</p>
                </template>
                <template x-for="(f, i) in (Array.isArray(formData.fields) ? formData.fields : [])" :key="i">
                    <div class="border border-gray-200 rounded-lg p-3">
                        <p class="text-sm font-medium text-gray-800" x-text="f.label ?? f.nama ?? ('Field ' + (i + 1))"></p>
                        <p class="text-xs text-gray-400" x-text="'Tipe: ' + (f.type ?? f.tipe ?? '-')"></p>
                    </div>
                </template>
            </div>
            <div class="flex justify-end pt-4">
                <button type="button" @click="formOpen = false" class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl">Tutup</button>
            </div>
        </div>
    </div>

    {{-- MODAL KONFIRMASI HAPUS KPI --}}
    <div x-show="deleteKpiOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="deleteKpiOpen = false" class="bg-white rounded-2xl p-6 w-full max-w-sm text-center">
            <div class="w-12 h-12 mx-auto rounded-full bg-red-50 flex items-center justify-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6h16z" /></svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-2">Hapus KPI?</h3>
            <p class="text-sm text-gray-500 mb-6">
                Hapus KPI <span class="font-medium" x-text="deleteKpiData.nama"></span>? Form input dan semua data periode akan ikut terhapus.
            </p>
            <form :action="'/unit-bisnis/kpi/' + deleteKpiData.id" method="POST" class="flex justify-center gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="deleteKpiOpen = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                <button type="submit" class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-xl">Hapus</button>
            </form>
        </div>
    </div>

</div>
@endsection
