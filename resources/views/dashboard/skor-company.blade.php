@extends('layouts.app')
@section('title', 'Detail KPI Master')

@section('content')
@php
    use App\Helpers\Format;
@endphp

<div class="mb-6">
    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Kembali ke Dashboard
    </a>
</div>

@if (!empty($apiErrors))
    <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm px-4 py-3 rounded-lg">
        <p class="font-semibold mb-1">Beberapa data gagal dimuat dari API:</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach ($apiErrors as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('success'))
    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
@endif

@if ($errors->has('bobot'))
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
        {{ $errors->first('bobot') }}
    </div>
@endif

{{-- HEADER + FILTER BULAN/TAHUN --}}
<div class="bg-white rounded-2xl border border-gray-200 px-6 py-4 mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h2 class="text-base font-semibold text-gray-900">Skor Company</h2>
        <p class="text-sm text-gray-500 mt-1">{{ Format::namaBulan($bulan) }} {{ $tahun }}</p>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <select name="bulan" class="text-sm border border-gray-200 rounded-lg px-2 py-1.5" onchange="this.form.submit()">
            @for ($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" @selected($m == $bulan)>{{ Format::namaBulan($m) }}</option>
            @endfor
        </select>
        <select name="tahun" class="text-sm border border-gray-200 rounded-lg px-2 py-1.5" onchange="this.form.submit()">
            @for ($y = now()->year - 2; $y <= now()->year + 1; $y++)
                <option value="{{ $y }}" @selected($y == $tahun)>{{ $y }}</option>
            @endfor
        </select>
    </form>
</div>

{{-- SKOR COMPANY --}}
<div class="bg-white rounded-2xl border border-gray-200 p-8 mb-6 text-center">
    @if ($skorCompany !== null)
        <p class="text-sm text-gray-500 mb-2">Skor Company</p>
        <p class="text-5xl font-bold {{ $skorCompany >= 100 ? 'text-green-600' : ($skorCompany >= 80 ? 'text-yellow-500' : 'text-red-500') }}">
            {{ number_format($skorCompany, 1) }}%
        </p>
    @else
        <p class="text-sm text-gray-400 py-4">Skor company belum tersedia untuk periode ini.</p>
    @endif
</div>

{{-- BREAKDOWN KATEGORI -> UNIT (expandable) --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6" x-data="{ open: null }">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between text-xs uppercase text-gray-500 font-semibold">
        <span class="flex-1">Kategori</span>
        <span class="w-20 text-right">Bobot</span>
        <span class="w-24 text-right">Rata-rata</span>
        <span class="w-24 text-right">Kontribusi</span>
        <span class="w-6"></span>
    </div>

    @forelse ($kategoriMaster as $i => $k)
        <div class="border-b border-gray-100 last:border-0">
            <button type="button" @click="open = (open === {{ $i }} ? null : {{ $i }})"
                    class="w-full flex items-center px-6 py-4 hover:bg-gray-50 text-left">
                <span class="flex-1 font-medium text-gray-900">{{ $k['nama_kategori'] ?? '-' }}</span>
                <span class="w-20 text-right text-sm text-gray-500">{{ number_format($k['bobot_persen'] ?? 0, 0) }}%</span>
                <span class="w-24 text-right text-sm text-gray-500">{{ number_format($k['rata_rata'] ?? 0, 1) }}%</span>
                <span class="w-24 text-right text-sm font-semibold text-gray-900">{{ number_format($k['kontribusi'] ?? 0, 1) }}%</span>
                <span class="w-6 text-gray-400" :class="open === {{ $i }} ? 'rotate-180' : ''" style="display:inline-block;transition:transform .15s">▾</span>
            </button>

            <div x-show="open === {{ $i }}" x-cloak class="bg-gray-50 px-6 py-3">
                @forelse ($k['unit'] ?? [] as $u)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <span class="text-gray-700">{{ $u['nama_unit'] ?? '-' }}</span>
                        <span class="font-medium text-gray-900">{{ number_format($u['skor'] ?? 0, 1) }}%</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 py-2">Belum ada unit di kategori ini.</p>
                @endforelse
            </div>
        </div>
    @empty
        <div class="px-6 py-8 text-center text-sm text-gray-400">Belum ada data kategori untuk periode ini.</div>
    @endforelse
</div>

{{-- FORM ATUR BOBOT KATEGORI --}}
<div class="bg-white rounded-2xl border border-gray-200 p-6"
     x-data="{
        rows: {{ Js::from(collect($bobotKategori)->map(fn ($b) => [
            'kategori_id'  => $b['kategori_id'] ?? $b['id'] ?? null,
            'nama'         => $b['nama_kategori'] ?? $b['nama'] ?? '-',
            'bobot_persen' => $b['bobot_persen'] ?? 0,
        ])->values()) }},
        get total() {
            return this.rows.reduce((sum, r) => sum + (parseFloat(r.bobot_persen) || 0), 0);
        }
     }">
    <h3 class="text-sm font-semibold text-gray-900 mb-1">Atur Bobot Kategori</h3>
    <p class="text-xs text-gray-400 mb-4">Total bobot seluruh kategori wajib 100%.</p>

    <form method="POST" action="{{ route('bobot-kategori.update') }}">
        @csrf
        <input type="hidden" name="periode" value="{{ $tahun }}">

        <template x-for="(row, idx) in rows" :key="row.kategori_id">
            <div class="flex items-center gap-4 py-2.5 border-b border-gray-100 last:border-0">
                <span class="flex-1 text-sm font-medium text-gray-900" x-text="row.nama"></span>
                <input type="hidden" :name="`bobot[${idx}][kategori_id]`" :value="row.kategori_id">
                <div class="flex items-center gap-1">
                    <input type="number" step="0.1" min="0" max="100"
                           :name="`bobot[${idx}][bobot_persen]`"
                           x-model.number="row.bobot_persen"
                           class="w-20 text-right text-sm border border-gray-200 rounded-lg px-2 py-1">
                    <span class="text-sm text-gray-400">%</span>
                </div>
            </div>
        </template>

        <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-200">
            <span class="text-sm font-semibold" :class="Math.abs(total - 100) < 0.01 ? 'text-green-600' : 'text-red-500'">
                Total: <span x-text="total.toFixed(1)"></span>%
                <span x-show="Math.abs(total - 100) >= 0.01" class="font-normal">(harus 100%)</span>
            </span>
            <button type="submit"
                    :disabled="Math.abs(total - 100) >= 0.01"
                    :class="Math.abs(total - 100) < 0.01 ? 'bg-brand-600 hover:bg-brand-700 cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
                    class="text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                Simpan
            </button>
        </div>
    </form>
</div>
@endsection