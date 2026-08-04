<?php

namespace App\Http\Controllers;

use App\Services\ApiService;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    protected ApiService $api;

    public function __construct(ApiService $api)
    {
        $this->api = $api;
    }

    public function index(Request $request)
    {
        $laporanHarian = [];
        $laporanSop    = [];
        $error         = null;

        try {
            $laporanHarian = $this->api->get('/laporan-harian')['data'] ?? [];
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        try {
            $laporanSop = $this->api->get('/laporan-sop')['data'] ?? [];
        } catch (\Exception $e) {
            //
        }

        return view('laporan.index', compact('laporanHarian', 'laporanSop', 'error'));
    }

    public function destroyHarian($id)
    {
        try {
            $this->api->delete("/laporan-harian/{$id}");
            return back()->with('success', 'Laporan harian berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reviewSop(Request $request, $id)
    {
        $request->validate([
            'status'  => 'required|in:approved,rejected',
            'catatan' => 'nullable|string',
        ]);

        try {
            $this->api->patch("/laporan-sop/{$id}/review", $request->only('status', 'catatan'));
            return back()->with('success', 'Laporan SOP berhasil direview.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroySop($id)
    {
        try {
            $this->api->delete("/laporan-sop/{$id}");
            return back()->with('success', 'Laporan SOP berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
