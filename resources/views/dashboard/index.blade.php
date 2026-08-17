@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@php
    use App\Helpers\Format;

    $unitList  = $summary['unit_bisnis'] ?? [];
    $totalUnit = count($unitList);
    $hijau     = collect($unitList)->where('status', 'hijau')->count();
    $kuning    = collect($unitList)->where('status', 'kuning')->count();
    $merah     = collect($unitList)->where('status', 'merah')->count();
    $totalAchievement = $summary['total_achievement'] ?? ($summary['rata_rata'] ?? 0);

    // PERBAIKAN: sebelumnya semua persentase achievement dibulatkan ke
    // bilangan bulat (number_format(..., 0)), jadi KPI dengan pecahan
    // (mis. 52.7%) selalu tampil dibulatkan jadi 53%. Sekarang dipakai
    // helper ini: kalau angkanya memang punya pecahan, tampilkan 1 angka
    // di belakang koma (52.7%); kalau bulat (52.0), tetap tampil tanpa
    // koma (52%) supaya tidak ada ".0" yang mengganggu di tampilan.
    $formatPersen = function ($value) {
        $value = (float) $value;
        // Bulatkan dulu ke 1 desimal supaya floating point error (mis.
        // 51.999999) tidak bikin salah nampilin ".0" atau ".x" palsu.
        $rounded = round($value, 1);
        if ($rounded == floor($rounded)) {
            return number_format($rounded, 0);
        }
        return number_format($rounded, 1);
    };
@endphp

