<?php

namespace App\Services;

use App\Helpers\Format;

/**
 * Ubah data /kpi-periods (satu record per KPI per periode, berisi nilai
 * realisasi akumulasi) menjadi satu baris per input/submission karyawan.
 * Dipakai oleh halaman Validasi Input KPI dan "Aktivitas Terbaru" di
 * Dashboard supaya logikanya tidak duplikat di dua tempat.
 */
class KpiPeriodFlattener
{
    public const HISTORY_KEYS = ['riwayat_realisasi', 'riwayat', 'histori', 'history', 'inputs', 'realisasi_log', 'logs', 'entries', 'detail_realisasi'];

    public const USER_KEYS = [
        'user_nama', 'user.name', 'user.nama', 'karyawan_nama', 'karyawan.nama',
        'input_by', 'input_by_nama', 'created_by_nama', 'nama_user', 'pengguna.nama',
        'user.username', 'karyawan.name', 'created_by.name', 'created_by.nama',
        'diinput_oleh', 'diinput_oleh_nama', 'submitted_by_nama',
    ];

    /**
     * Kemungkinan nama field foreign-key user (hanya berisi ID, bukan nama)
     * pada record period/entry. Dipakai untuk menebak nama user lewat daftar
     * /users kalau field nama langsung (USER_KEYS di atas) tidak ditemukan.
     */
    public const USER_ID_KEYS = [
        'user_id', 'karyawan_id', 'created_by', 'created_by_id', 'input_by_id',
        'diinput_oleh_id', 'submitted_by', 'submitted_by_id', 'pengguna_id',
    ];

    /**
     * @param array $periods   Data mentah dari /kpi-periods
     * @param array $usersById Peta [id => nama] dari /users, untuk resolve user
     *                         lewat foreign key kalau nama tidak ada langsung di record.
     */
    public static function flatten(array $periods, array $usersById = []): array
    {
        $rows = [];

        foreach ($periods as $period) {
            $history = null;
            foreach (self::HISTORY_KEYS as $key) {
                if (!empty($period[$key]) && is_array($period[$key])) {
                    $history = $period[$key];
                    break;
                }
            }

            $base = [
                'kpi_period_id'    => Format::pick($period, ['id']),
                'unit_bisnis_id'   => Format::pick($period, ['unit_bisnis_id', 'unit_bisnis.id']),
                'unit_bisnis_nama' => Format::pick($period, ['unit_bisnis_nama', 'unit_bisnis.nama']),
                'kpi_nama'         => Format::pick($period, [
                    'kpi_nama', 'kpi_jenis.nama', 'kpi_jenis_nama', 'nama_kpi', 'jenis_kpi',
                    'kpi_template.nama', 'kpi_template.kpi_jenis.nama', 'kpi.nama', 'kpi_master.nama',
                ]),
                'satuan'           => Format::pick($period, ['satuan', 'kpi_jenis.satuan', 'kpi_jenis_satuan', 'kpi_template.satuan']),
                'periode_bulan'    => Format::pick($period, ['periode_bulan']),
                'periode_tahun'    => Format::pick($period, ['periode_tahun']),
                'target'           => Format::pick($period, ['target'], 0),
                'status'           => Format::pick($period, ['status']),
                '_raw'             => $period,
            ];

            if ($history) {
                foreach ($history as $entry) {
                    $rows[] = array_merge($base, [
                        'id'         => Format::pick($entry, ['id']) ?? $base['kpi_period_id'],
                        'user_nama'  => self::resolveUserNama($entry, $usersById),
                        'realisasi'  => Format::pick($entry, ['realisasi', 'nilai'], 0),
                        'catatan'    => Format::pick($entry, ['catatan']),
                        'created_at' => Format::pick($entry, ['created_at', 'waktu', 'tanggal']),
                    ]);
                }
            } else {
                $rows[] = array_merge($base, [
                    'id'         => $base['kpi_period_id'],
                    'user_nama'  => self::resolveUserNama($period, $usersById),
                    'realisasi'  => Format::pick($period, ['realisasi'], 0),
                    'catatan'    => Format::pick($period, ['catatan']),
                    'created_at' => Format::pick($period, ['updated_at', 'created_at']),
                ]);
            }
        }

        return $rows;
    }

    /**
     * Coba temukan nama user dari field nama langsung dulu; kalau tidak ada,
     * cari ID user pada record lalu cocokkan ke peta /users ($usersById).
     */
    protected static function resolveUserNama(array $entry, array $usersById): ?string
    {
        $nama = Format::pick($entry, self::USER_KEYS);
        if ($nama !== null) {
            return $nama;
        }

        if (empty($usersById)) {
            return null;
        }

        $userId = Format::pick($entry, self::USER_ID_KEYS);
        if ($userId === null) {
            return null;
        }

        return $usersById[$userId] ?? null;
    }
}
