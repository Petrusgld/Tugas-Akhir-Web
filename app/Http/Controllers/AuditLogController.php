<?php

namespace App\Http\Controllers;

use App\Services\ApiService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    protected ApiService $api;

    public function __construct(ApiService $api)
    {
        $this->api = $api;
    }

    public function index(Request $request)
    {
        $logs  = [];
        $error = null;

        try {
            if ($request->filled('user_id')) {
                $logs = $this->api->get("/audit-logs/user/{$request->user_id}")['data'] ?? [];
            } else {
                $logs = $this->api->get('/audit-logs')['data'] ?? [];
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return view('audit-log.index', compact('logs', 'error'));
    }
}
