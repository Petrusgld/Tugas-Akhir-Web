<?php

namespace App\Http\Controllers;

use App\Helpers\Format;
use App\Services\ApiService;
use Illuminate\Http\Request;

class UnitBisnisController extends Controller
{
    protected ApiService $api;

    public function __construct(ApiService $api)
    {
        $this->api = $api;
    }

    public function index(Request $request)
    {
        $unitBisnis = [];
        $kategori   = [];
        $error      = null;

        try {
            $kategori = $this->api->get('/kategori-unit-bisnis')['data'] ?? [];
        } catch (\Exception $e) {
            // biarkan kategori kosong jika gagal
        }

        $kategoriById = collect($kategori)->keyBy(fn ($k) => Format::pick($k, ['id', 'kategori_id']));

        try {
            $rawUnit = $this->api->get('/unit-bisnis')['data'] ?? [];

            // Normalisasi: samakan nama field kategori apapun bentuk respons API-nya
            // (kategori_nama langsung, objek kategori.nama, atau hanya kategori_id yang
            // perlu dicocokkan manual ke daftar kategori).
            $unitBisnis = array_map(function ($u) use ($kategoriById) {
                $kategoriId = Format::pick($u, ['kategori_id', 'kategori.id']);
                $kat        = $kategoriId !== null ? $kategoriById->get($kategoriId) : null;

                $u['kategori_id']   = $kategoriId;
                $u['kategori_nama'] = Format::pick($u, ['kategori_nama', 'kategori.nama'])
                    ?? Format::pick($kat, ['nama', 'kategori_nama']);
                $u['nama']   = Format::pick($u, ['nama', 'unit_bisnis_nama']);
                $u['status'] = Format::isAktif($u) ? 'aktif' : 'nonaktif';

                return $u;
            }, $rawUnit);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return view('unit-bisnis.index', compact('unitBisnis', 'kategori', 'error'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama'       => 'required|string|max:255',
            'kategori_id' => 'required',
            'deskripsi'  => 'nullable|string',
        ]);

        try {
            $this->api->post('/unit-bisnis', $request->only('nama', 'kategori_id', 'deskripsi'));
            return back()->with('success', 'Unit bisnis berhasil ditambahkan.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $unit          = null;
        $templates     = [];
        $kpiJenis      = [];
        $periodeAktif  = null;
        $periods       = [];
        $error         = null;

        try {
            $unit = $this->api->get("/unit-bisnis/{$id}")['data'] ?? null;
            if ($unit) {
                $unit['nama']         = Format::pick($unit, ['nama', 'unit_bisnis_nama']);
                $unit['kategori_nama'] = Format::pick($unit, ['kategori_nama', 'kategori.nama']);
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        try {
            $rawTemplates = $this->api->get("/kpi-templates/unit-bisnis/{$id}")['data'] ?? [];

            // Normalisasi id template + nama KPI + satuan supaya konsisten dengan
            // field yang dicocokkan ke data periode (target/realisasi) di bawah.
            $templates = array_map(function ($t) {
                $t['id']     = Format::pick($t, ['id', 'kpi_template_id']);
                $t['nama']   = Format::pick($t, ['nama', 'kpi_jenis.nama', 'kpi_jenis_nama']);
                $t['satuan'] = Format::pick($t, ['satuan', 'kpi_jenis.satuan', 'kpi_jenis_satuan']);

                return $t;
            }, $rawTemplates);
        } catch (\Exception $e) {
            //
        }

        try {
            $kpiJenis = $this->api->get('/kpi-jenis')['data'] ?? [];
        } catch (\Exception $e) {
            //
        }

        try {
            $periodeAktif = $this->api->get('/periode/aktif')['data'] ?? null;
        } catch (\Exception $e) {
            //
        }

        try {
            $rawPeriods = $this->api->get("/kpi-periods/unit-bisnis/{$id}")['data'] ?? [];

            // Normalisasi field + filter ke periode aktif saja. Sebelumnya kode ini
            // TIDAK memfilter berdasarkan bulan/tahun sama sekali, jadi kalau unit
            // punya lebih dari satu periode (histori bulan-bulan sebelumnya), yang
            // dipakai bisa periode yang salah / tertimpa. Alias nama field juga
            // diperbanyak karena API kadang memakai nama yang berbeda-beda untuk
            // relasi ke template KPI-nya.
            $bulanAktifNow = $periodeAktif['bulan'] ?? now()->month;
            $tahunAktifNow = $periodeAktif['tahun'] ?? now()->year;

            $normalize = function ($p) {
                $p['kpi_template_id'] = Format::pick($p, [
                    'kpi_template_id', 'kpi_template.id', 'template_id', 'kpiTemplateId',
                    'id_kpi_template', 'kpi_templates_id', 'kpi_template.kpi_template_id',
                ]);
                $p['periode_bulan'] = Format::pick($p, ['periode_bulan', 'bulan']);
                $p['periode_tahun'] = Format::pick($p, ['periode_tahun', 'tahun']);
                $p['target']        = Format::pick($p, ['target', 'nilai_target'], 0);
                $p['realisasi']     = Format::pick($p, ['realisasi', 'nilai_realisasi', 'total_realisasi'], 0);

                return $p;
            };

            $normalized = array_map($normalize, $rawPeriods);

            $periods = array_values(array_filter($normalized, function ($p) use ($bulanAktifNow, $tahunAktifNow) {
                return $p['periode_bulan'] !== null && $p['periode_tahun'] !== null
                    && (int) $p['periode_bulan'] === (int) $bulanAktifNow
                    && (int) $p['periode_tahun'] === (int) $tahunAktifNow;
            }));

            // Fallback: kalau setelah filter periode tidak ada satupun match (misal
            // karena field periode_bulan/periode_tahun ternyata bernama lain di API),
            // jangan sembunyikan semuanya — pakai data mentahnya apa adanya supaya
            // target yang baru disimpan tetap kelihatan, dan biar mudah dicek lewat
            // "Data API mentah" di kartu KPI.
            if (empty($periods) && !empty($normalized)) {
                $periods = $normalized;
            }
        } catch (\Exception $e) {
            //
        }

        return view('unit-bisnis.show', compact(
            'unit', 'templates', 'kpiJenis', 'periodeAktif', 'periods', 'error'
        ) + ['id' => $id]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama'       => 'required|string|max:255',
            'kategori_id' => 'required',
            'deskripsi'  => 'nullable|string',
        ]);

        try {
            $this->api->put("/unit-bisnis/{$id}", $request->only('nama', 'kategori_id', 'deskripsi'));
            return back()->with('success', 'Unit bisnis berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->api->delete("/unit-bisnis/{$id}");
            return redirect()->route('unit-bisnis.index')->with('success', 'Unit bisnis berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function tambahKpi(Request $request, $unitBisnisId)
    {
        $request->validate([
            'kpi_jenis_id' => 'required',
        ]);

        try {
            $this->api->post('/kpi-templates', [
                'unit_bisnis_id' => $unitBisnisId,
                'kpi_jenis_id'   => $request->kpi_jenis_id,
            ]);
            return back()->with('success', 'KPI berhasil ditambahkan.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function hapusKpi($kpiId)
    {
        try {
            $this->api->delete("/kpi-templates/{$kpiId}");
            return back()->with('success', 'KPI berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function setTarget(Request $request, $kpiId)
    {
        $request->validate([
            'unit_bisnis_id'   => 'required',
            'periode_bulan'    => 'required',
            'periode_tahun'    => 'required',
            'target'           => 'required|numeric',
            'threshold_hijau'  => 'required|numeric',
            'threshold_kuning' => 'required|numeric',
            'period_id'        => 'nullable',
        ]);

        try {
            $payload = [
                'unit_bisnis_id'   => $request->unit_bisnis_id,
                'kpi_template_id'  => $kpiId,
                'periode_bulan'    => $request->periode_bulan,
                'periode_tahun'    => $request->periode_tahun,
                'target'           => $request->target,
                'threshold_hijau'  => $request->threshold_hijau,
                'threshold_kuning' => $request->threshold_kuning,
            ];

            if ($request->filled('period_id')) {
                $this->api->put("/kpi-periods/{$request->period_id}", $payload);
            } else {
                $this->api->post('/kpi-periods', $payload);
            }

            return back()->with('success', 'Target KPI berhasil disimpan.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