@if (!empty($apiErrors))
    <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm px-4 py-3 rounded-lg">
        <p class="font-semibold mb-1">Beberapa data dashboard gagal dimuat dari API:</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach ($apiErrors as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
        <p class="mt-2 text-xs text-yellow-700">Ini sebabnya kartu/statistik di bawah bisa tampil 0 atau kosong — endpoint di atas perlu dicek (nama endpoint, format tanggal, atau hak akses token).</p>
    </div>
@endif

{{-- BAGIAN 1 — HEADER PERIODE --}}
<div class="bg-white rounded-2xl border border-gray-200 px-6 py-4 mb-6 flex items-center justify-between">
    <div>
        <div class="flex items-center gap-3">
            <h2 class="text-base font-semibold text-gray-900">
                Periode Aktif: {{ $periodeAktif['label'] ?? (Format::namaBulan($periodeAktif['bulan'] ?? now()->month) . ' ' . ($periodeAktif['tahun'] ?? now()->year)) }}
            </h2>
            <span class="px-2.5 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">Aktif</span>
        </div>
        <p class="text-sm text-gray-500 mt-1">Data performa dihitung sampai cut-off tanggal 25 setiap bulannya.</p>
    </div>
</div>

{{-- BAGIAN 2 — STAT CARDS --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500">Total Unit Bisnis</p>
        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalUnit }}</p>
        <p class="text-xs text-gray-400 mt-1">unit</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500">On Track</p>
        <p class="text-3xl font-bold text-green-600 mt-2">{{ $hijau }}</p>
        <p class="text-xs text-gray-400 mt-1">hijau</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500">Warning</p>
        <p class="text-3xl font-bold text-yellow-500 mt-2">{{ $kuning }}</p>
        <p class="text-xs text-gray-400 mt-1">kuning</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <p class="text-sm text-gray-500">Critical</p>
        <p class="text-3xl font-bold text-red-500 mt-2">{{ $merah }}</p>
        <p class="text-xs text-gray-400 mt-1">merah</p>
    </div>
</div>

{{-- BAGIAN 3 — KPI MASTER + TREND --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">KPI Master Perusahaan</h3>
        <div class="flex-1 flex flex-col items-center justify-center gap-5">
            <div class="relative w-56 h-56 max-w-full aspect-square shrink-0">
                <canvas id="donutChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-3xl font-bold text-gray-900">{{ number_format($totalAchievement, 1) }}%</span>
                    <span class="text-xs text-gray-400">total</span>
                </div>
            </div>
            <div class="flex items-center justify-center gap-5 text-sm">
                <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span> Hijau: {{ $hijau }}</div>
                <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span> Kuning: {{ $kuning }}</div>
                <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Merah: {{ $merah }}</div>
            </div>
        </div>

        {{-- Tombol ke halaman Skor Company (skor berbobot + atur bobot
             kategori). Owner only — untuk admin/manajer tombol ini tidak
             ditampilkan sama sekali karena halaman tujuannya juga owner-only. --}}
        @if ($isOwner)
        <a href="{{ route('skor-company') }}"
           class="flex items-center justify-between mt-5 pt-4 border-t border-gray-100 text-sm font-medium text-green-600 hover:text-green-700">
            <span>Lihat Detail KPI Master</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-6"
         x-data="{ trendView: '{{ request()->has('trend_bulan') ? '1bulan' : '6bulan' }}' }">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-900">Tren KPI</h3>
            <div class="flex items-center bg-gray-100 rounded-lg p-1 text-xs font-medium">
                <button type="button" @click="trendView = '6bulan'"
                        :class="trendView === '6bulan' ? 'bg-white shadow text-gray-900' : 'text-gray-500'"
                        class="px-3 py-1.5 rounded-md transition">6 Bulan</button>
                <button type="button" @click="trendView = '1bulan'"
                        :class="trendView === '1bulan' ? 'bg-white shadow text-gray-900' : 'text-gray-500'"
                        class="px-3 py-1.5 rounded-md transition">1 Bulan</button>
            </div>
        </div>

        {{-- Tampilan 6 Bulan: tren rata-rata pencapaian seluruh unit --}}
        <div x-show="trendView === '6bulan'">
            <canvas id="trendChart" height="160"></canvas>
        </div>

        {{-- Tampilan 1 Bulan: breakdown pencapaian per unit bisnis untuk satu
             bulan, dengan panah kiri/kanan untuk pindah bulan. --}}
        <div x-show="trendView === '1bulan'" x-cloak>
            <div class="flex items-center justify-between mb-3">
                <a href="{{ request()->fullUrlWithQuery(['trend_bulan' => $trendSatuBulan['prev_bulan'], 'trend_tahun' => $trendSatuBulan['prev_tahun']]) }}"
                   class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <span class="text-sm font-semibold text-gray-700">{{ $trendSatuBulan['label'] }}</span>
                <a href="{{ request()->fullUrlWithQuery(['trend_bulan' => $trendSatuBulan['next_bulan'], 'trend_tahun' => $trendSatuBulan['next_tahun']]) }}"
                   class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            </div>
            <canvas id="trendChartBulanIni" height="190"></canvas>
        </div>
    </div>
</div>

{{-- BAGIAN 4 — KPI PER UNIT BISNIS --}}
<div class="mb-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-4">KPI per Unit Bisnis</h3>

    @if (empty($unitList))
        <div class="bg-white rounded-2xl border border-gray-200 p-8 text-center text-sm text-gray-400">
            Belum ada data KPI unit bisnis untuk periode ini.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach (array_slice($unitList, 0, 6) as $u)
                <a href="{{ route('unit-bisnis.show', $u['unit_bisnis_id'] ?? $u['id'] ?? '#') }}"
                   class="block bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-md transition">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold text-gray-900">{{ $u['nama'] ?? $u['unit_bisnis_nama'] ?? '-' }}</p>
                        @if (!empty($u['kategori_nama']))
                            <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full shrink-0">{{ $u['kategori_nama'] }}</span>
                        @endif
                    </div>
                    <div class="mt-3 w-full bg-gray-100 rounded-full h-2.5">
                        <div class="h-2.5 rounded-full {{ Format::statusColor($u['status'] ?? null, 'bg') }}"
                             style="width: {{ min(100, (float) ($u['achievement'] ?? 0)) }}%"></div>
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-sm font-bold text-gray-900">{{ $formatPersen($u['achievement'] ?? 0) }}%</span>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ Format::statusColor($u['status'] ?? null, 'bgLight') }} {{ Format::statusColor($u['status'] ?? null, 'text') }}">
                            {{ strtoupper($u['status'] ?? '-') }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">{{ $u['jumlah_kpi'] ?? 0 }} KPI</p>
                </a>
            @endforeach
        </div>
        @if (count($unitList) > 6)
            <p class="text-xs text-gray-400 mt-3">
                Menampilkan 6 dari {{ count($unitList) }} unit bisnis.
                <a href="{{ route('unit-bisnis.index') }}" class="text-brand-600 hover:underline">Lihat semua &rarr;</a>
            </p>
        @endif
    @endif
</div>

{{-- BAGIAN 5 — AKTIVITAS TERBARU --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900">Aktivitas Terbaru</h3>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="text-left px-6 py-3">Unit Bisnis</th>
                <th class="text-left px-6 py-3">Form / KPI</th>
                <th class="text-left px-6 py-3">Waktu</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($aktivitas as $a)
                <tr>
                    <td class="px-6 py-3">{{ $a['unit_bisnis_nama'] ?? '-' }}</td>
                    <td class="px-6 py-3">{{ $a['kpi_nama'] ?? '-' }}</td>
                    <td class="px-6 py-3 text-gray-400">
                        @if (!empty($a['created_at']))
                            @php($tglAktivitas = \Carbon\Carbon::parse($a['created_at'])->setTimezone('Asia/Jakarta'))
                            {{ $tglAktivitas->format('H:i') }} - {{ $tglAktivitas->format('d') }} {{ Format::namaBulan($tglAktivitas->month) }} {{ $tglAktivitas->format('Y') }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-6 py-8 text-center text-gray-400">Belum ada aktivitas terbaru.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
    const donutCtx = document.getElementById('donutChart');
    new Chart(donutCtx, {
        type: 'doughnut',
        data: {
            labels: ['Hijau', 'Kuning', 'Merah'],
            datasets: [{
                data: [{{ $hijau }}, {{ $kuning }}, {{ $merah }}],
                backgroundColor: ['#22C55E', '#EAB308', '#EF4444'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: { legend: { display: false } }
        }
    });

    const trendData = @json($trend);
    const trendCtx = document.getElementById('trendChart');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendData.map(t => (t.label ?? (t.bulan + '/' + t.tahun))),
            datasets: [{
                label: 'Rata-rata Pencapaian (%)',
                data: trendData.map(t => t.achievement ?? t.rata_rata ?? 0),
                borderColor: '#22C55E',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#22C55E',
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });

    // Tren "1 Bulan": breakdown pencapaian per unit bisnis untuk bulan yang
    // sedang dipilih (dinavigasi lewat panah kiri/kanan di atas chart ini).
    // Gaya chart dibuat sama persis dengan Tren KPI 6 Bulan (line/area hijau)
    // supaya konsisten secara visual, hanya sumbu-X-nya per unit bisnis.
    const trendBulanIniUnits = @json($trendSatuBulan['units'] ?? []);

    // Supaya label nama unit bisnis yang panjang tidak dirotasi miring (jelek
    // & sulit dibaca), label dipecah jadi beberapa baris (maks. 2 kata/baris).
    // Chart.js otomatis merender array string sebagai tick multi-baris.
    function wrapLabel(text) {
        const words = String(text ?? '-').split(' ');
        const lines = [];
        for (let i = 0; i < words.length; i += 2) {
            lines.push(words.slice(i, i + 2).join(' '));
        }
        return lines;
    }

    const trendBulanIniCtx = document.getElementById('trendChartBulanIni');
    if (trendBulanIniCtx) {
        new Chart(trendBulanIniCtx, {
            type: 'line',
            data: {
                labels: trendBulanIniUnits.map(u => wrapLabel(u.nama ?? u.unit_bisnis_nama)),
                datasets: [{
                    label: 'Pencapaian (%)',
                    data: trendBulanIniUnits.map(u => u.achievement ?? 0),
                    borderColor: '#22C55E',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.35,
                    fill: true,
                    pointBackgroundColor: '#22C55E',
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100 },
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 0,
                            minRotation: 0,
                            font: { size: 10 },
                        }
                    }
                }
            }
        });
    }
</script>
@endsection