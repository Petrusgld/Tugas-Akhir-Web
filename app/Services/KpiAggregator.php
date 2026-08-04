<?php

namespace App\Services;

use App\Helpers\Format;

/**
 * KpiAggregator
 * ==============
 * Dashboard, Leaderboard, dan kartu "Total Unit Bisnis / On Track / Warning /
 * Critical" sebelumnya mengambil data dari endpoint agregat backend
 * (/kpi-master/summary, /statistik/aktivitas, /leaderboard, /kpi-master/trend).
 * Endpoint-endpoint itu ternyata mengembalikan data kosong atau salah
 * (0 unit bisnis, 0% padahal ada realisasi, dst) walaupun endpoint dasarnya
 * — /unit-bisnis, /kpi-templates/unit-bisnis/{id} — sudah terbukti benar
 * (dipakai & tampil normal di halaman "Unit Bisnis & KPI").
 *
 * PERBAIKAN PENTING: versi sebelumnya mengambil target/realisasi tiap unit
 * lewat endpoint /kpi-periods/unit-bisnis/{id}. Endpoint itu TERNYATA yang
 * bermasalah — selalu kosong — sehingga achievement tiap unit selalu 0% dan
 * status selalu "merah" di Dashboard & Leaderboard, walaupun halaman
 * "Validasi Input KPI" (yang memakai endpoint GLOBAL /kpi-periods) menampilkan
 * data yang benar untuk unit yang sama (contoh: Cluster de Matraman 100%/HIJAU).
 * Sekarang kelas ini HANYA memakai endpoint global /kpi-periods (satu kali
 * fetch, di-cache, lalu dikelompokkan per unit_bisnis_id di PHP) — sumber
 * yang sama persis dengan yang sudah terbukti benar di Validasi Input KPI.
 */
class KpiAggregator
{
    /** Cache in-memory supaya /kpi-periods tidak di-fetch berulang kali dalam satu request. */
    protected ?array $allPeriodsCache = null;

    public function __construct(protected ApiService $api)
    {
    }

    /**
     * Ambil seluruh data /kpi-periods (semua unit, semua periode) sekali saja
     * lalu simpan di cache instance ini.
     */
    public function allPeriods(): array
    {
        if ($this->allPeriodsCache !== null) {
            return $this->allPeriodsCache;
        }

        try {
            $this->allPeriodsCache = $this->api->get('/kpi-periods')['data'] ?? [];
        } catch (\Exception $e) {
            $this->allPeriodsCache = [];
        }

        return $this->allPeriodsCache;
    }

