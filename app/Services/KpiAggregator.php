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
 * PERBAIKAN #1: versi sebelumnya mengambil target/realisasi tiap unit lewat
 * endpoint /kpi-periods/unit-bisnis/{id}. Endpoint itu TERNYATA yang
 * bermasalah — selalu kosong — sehingga achievement tiap unit selalu 0% dan
 * status selalu "merah" di Dashboard & Leaderboard, walaupun halaman
 * "Validasi Input KPI" (yang memakai endpoint GLOBAL /kpi-periods) menampilkan
 * data yang benar untuk unit yang sama. Sekarang kelas ini HANYA memakai
 * endpoint global /kpi-periods (satu kali fetch, di-cache, lalu dikelompokkan
 * per unit_bisnis_id di PHP) — sumber yang sama persis dengan yang sudah
 * terbukti benar di Validasi Input KPI.
 *
 * PERBAIKAN #2: jumlah_kpi per unit sebelumnya diambil dari endpoint per-unit
 * /kpi-templates/unit-bisnis/{id}, yang ternyata punya pola bug yang sama —
 * kosong untuk hampir semua unit kecuali kebetulan satu-dua unit saja.
 * Sekarang dipindah ke fetch GLOBAL /kpi-templates (satu kali, di-cache),
 * dikelompokkan per unit_bisnis_id di PHP — pola yang sama dengan
 * allPeriods().
 *
 * PERBAIKAN #3: status unit (hijau/kuning/merah) sebelumnya dihitung ulang
 * dengan membandingkan achievement rata-rata ke rata-rata threshold_hijau/
 * threshold_kuning dari semua KPI unit itu. Ini salah secara logika karena
 * mencampur threshold dari KPI yang berbeda-beda jadi satu angka yang tidak
 * bermakna, dan mengabaikan tipe formula KPI (maximize/minimize/range/binary)
 * yang membuat perhitungan realisasi/target*100 tidak selalu valid.
 * Sekarang status unit memakai field `status` yang SUDAH dihitung backend
 * per-KPI (field ini sudah benar & sudah memperhitungkan tipe formula +
 * threshold KPI itu sendiri), lalu diambil status TERBURUK (weakest link)
 * di antara semua KPI unit tersebut sebagai status unit. Ini konsisten
 * dengan apa yang sudah tampil benar di halaman Validasi Input KPI.
 */
class KpiAggregator
{
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
                $statuses = [];

                foreach ($periodeIni as $p) {
                    $target    = (float) Format::pick($p, ['target', 'nilai_target'], 0);
                    $realisasi = (float) Format::pick($p, ['realisasi', 'nilai_realisasi', 'total_realisasi'], 0);

                    if ($target > 0) {
                        $achievements[] = min(100, ($realisasi / $target) * 100);
                    }

                    // PENTING: pakai status yang SUDAH dihitung backend per-KPI
                    // (field ini sudah benar dan sudah memperhitungkan tipe
                    // formula KPI — maximize/minimize/range/binary — serta
                    // threshold KPI itu sendiri). Jangan dihitung ulang dengan
                    // mengambil rata-rata threshold semua KPI di unit, karena
                    // itu mencampur threshold KPI yang berbeda-beda jadi satu
                    // angka yang tidak bermakna dan menghasilkan status yang
                    // salah (contoh lama: unit dengan KPI hijau & kuning tetap
                    // dihitung merah karena rata-rata threshold-nya terlalu
                    // tinggi).
                    $st = Format::pick($p, ['status', 'warna']);
                    if ($st !== null) {
                        $statuses[] = strtolower((string) $st);
                    }
                }

                $row['achievement'] = !empty($achievements) ? array_sum($achievements) / count($achievements) : 0.0;

                // Status unit = status TERBURUK di antara semua KPI unit itu
                // (weakest link: kalau ada satu saja KPI merah, unit dianggap
                // merah; kalau tidak ada merah tapi ada kuning, unit kuning;
                // hijau hanya kalau semua KPI hijau). Ini konsisten dengan apa
                // yang sudah tampil benar di halaman Validasi Input KPI untuk
                // KPI-KPI yang sama.
                if (in_array('merah', $statuses, true)) {
                    $row['status'] = 'merah';
                } elseif (in_array('kuning', $statuses, true)) {
                    $row['status'] = 'kuning';
                } elseif (in_array('hijau', $statuses, true)) {
                    $row['status'] = 'hijau';
                } else {
                    // Fallback kalau field status ternyata tidak ada sama
                    // sekali di response API (seharusnya tidak terjadi,
                    // berdasarkan sampel data yang sudah dicek).
                    $row['status'] = $row['achievement'] >= 85
                        ? 'hijau'
                        : ($row['achievement'] >= 50 ? 'kuning' : 'merah');
                }
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