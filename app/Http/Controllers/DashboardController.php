<?php

namespace App\Http\Controllers;

use App\Helpers\Format;
use App\Services\ApiService;
use App\Services\KpiAggregator;
use App\Services\KpiPeriodFlattener;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class DashboardController extends Controller
{
    protected ApiService $api;
    protected KpiAggregator $aggregator;

    public function __construct(ApiService $api, KpiAggregator $aggregator)
    {
        $this->api = $api;
        $this->aggregator = $aggregator;
    }

    public function index(Request $request)
    {
        $periodeAktif = null;
        $summary      = ['unit_bisnis' => [], 'total_hijau' => 0, 'total_kuning' => 0, 'total_merah' => 0, 'total_achievement' => 0];
        $trend        = [];
        $aktivitas    = [];
        $apiErrors    = [];

        // 1. Periode aktif (hanya dipakai untuk label header — kalau gagal, pakai bulan/tahun berjalan)
        try {
            $periodeRes   = $this->api->get('/periode/aktif');
            $periodeAktif = $periodeRes['data'] ?? null;
        } catch (\Exception $e) {
            $apiErrors[] = 'Periode aktif: ' . $e->getMessage();
        }

        $bulan = $periodeAktif['bulan'] ?? now()->month;
        $tahun = $periodeAktif['tahun'] ?? now()->year;

        // 2. KPI master summary — DIHITUNG SENDIRI dari /unit-bisnis + /kpi-templates +
        //    /kpi-periods (lihat App\Services\KpiAggregator), karena endpoint
        //    /kpi-master/summary bawaan API ternyata mengembalikan data kosong,
        //    dan endpoint per-unit /kpi-periods/unit-bisnis/{id} yang tadinya
        //    dipakai ternyata juga selalu kosong (lihat catatan di KpiAggregator).
        $agg = $this->aggregator->unitAchievements((int) $bulan, (int) $tahun);
        if ($agg['error']) {
            $apiErrors[] = 'Ringkasan KPI: ' . $agg['error'];
        }
        $units = $agg['units'];

        $hijau  = collect($units)->where('status', 'hijau')->count();
        $kuning = collect($units)->where('status', 'kuning')->count();
        $merah  = collect($units)->where('status', 'merah')->count();

        // PERBAIKAN: total_achievement (skor KPI Master Perusahaan) SEBELUMNYA
        // dirata-rata dari SEMUA unit bisnis (termasuk yang belum sama sekali
        // punya target/status 'belum_ada_target', achievement-nya otomatis 0).
        // Ini bikin angka di Web "diencerkan" oleh unit yang belum lapor, dan
        // jadi beda dari Admin App — yang cuma merata-ratakan unit yang SUDAH
        // muncul di response summary (yaitu unit yang sudah punya target
        // periode ini). Sekarang disamakan: hanya unit dengan `punya_target`
        // = true yang ikut dihitung rata-ratanya, supaya KPI Master di Web dan
        // App selalu identik untuk periode & data yang sama.
        $unitsPunyaTarget = collect($units)->where('punya_target', true);
        $rataRata = $unitsPunyaTarget->count() ? $unitsPunyaTarget->avg('achievement') : 0;

        $summary = [
            'unit_bisnis'       => $units,
            'total_hijau'       => $hijau,
            'total_kuning'      => $kuning,
            'total_merah'       => $merah,
            'total_achievement' => $rataRata,
        ];

        // 3. Tren 6 bulan terakhir — dihitung dari seluruh /kpi-periods (semua unit),
        //    dikelompokkan per bulan/tahun, karena /kpi-master/trend juga kosong.
        try {
            $trend = $this->aggregator->trend((int) $bulan, (int) $tahun, 6);
        } catch (\Exception $e) {
            $apiErrors[] = 'Tren KPI: ' . $e->getMessage();
        }

        // 3b. Tren "1 Bulan" — breakdown pencapaian per unit bisnis untuk SATU
        //     bulan tertentu (bisa dinavigasi kiri/kanan lewat query string
        //     trend_bulan/trend_tahun), memakai perhitungan yang sama persis
        //     dengan kartu ringkasan di atas supaya konsisten dengan halaman
        //     Validasi Input KPI & Leaderboard.
        $trendBulan = (int) $request->get('trend_bulan', $bulan);
        $trendTahun = (int) $request->get('trend_tahun', $tahun);

        if ($trendBulan === (int) $bulan && $trendTahun === (int) $tahun) {
            $unitsBulanIni = $units;
        } else {
            $unitsBulanIni = $this->aggregator->unitAchievements($trendBulan, $trendTahun)['units'];
        }

        // Bulan sebelumnya / berikutnya untuk tombol navigasi kiri-kanan.
        $prevBulan = $trendBulan - 1;
        $prevTahun = $trendTahun;
        if ($prevBulan < 1) {
            $prevBulan = 12;
            $prevTahun--;
        }
        $nextBulan = $trendBulan + 1;
        $nextTahun = $trendTahun;
        if ($nextBulan > 12) {
            $nextBulan = 1;
            $nextTahun++;
        }

        $trendSatuBulan = [
            'bulan'      => $trendBulan,
            'tahun'      => $trendTahun,
            'label'      => Format::namaBulan($trendBulan) . ' ' . $trendTahun,
            'units'      => $unitsBulanIni,
            'prev_bulan' => $prevBulan,
            'prev_tahun' => $prevTahun,
            'next_bulan' => $nextBulan,
            'next_tahun' => $nextTahun,
        ];

        // 3c. Cuma cek role di sini untuk tahu apakah tombol "Lihat Detail KPI
        //     Master" ditampilkan atau tidak. TIDAK fetch data Skor Company /
        //     Bobot Kategori di dashboard — itu baru di-fetch di halaman
        //     /skor-company (lihat method skorCompany() di bawah), supaya
        //     dashboard tidak melakukan API call tambahan yang tidak dipakai.
        $isOwner = (Session::get('user')['role'] ?? null) === 'owner';

        // 4. Peta user (id => nama) supaya "Aktivitas Terbaru" bisa menampilkan
        //    nama user yang input, kalau field nama tidak langsung tersedia di
        //    record /kpi-periods (lihat App\Services\KpiPeriodFlattener).
        $usersById = [];
        try {
            $rawUsers = $this->api->get('/users')['data'] ?? [];
            foreach ($rawUsers as $u) {
                $uid = Format::pick($u, ['id']);
                if ($uid !== null) {
                    $usersById[$uid] = Format::pick($u, ['name', 'nama'], '-');
                }
            }
        } catch (\Exception $e) {
            // biarkan kosong, nama user tetap fallback ke '-'
        }

        // 5. Aktivitas terbaru — diambil dari /kpi-periods global (endpoint yang sama
        //    dan sudah terbukti berisi data di halaman Validasi Input KPI, dan sudah
        //    di-cache oleh aggregator di atas sehingga tidak fetch dua kali), diurutkan
        //    dari yang paling baru diinput.
        try {
            $rawPeriods = $this->aggregator->allPeriods();
            $flat = KpiPeriodFlattener::flatten($rawPeriods, $usersById);

            $aktivitas = collect($flat)
                ->filter(fn ($r) => !empty($r['created_at']))
                ->sortByDesc(fn ($r) => strtotime($r['created_at']))
                ->take(8)
                ->values()
                ->all();

            // Kalau tidak ada satupun record yang punya created_at (field mungkin
            // bernama lain di API), tetap tampilkan 8 teratas apa adanya daripada kosong.
            if (empty($aktivitas) && !empty($flat)) {
                $aktivitas = array_slice($flat, 0, 8);
            }
        } catch (\Exception $e) {
            $apiErrors[] = 'Aktivitas terbaru: ' . $e->getMessage();
        }

        return view('dashboard.index', compact(
            'periodeAktif', 'summary', 'trend', 'trendSatuBulan', 'aktivitas', 'apiErrors', 'isOwner'
        ));
    }

    /**
     * Halaman terpisah — Skor Company (skor + breakdown kategori->unit) dan
     * form Atur Bobot Kategori. Owner only; diakses lewat tombol "Lihat
     * Detail KPI Master" di dashboard.
     */
    public function skorCompany(Request $request)
    {
        if ((Session::get('user')['role'] ?? null) !== 'owner') {
            abort(403);
        }

        $bulan = (int) $request->get('bulan', now()->month);
        $tahun = (int) $request->get('tahun', now()->year);

        $skorCompany    = null;
        $kategoriMaster = [];
        $bobotKategori  = [];
        $apiErrors      = [];

        try {
            $companyRes     = $this->api->get('/kpi-master/company', ['bulan' => $bulan, 'tahun' => $tahun]);
            $companyData    = $companyRes['data'] ?? $companyRes;
            $skorCompany    = $companyData['skor_company'] ?? null;
            $kategoriMaster = $companyData['kategori'] ?? [];
        } catch (\Exception $e) {
            $apiErrors[] = 'Skor Company: ' . $e->getMessage();
        }

        try {
            $bobotRes      = $this->api->get('/bobot-kategori', ['periode' => $tahun]);
            $bobotKategori = $bobotRes['data'] ?? [];
        } catch (\Exception $e) {
            $apiErrors[] = 'Bobot Kategori: ' . $e->getMessage();
        }

        return view('dashboard.skor-company', compact(
            'skorCompany', 'kategoriMaster', 'bobotKategori', 'bulan', 'tahun', 'apiErrors'
        ));
    }

    /**
     * Simpan Bobot Kategori dari halaman Skor Company. Redirect balik ke
     * halaman yang sama (bukan dashboard). Owner only.
     */
    public function updateBobotKategori(Request $request)
    {
        if ((Session::get('user')['role'] ?? null) !== 'owner') {
            abort(403);
        }

        $validated = $request->validate([
            'periode'              => ['required', 'integer'],
            'bobot'                => ['required', 'array', 'min:1'],
            'bobot.*.kategori_id'  => ['required', 'integer'],
            'bobot.*.bobot_persen' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $totalBobot = collect($validated['bobot'])->sum('bobot_persen');

        // Dicek juga real-time di frontend, tapi divalidasi ulang di sini
        // sebelum dikirim ke API sebagai jaring pengaman (server tetap sumber kebenaran).
        if (abs($totalBobot - 100) > 0.01) {
            return back()->withErrors([
                'bobot' => "Total bobot kategori harus 100%. Saat ini: {$totalBobot}%.",
            ])->withInput();
        }

        try {
            $this->api->post('/bobot-kategori', $validated);
        } catch (\Exception $e) {
            return back()->withErrors([
                'bobot' => 'Gagal menyimpan bobot kategori: ' . $e->getMessage(),
            ])->withInput();
        }

        return redirect()
            ->route('skor-company', ['periode' => $validated['periode']])
            ->with('success', 'Bobot kategori berhasil disimpan.');
    }
}