    /**
     * Hitung pencapaian tiap unit bisnis untuk bulan/tahun tertentu.
     *
     * Return: [
     *   'units' => [ [id, unit_bisnis_id, nama, kategori_nama, achievement, status, jumlah_kpi], ... ],
     *   'error' => string|null,
     * ]
     */
    public function unitAchievements(int $bulan, int $tahun): array
    {
        $kategori = [];
        try {
            $kategori = $this->api->get('/kategori-unit-bisnis')['data'] ?? [];
        } catch (\Exception $e) {
            // biarkan kosong, kategori bersifat pelengkap saja
        }
        $kategoriById = collect($kategori)->keyBy(fn ($k) => Format::pick($k, ['id', 'kategori_id']));

        try {
            $unitList = $this->api->get('/unit-bisnis')['data'] ?? [];
        } catch (\Exception $e) {
            return ['units' => [], 'error' => $e->getMessage()];
        }

        // Ambil SEMUA periode sekali saja (global, sumber yang sama dengan
        // Validasi Input KPI), lalu kelompokkan per unit_bisnis_id di PHP —
        // supaya tidak bergantung pada endpoint /kpi-periods/unit-bisnis/{id}
        // yang ternyata selalu kosong.
        $allPeriods = $this->allPeriods();
        $periodsByUnit = [];
        foreach ($allPeriods as $p) {
            $uid = Format::pick($p, ['unit_bisnis_id', 'unit_bisnis.id']);
            if ($uid === null) {
                continue;
            }
            $periodsByUnit[$uid][] = $p;
        }

        $results = [];

        foreach ($unitList as $u) {
            $id = Format::pick($u, ['id', 'unit_bisnis_id']);
            if ($id === null) {
                continue;
            }

            $katId = Format::pick($u, ['kategori_id', 'kategori.id']);
            $kat   = $katId !== null ? $kategoriById->get($katId) : null;

            $row = [
                'id'              => $id,
                'unit_bisnis_id'  => $id,
                'nama'            => Format::pick($u, ['nama', 'unit_bisnis_nama'], '-'),
                'kategori_nama'   => Format::pick($u, ['kategori_nama', 'kategori.nama']) ?? Format::pick($kat, ['nama']),
                'achievement'     => 0.0,
                'status'          => 'merah',
                'jumlah_kpi'      => 0,
                'punya_target'    => false,
            ];

            // Jumlah KPI aktif (template) yang dimiliki unit ini, terlepas dari
            // apakah sudah di-set target bulan ini atau belum.
            try {
                $templates = $this->api->get("/kpi-templates/unit-bisnis/{$id}")['data'] ?? [];
                $row['jumlah_kpi'] = count($templates);
            } catch (\Exception $e) {
                // biarkan 0 jika gagal
            }

            // Target & realisasi periode berjalan untuk unit ini — diambil dari
            // hasil pengelompokan /kpi-periods global di atas (bukan endpoint
            // per-unit yang bermasalah).
            $periods = $periodsByUnit[$id] ?? [];

            $periodeIni = array_values(array_filter($periods, function ($p) use ($bulan, $tahun) {
                $pb = Format::pick($p, ['periode_bulan', 'bulan']);
                $pt = Format::pick($p, ['periode_tahun', 'tahun']);

                return $pb !== null && $pt !== null && (int) $pb === (int) $bulan && (int) $pt === (int) $tahun;
            }));

            if (!empty($periodeIni)) {
                $row['punya_target'] = true;
                $achievements = [];
                $thHijauSum = 0.0;
                $thKuningSum = 0.0;

                foreach ($periodeIni as $p) {
                    $target    = (float) Format::pick($p, ['target', 'nilai_target'], 0);
                    $realisasi = (float) Format::pick($p, ['realisasi', 'nilai_realisasi', 'total_realisasi'], 0);

                    if ($target > 0) {
                        $achievements[] = min(100, ($realisasi / $target) * 100);
                    }

                    $thHijauSum  += (float) Format::pick($p, ['threshold_hijau'], 90);
                    $thKuningSum += (float) Format::pick($p, ['threshold_kuning'], 70);
                }

                $count = count($periodeIni);
                $thHijau  = $count ? $thHijauSum / $count : 90;
                $thKuning = $count ? $thKuningSum / $count : 70;

                $row['achievement'] = !empty($achievements) ? array_sum($achievements) / count($achievements) : 0.0;
                $row['status'] = $row['achievement'] >= $thHijau
                    ? 'hijau'
                    : ($row['achievement'] >= $thKuning ? 'kuning' : 'merah');
            }

            $results[] = $row;
        }

        return ['units' => $results, 'error' => null];
    }

    /**
     * Rata-rata pencapaian per bulan (untuk chart tren "6 Bulan"), dihitung
     * dari seluruh data /kpi-periods (semua unit bisnis) yang sudah punya
     * target > 0, dikelompokkan per (bulan, tahun).
     */
    public function trend(int $bulanAkhir, int $tahunAkhir, int $jumlahBulan = 6): array
    {
        $bulanList = [];
        $b = $bulanAkhir;
        $t = $tahunAkhir;
        for ($i = 0; $i < $jumlahBulan; $i++) {
            $bulanList[] = ['bulan' => $b, 'tahun' => $t];
            $b--;
            if ($b < 1) {
                $b = 12;
                $t--;
            }
        }
        $bulanList = array_reverse($bulanList);

        $allPeriods = $this->allPeriods();

        $grouped = [];
        foreach ($allPeriods as $p) {
            $pb = Format::pick($p, ['periode_bulan', 'bulan']);
            $pt = Format::pick($p, ['periode_tahun', 'tahun']);
            if ($pb === null || $pt === null) {
                continue;
            }
            $target    = (float) Format::pick($p, ['target', 'nilai_target'], 0);
            $realisasi = (float) Format::pick($p, ['realisasi', 'nilai_realisasi', 'total_realisasi'], 0);
            if ($target <= 0) {
                continue;
            }
            $key = ((int) $pb) . '-' . ((int) $pt);
            $grouped[$key][] = min(100, ($realisasi / $target) * 100);
        }

        return array_map(function ($bt) use ($grouped) {
            $key = $bt['bulan'] . '-' . $bt['tahun'];
            $vals = $grouped[$key] ?? [];
            return [
                'bulan'       => $bt['bulan'],
                'tahun'       => $bt['tahun'],
                'label'       => Format::namaBulan($bt['bulan']) . ' ' . $bt['tahun'],
                'achievement' => !empty($vals) ? round(array_sum($vals) / count($vals), 1) : 0,
            ];
        }, $bulanList);
    }
}
