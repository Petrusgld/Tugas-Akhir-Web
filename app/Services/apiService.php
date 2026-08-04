<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class ApiService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('api.base_url');
    }

    private function headers(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        if ($token = Session::get('api_token')) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $headers;
    }

    public function get(string $endpoint, array $params = []): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}{$endpoint}", $params);

        return $this->handle($response);
    }

    public function post(string $endpoint, array $data = []): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}{$endpoint}", $data);

        return $this->handle($response);
    }

    public function put(string $endpoint, array $data = []): array
    {
        $response = Http::withHeaders($this->headers())
            ->put("{$this->baseUrl}{$endpoint}", $data);

        return $this->handle($response);
    }

    public function patch(string $endpoint, array $data = []): array
    {
        $response = Http::withHeaders($this->headers())
            ->patch("{$this->baseUrl}{$endpoint}", $data);

        return $this->handle($response);
    }

    public function delete(string $endpoint): array
    {
        $response = Http::withHeaders($this->headers())
            ->delete("{$this->baseUrl}{$endpoint}");

        return $this->handle($response);
    }

    private function handle($response): array
    {
        $body = $response->json() ?? [];

        if ($response->successful()) {
            return $body;
        }

        $message = $body['message'] ?? 'Terjadi kesalahan pada server (' . $response->status() . ')';

        // Laravel-style API biasanya mengirim rincian error per-field di "errors"
        // ({"field": ["pesan 1", "pesan 2"]}) — tampilkan supaya mudah didebug.
        if (!empty($body['errors']) && is_array($body['errors'])) {
            $details = [];
            foreach ($body['errors'] as $field => $msgs) {
                $details[] = is_array($msgs) ? implode(', ', $msgs) : $msgs;
            }
            if ($details) {
                $message .= ' — ' . implode(' | ', $details);
            }
        }

        throw new \Exception($message);
    }
}
