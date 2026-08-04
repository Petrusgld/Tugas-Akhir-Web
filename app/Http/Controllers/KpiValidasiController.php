<?php

namespace App\Http\Controllers;

use App\Helpers\Format;
use App\Services\ApiService;
use App\Services\KpiPeriodFlattener;
use Illuminate\Http\Request;

class KpiValidasiController extends Controller
{
    protected ApiService $api;

    public function __construct(ApiService $api)
    {
        $this->api = $api;
    }

    public function index(Request $request)
    {
        $periods = [];
        $error   = null;

        // Peta user (id => nama), dipakai KpiPeriodFlattener
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
            // biarkan kosong, nama user fallback ke '-'
        }

        try {
            $rawPeriods = $this->api->get('/kpi-periods')['data'] ?? [];
            $periods    = KpiPeriodFlattener::flatten($rawPeriods, $usersById);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return view('kpi-validasi.index', compact('periods', 'error'));
    }

    public function updateRealisasi(Request $request, $id)
    {
        $request->validate([
            'realisasi' => 'required|numeric',
            'catatan'   => 'nullable|string',
        ]);

        try {
            $this->api->put("/kpi-periods/{$id}/realisasi", $request->only('realisasi', 'catatan'));
            return back()->with('success', 'Input KPI berhasil divalidasi/diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}