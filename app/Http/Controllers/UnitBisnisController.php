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
            // biarkan kosong
        }

        $kategoriById = collect($kategori)->keyBy(fn ($k) => Format::pick($k, ['id', 'kategori_id']));

        try {
            $rawUnit = $this->api->get('/unit-bisnis')['data'] ?? [];

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
                $unit['nama']          = Format::pick($unit, ['nama', 'unit_bisnis_nama']);
                $unit['kategori_nama'] = Format::pick($unit, ['kategori_nama', 'kategori.nama']);
                // PENTING: kategori_id unit ini dipakai di bawah untuk
                // memfilter dropdown "Jenis KPI" (lihat blok kpiJenis),
                // supaya cuma KPI yang relevan dengan kategori unit ini
                // yang muncul (mis. unit kategori Properti hanya melihat
                // KPI Revenue, Jumlah Komplain, Biaya Maintenance, Progres
                // Pembangunan — bukan KPI milik kategori UMKM/Properti
                // Management).
                $unit['kategori_id']   = Format::pick($unit, ['kategori_id', 'kategori.id']);
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        try {
            $rawTemplates = $this->api->get("/kpi-templates/unit-bisnis/{$id}")['data'] ?? [];

            $templates = array_map(function ($t) {
                $kpiNama = Format::pick($t, ['kpi_jenis.nama', 'kpi_jenis_nama']);
                if (!$kpiNama) {
                    $kpiNama = Format::pick($t, ['nama']);
                }
                if (!$kpiNama) {
                    $kpiNama = 'KPI #' . ($t['id'] ?? '?');
                }

                $t['id']     = Format::pick($t, ['id', 'kpi_template_id']);
                $t['nama']   = $kpiNama;
                $t['satuan'] = Format::pick($t, ['satuan', 'kpi_jenis.satuan', 'kpi_jenis_satuan']);

                $formTemplate = $t['form_template'] ?? [];
                $t['form_template_id'] = $formTemplate['id'] ?? null;

                // PENTING: nested 'form_template' dari
                // /kpi-templates/unit-bisnis/{id} TIDAK menyertakan field
                // detail, jadi harus fetch /form-templates/{id} terpisah.
                // PERBAIKAN: key hasilnya adalah 'form_fields', BUKAN
                // 'fields' — sebelumnya salah baca 'fields' sehingga selalu
                // kosong walau data aslinya sudah ada di database.
                $allFields = [];
                if ($t['form_template_id']) {
                    try {
                        $formDetail = $this->api->get("/form-templates/{$t['form_template_id']}");
                        $allFields = $formDetail['data']['form_fields'] ?? [];
                    } catch (\Exception $e) {
                        $allFields = [];
                    }
                }

                // Normalisasi key: API pakai 'tipe'/'wajib', tapi Blade +
                // Alpine.js (modal Kelola Form & Tambah KPI) baca
                // 'type'/'required'. Tanpa ini, field yang sudah tersimpan
                // tampil tapi dropdown tipe-nya kosong saat modal dibuka.
                // Field KPI utama dibedakan dari field tambahan lewat
                // kpi_template_id (terisi = field KPI, null = field tambahan) —
                // BUKAN dari 'is_kpi_field', karena field itu ternyata selalu
                // false di API untuk kedua jenis field (tidak bisa dipercaya).
                $normalizedFields = array_map(function ($f) {
                    return [
                        'id'              => $f['id'] ?? null,
                        'label'           => $f['label'] ?? '',
                        'type'            => $f['tipe'] ?? ($f['type'] ?? 'text'),
                        'required'        => (bool) ($f['wajib'] ?? ($f['required'] ?? false)),
                        'options'         => $f['options'] ?? [],
                        'kpi_template_id' => $f['kpi_template_id'] ?? null,
                    ];
                }, $allFields);

                $t['form_fields'] = array_values(array_filter($normalizedFields, function ($f) {
                    return empty($f['kpi_template_id']);
                }));

                return $t;
            }, $rawTemplates);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        // PERBAIKAN: Jenis KPI sekarang difilter per KATEGORI unit bisnis
        // yang sedang dibuka, memakai parameter query baru dari API
        // (GET /kpi-jenis?kategori_id={id}). Sebelumnya endpoint ini
        // selalu dipanggil TANPA filter, sehingga dropdown "Tambah KPI"
        // menampilkan SEMUA jenis KPI dari semua kategori (Properti,
        // Properti Management, UMKM tercampur jadi satu) — bikin user bisa
        // salah pilih KPI yang sebenarnya tidak relevan untuk unit ini.
        // Kalau kategori_id unit tidak diketahui (mis. gagal fetch unit di
        // atas), fallback ke tanpa filter supaya dropdown tidak kosong sama
        // sekali.
        try {
            $kategoriId = $unit['kategori_id'] ?? null;
            $query = $kategoriId !== null ? ['kategori_id' => $kategoriId] : [];
            $kpiJenis = $this->api->get('/kpi-jenis', $query)['data'] ?? [];
        } catch (\Exception $e) {
            // biarkan kosong
        }

        try {
            $periodeAktif = $this->api->get('/periode/aktif')['data'] ?? null;
        } catch (\Exception $e) {
            // biarkan kosong
        }

        try {
            $allPeriods = $this->api->get('/kpi-periods')['data'] ?? [];
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
                $p['threshold_hijau']  = Format::pick($p, ['threshold_hijau'], 90);
                $p['threshold_kuning'] = Format::pick($p, ['threshold_kuning'], 70);
                $p['id'] = Format::pick($p, ['id']);
                return $p;
            };

            $normalized = array_map($normalize, $allPeriods);

            $filteredByUnit = array_filter($normalized, function ($p) use ($id) {
                $uid = Format::pick($p, ['unit_bisnis_id', 'unit_bisnis.id']);
                return $uid !== null && (int) $uid === (int) $id;
            });

            $periods = array_values(array_filter($filteredByUnit, function ($p) use ($bulanAktifNow, $tahunAktifNow) {
                return $p['periode_bulan'] !== null && $p['periode_tahun'] !== null
                    && (int) $p['periode_bulan'] === (int) $bulanAktifNow
                    && (int) $p['periode_tahun'] === (int) $tahunAktifNow;
            }));

            if (empty($periods) && !empty($filteredByUnit)) {
                $periods = array_values($filteredByUnit);
            }
        } catch (\Exception $e) {
            // biarkan kosong
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

    /**
     * Tambah KPI — BACKEND OTOMATIS BUAT FORM TEMPLATE
     * Kita hanya perlu menambahkan field tambahan (jika ada) ke form template yang sudah dibuat.
     */
    public function tambahKpi(Request $request, $unitBisnisId)
    {
        $request->validate([
            'kpi_jenis_id' => 'required|integer',
            'nama'         => 'nullable|string|max:255',
            'form_fields'  => 'nullable|json',
        ]);

        $formFields = json_decode($request->input('form_fields'), true);
        if (!empty($formFields) && is_array($formFields)) {
            foreach ($formFields as $f) {
                $label = trim($f['label'] ?? '');
                if (strcasecmp($label, 'KPI') === 0 || strcasecmp($label, 'Realisasi') === 0) {
                    return back()->with('error', 'Label "KPI" atau "Realisasi" tidak boleh digunakan untuk field tambahan.');
                }
            }
        }

        try {
            // 1. Buat KPI template — backend otomatis buat form template
            $kpiResponse = $this->api->post('/kpi-templates', [
                'unit_bisnis_id' => $unitBisnisId,
                'kpi_jenis_id'   => $request->kpi_jenis_id,
                'nama'           => $request->nama ?? null,
            ]);

            $kpiTemplateId = $kpiResponse['data']['id'] ?? null;
            if (!$kpiTemplateId) {
                throw new \Exception('Gagal membuat KPI template, ID tidak ditemukan.');
            }

            // 2. Ambil form template ID yang otomatis dibuat
            $formTemplateId = $kpiResponse['data']['form_template']['id'] ?? null;

            // 3. Jika ada field tambahan dan form template ditemukan, update form template
            if (!empty($formFields) && $formTemplateId) {
                $kpiDetail = $this->api->get("/kpi-templates/{$kpiTemplateId}");
                $existingForm = $kpiDetail['data']['form_template'] ?? null;
                $existingFields = $existingForm['fields'] ?? [];

                // Cari field KPI yang sudah ada (bawaan)
                $kpiField = null;
                foreach ($existingFields as $f) {
                    if (!empty($f['kpi_template_id'])) {
                        $kpiField = $f;
                        break;
                    }
                }

                $finalFields = [];
                if ($kpiField) {
                    $finalFields[] = $kpiField;
                }

                // Tambahkan field tambahan (urutan mulai 1)
                foreach ($formFields as $idx => $f) {
                    $finalFields[] = [
                        'label'    => $f['label'],
                        'tipe'     => $f['type'],
                        'wajib'    => (bool) ($f['required'] ?? false),
                        'urutan'   => $idx + 1,
                    ];
                }

                // Update form template dengan field tambahan
                $this->api->put("/form-templates/{$formTemplateId}", [
                    'fields' => $finalFields,
                ]);
            }

            return back()->with('success', 'KPI berhasil ditambahkan beserta form input.');
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

    /**
     * Update form template (kelola form)
     */
    public function updateFormTemplate(Request $request, $kpiId)
    {
        $request->validate([
            'fields_json' => 'required|string',
        ]);

        $fields = json_decode($request->input('fields_json'), true);
        if (!is_array($fields)) {
            return back()->with('error', 'Data field form tidak valid.');
        }

        $allowedTypes = ['text', 'number', 'date', 'select', 'textarea'];
        foreach ($fields as $i => $f) {
            $label = trim((string) ($f['label'] ?? ''));
            $type  = $f['type'] ?? '';
            if ($label === '') {
                return back()->with('error', 'Label field ke-' . ($i + 1) . ' tidak boleh kosong.');
            }
            if (strcasecmp($label, 'KPI') === 0 || strcasecmp($label, 'Realisasi') === 0) {
                return back()->with('error', 'Label "KPI" atau "Realisasi" tidak boleh digunakan untuk field tambahan.');
            }
            if (!in_array($type, $allowedTypes, true)) {
                return back()->with('error', 'Tipe field "' . $label . '" tidak valid.');
            }
            if ($type === 'select' && empty($f['options'])) {
                return back()->with('error', 'Field dropdown "' . $label . '" harus punya minimal 1 pilihan.');
            }
        }

        try {
            $kpiDetail = $this->api->get("/kpi-templates/{$kpiId}");
            $formTemplate = $kpiDetail['data']['form_template'] ?? null;
            $formTemplateId = $formTemplate['id'] ?? null;
            $unitBisnisId = $kpiDetail['data']['unit_bisnis_id'] ?? null;
            $kpiNama = $kpiDetail['data']['nama'] ?? 'Realisasi';

            if (!$formTemplateId) {
                if (!$unitBisnisId) {
                    throw new \Exception('Unit bisnis tidak ditemukan.');
                }
                // Buat form template baru jika belum ada
                $postFields = [];
                $postFields[] = [
                    'label'    => $kpiNama,
                    'tipe'     => 'number',
                    'wajib'    => true,
                    'urutan'   => 0,
                    'kpi_template_id' => $kpiId,
                ];
                foreach ($fields as $idx => $f) {
                    $postFields[] = [
                        'label'    => $f['label'],
                        'tipe'     => $f['type'],
                        'wajib'    => (bool) ($f['required'] ?? false),
                        'urutan'   => $idx + 1,
                    ];
                }
                $this->api->post('/form-templates', [
                    'unit_bisnis_id' => $unitBisnisId,
                    'nama'           => 'Form ' . $kpiNama,
                    'deskripsi'      => 'Dibuat dari kelola form',
                    'is_active'      => true,
                    'fields'         => $postFields,
                ]);
            } else {
                // Update existing
                // PERBAIKAN: key sebelumnya 'type', tapi API expect 'tipe'
                // (konsisten dengan blok "buat baru" di atas & method
                // tambahKpi()) — ini penyebab error "The fields.0.tipe field
                // is required when fields is present."
                $finalFields = [];
                $finalFields[] = [
                    'label'    => $kpiNama,
                    'tipe'     => 'number',
                    'wajib'    => true,
                    'urutan'   => 0,
                    'kpi_template_id' => $kpiId,
                ];
                foreach ($fields as $idx => $f) {
                    $finalFields[] = [
                        'label'    => $f['label'],
                        'tipe'     => $f['type'],
                        'wajib'    => (bool) ($f['required'] ?? false),
                        'urutan'   => $idx + 1,
                    ];
                }
                $this->api->put("/form-templates/{$formTemplateId}", [
                    'fields' => $finalFields,
                ]);
            }

            return back()->with('success', 'Form berhasil disimpan.');
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