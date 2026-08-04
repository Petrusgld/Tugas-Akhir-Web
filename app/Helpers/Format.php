<?php

namespace App\Helpers;

class Format
{
    /**
     * Ambil nilai pertama yang ditemukan dari beberapa kemungkinan nama field/path
     * (mendukung dot-notation untuk data nested, misal 'kpi_jenis.nama').
     *
     * Berguna karena field yang dikembalikan API kadang berbeda nama dengan yang
     * diasumsikan di frontend (lihat catatan di SETUP.md). Dengan helper ini kita
     * cukup menambah alias baru di satu tempat setiap kali menemukan nama field
     * lain dari API, tanpa mengubah setiap view satu per satu.
     */
    public static function pick(array|null $item, array $keys, $default = null)
    {
        if (!$item) {
            return $default;
        }

        foreach ($keys as $key) {
            if (str_contains($key, '.')) {
                $value = data_get($item, $key);
            } else {
                $value = $item[$key] ?? null;
            }

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Tentukan apakah sebuah akun/entitas berstatus aktif, dari berbagai
     * kemungkinan nama field yang dipakai API (status, aktif, is_active, active,
     * deleted_at). Default-nya AKTIF hanya jika benar-benar tidak ada satupun
     * indikator status pada payload.
     */
    public static function isAktif(array|null $item): bool
    {
        if (!$item) {
            return true;
        }

        if (array_key_exists('status', $item) && $item['status'] !== null) {
            return in_array(strtolower((string) $item['status']), ['aktif', 'active', '1', 'true']);
        }

        foreach (['aktif', 'is_active', 'active'] as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null) {
                return $item[$key] === true || in_array($item[$key], [1, '1', 'aktif', 'active'], true);
            }
        }

        if (array_key_exists('deleted_at', $item)) {
            return empty($item['deleted_at']);
        }

        return true;
    }

    /**
     * Format angka menjadi format Rupiah singkat, contoh: Rp 90.000.000 -> Rp90jt
     */
    public static function rupiah(float|int|string|null $val, bool $short = false): string
    {
        $val = (float) $val;

        if ($short) {
            if ($val >= 1_000_000_000) return 'Rp' . number_format($val / 1_000_000_000, 1) . 'M';
            if ($val >= 1_000_000)     return 'Rp' . number_format($val / 1_000_000, 0) . 'jt';
            if ($val >= 1_000)         return 'Rp' . number_format($val / 1_000, 0) . 'rb';
        }

        return 'Rp' . number_format($val, 0, ',', '.');
    }

    /**
     * Format angka biasa dengan pemisah ribuan titik ala Indonesia (1.000.000),
     * dan tambahkan awalan "Rp" otomatis jika satuan KPI-nya berupa mata uang.
     */
    public static function angka(float|int|string|null $val, ?string $satuan = null): string
    {
        $val    = (float) $val;
        $angka  = number_format($val, 0, ',', '.');
        $satuan = strtolower(trim($satuan ?? ''));

        if (in_array($satuan, ['rp', 'rupiah', 'idr']) || str_contains($satuan, 'rupiah')) {
            return 'Rp ' . $angka;
        }

        return $angka;
    }

    /**
     * Kelas warna Tailwind berdasarkan status KPI (hijau/kuning/merah)
     */
    public static function statusColor(?string $status, string $type = 'bg'): string
    {
        $status = strtolower($status ?? '');

        return match ($status) {
            'hijau'  => match ($type) {
                'bg'     => 'bg-green-500',
                'bgLight'=> 'bg-green-100',
                'text'   => 'text-green-700',
                'border' => 'border-green-500',
                default  => 'green',
            },
            'kuning' => match ($type) {
                'bg'     => 'bg-yellow-500',
                'bgLight'=> 'bg-yellow-100',
                'text'   => 'text-yellow-700',
                'border' => 'border-yellow-500',
                default  => 'yellow',
            },
            'merah'  => match ($type) {
                'bg'     => 'bg-red-500',
                'bgLight'=> 'bg-red-100',
                'text'   => 'text-red-700',
                'border' => 'border-red-500',
                default  => 'red',
            },
            default  => match ($type) {
                'bg'     => 'bg-gray-400',
                'bgLight'=> 'bg-gray-100',
                'text'   => 'text-gray-600',
                'border' => 'border-gray-300',
                default  => 'gray',
            },
        };
    }

    /**
     * Progress bar color berdasarkan realisasi vs threshold
     */
    public static function progressColor(float $percent, float $thresholdHijau = 90, float $thresholdKuning = 70): string
    {
        if ($percent >= $thresholdHijau) return 'bg-green-500';
        if ($percent >= $thresholdKuning) return 'bg-yellow-500';
        return 'bg-red-500';
    }

    /**
     * Nama bulan dalam Bahasa Indonesia
     */
    public static function namaBulan(int $bulan): string
    {
        $bulanList = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $bulanList[$bulan] ?? '-';
    }
}
