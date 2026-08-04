<?php

namespace App\Http\Controllers;

use App\Services\ApiService;
use App\Services\KpiAggregator;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
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
        $leaderboard  = [];
        $error        = null;

        try {
            $periodeAktif = $this->api->get('/periode/aktif')['data'] ?? null;
        } catch (\Exception $e) {
            //
        }

        $bulan = $request->get('bulan', $periodeAktif['bulan'] ?? now()->month);
        $tahun = $request->get('tahun', $periodeAktif['tahun'] ?? now()->year);

        // Leaderboard DIHITUNG SENDIRI dari /unit-bisnis + /kpi-templates +
        // /kpi-periods (lihat App\Services\KpiAggregator), karena endpoint
        // /leaderboard bawaan API ternyata mengembalikan 0% untuk semua unit
        // walaupun unit tersebut sudah punya realisasi.
        $agg = $this->aggregator->unitAchievements((int) $bulan, (int) $tahun);
        $error = $agg['error'];

        $leaderboard = collect($agg['units'])
            ->sortByDesc('achievement')
            ->values()
            ->all();

        return view('leaderboard.index', compact('periodeAktif', 'leaderboard', 'error', 'bulan', 'tahun'));
    }
}
