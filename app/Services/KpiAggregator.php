<?php

namespace App\Services;

use App\Helpers\Format;

class KpiAggregator
{
    /** Threshold status unit — HARUS sama persis dengan Admin App (Flutter). */
    protected const THRESHOLD_HIJAU = 80;
    protected const THRESHOLD_KUNING = 50;

    /** Cache in-memory supaya /kpi-periods tidak di-fetch berulang kali dalam satu request. */
    protected ?array $allPeriodsCache = null;

    /** Cache in-memory supaya /kpi-templates tidak di-fetch berulang kali dalam satu request. */
    protected ?array $allTemplatesCache = null;

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
     * Ambil seluruh data /kpi-templates (semua unit) sekali saja lalu simpan
     * di cache instance ini. Menggantikan pemanggilan per-unit
     * /kpi-templates/unit-bisnis/{id} yang ternyata kosong untuk sebagian
     * besar unit (pola bug yang sama seperti /kpi-periods/unit-bisnis/{id}
     * sebelumnya).
     */
    public function allTemplates(): array
    {
        if ($this->allTemplatesCache !== null) {
            return $this->allTemplatesCache;
        }

        try {
            $this->allTemplatesCache = $this->api->get('/kpi-templates')['data'] ?? [];
        } catch (\Exception $e) {
            $this->allTemplatesCache = [];
        }

        return $this->allTemplatesCache;
    }

    /**
     * Tentukan status hijau/kuning/merah dari rata-rata achievement unit,
     * memakai threshold yang identik dengan Admin App. Dipusatkan di satu
     * method supaya Dashboard, Leaderboard, dan halaman lain yang memakai
     * aggregator ini otomatis konsisten.
     */
    protected function statusFromAchievement(float $achievement): string
    {
        if ($achievement >= self::THRESHOLD_HIJAU) {
            return 'hijau';
        }
        if ($achievement >= self::THRESHOLD_KUNING) {
            return 'kuning';
        }
        return 'merah';
    }

    /**
     * Hitung pencapaian tiap unit bisnis untuk bulan/tahun tertentu.
     *
     * Return: [
     *   'units' => [ [id, unit_bisnis_id, nama, kategori_nama, achievement, status, jumlah_kpi, punya_target], ... ],
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

        // Ambil SEMUA template sekali saja (global), lalu kelompokkan per
        // unit_bisnis_id di PHP — menggantikan endpoint per-unit
        // /kpi-templates/unit-bisnis/{id} yang ternyata sering kosong.
        $allTemplates = $this->allTemplates();
        $templatesByUnit = [];
        foreach ($allTemplates as $t) {
            $uid = Format::pick($t, ['unit_bisnis_id', 'unit_bisnis.id']);
            if ($uid === null) {
                continue;
            }
            $templatesByUnit[$uid][] = $t;
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
                 'status'          => 'belum_ada_target', // <-- default baru, bukan 'merah'
                 'jumlah_kpi'      => count($templatesByUnit[$id] ?? []),
                 'punya_target'    => false,
            ];

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

                foreach ($periodeIni as $p) {
                    $target    = (float) Format::pick($p, ['target', 'nilai_target'], 0);
                    $realisasi = (float) Format::pick($p, ['realisasi', 'nilai_realisasi', 'total_realisasi'], 0);

                    if ($target > 0) {
                        $achievements[] = min(100, ($realisasi / $target) * 100);
                    }
                }

                $row['achievement'] = !empty($achievements) ? array_sum($achievements) / count($achievements) : 0.0;

                // Status unit = THRESHOLD dari rata-rata achievement unit itu
                // sendiri (bukan lagi weakest-link dari status per-KPI).
                // Ini konsisten dengan Admin App, yang juga menentukan status
                // unit dari rata-rata persentase unit, bukan dari status
                // per-KPI individual. Lihat catatan PERBAIKAN #3 di atas.
                $row['status'] = $this->statusFromAchievement($row['achievement']);
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