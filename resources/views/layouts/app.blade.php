<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') · KPI Master Emanuel Corp</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#F0FDF4',
                            100: '#DCFCE7',
                            500: '#22C55E',
                            600: '#16A34A',
                            700: '#15803D',
                            900: '#14532D',
                        },
                    },
                },
            },
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <style>
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

    <div class="flex min-h-screen">

        {{-- ============ SIDEBAR ============ --}}
        <aside class="w-64 bg-gray-900 text-gray-300 fixed inset-y-0 left-0 flex flex-col z-40">
            <div class="px-6 py-6 border-b border-gray-800">
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-lg bg-brand-500 flex items-center justify-center text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18" /><path d="M7 15l4-5 3 3 5-7" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-semibold leading-tight">KPI Master</p>
                        <p class="text-xs text-gray-500 leading-tight">Emanuel Corp Holding</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                @php
                    $navItems = [
                        [
                            'route' => 'dashboard', 'label' => 'Dashboard',
                            'icon' => '<path d="M3 12l9-9 9 9" /><path d="M5 10v10a1 1 0 001 1h4a1 1 0 001-1v-4a1 1 0 011-1h0a1 1 0 011 1v4a1 1 0 001 1h4a1 1 0 001-1V10" />',
                        ],
                        [
                            'route' => 'unit-bisnis.index', 'label' => 'Unit Bisnis & KPI',
                            'icon' => '<path d="M3 21h18" /><path d="M5 21V7l7-4 7 4v14" /><path d="M9 9h1M14 9h1M9 13h1M14 13h1M9 17h1M14 17h1" />',
                        ],
                        [
                            'route' => 'karyawan.index', 'label' => 'Karyawan',
                            'icon' => '<circle cx="9" cy="8" r="3.2" /><path d="M3 20c0-3.5 2.7-6 6-6s6 2.5 6 6" /><path d="M16 4.2a3.2 3.2 0 010 6.2" /><path d="M21 20c0-3-1.9-5.3-4.5-5.9" />',
                        ],
                        [
                            'route' => 'kpi-validasi.index', 'label' => 'Validasi Input KPI',
                            'icon' => '<path d="M9 12l2 2 4-4" /><circle cx="12" cy="12" r="9" />',
                        ],
                        [
                            'route' => 'leaderboard.index', 'label' => 'Leaderboard',
                            'icon' => '<path d="M8 21h8M12 17v4" /><path d="M7 4h10v6a5 5 0 01-10 0V4z" /><path d="M7 6H4a3 3 0 003 3M17 6h3a3 3 0 01-3 3" />',
                        ],
                    ];
                @endphp

                @foreach ($navItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition
                              {{ request()->routeIs($item['route']) || request()->routeIs(explode('.', $item['route'])[0].'.*')
                                    ? 'bg-brand-500 text-white'
                                    : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            {!! $item['icon'] !!}
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="px-4 py-4 border-t border-gray-800" x-data="{ logoutConfirm: false }">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-9 h-9 rounded-full bg-brand-600 flex items-center justify-center text-white text-sm font-semibold">
                        {{ strtoupper(substr(session('user.name', 'A'), 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ session('user.name', 'Admin') }}</p>
                        <p class="text-xs text-gray-500 capitalize">{{ session('user.role', '-') }}</p>
                    </div>
                </div>

                <button @click="logoutConfirm = true" type="button"
                        class="w-full text-sm text-center py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 hover:text-white transition">
                    Keluar
                </button>

                {{-- MODAL KONFIRMASI LOGOUT --}}
                <div x-show="logoutConfirm" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                    <div @click.outside="logoutConfirm = false" class="bg-white rounded-2xl p-6 w-full max-w-sm text-center">
                        <div class="w-12 h-12 mx-auto rounded-full bg-red-50 flex items-center justify-center mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" />
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900 mb-1">Keluar dari sistem?</h3>
                        <p class="text-sm text-gray-500 mb-6">Anda perlu login kembali untuk mengakses dashboard.</p>
                        <form action="{{ route('logout') }}" method="POST" class="flex justify-center gap-3">
                            @csrf
                            <button type="button" @click="logoutConfirm = false" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800">Batal</button>
                            <button type="submit" class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-xl">Ya, Keluar</button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        {{-- ============ MAIN CONTENT ============ --}}
        <div class="flex-1 ml-64 flex flex-col min-h-screen">

            {{-- TOP BAR --}}
            <header class="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between sticky top-0 z-30">
                <h1 class="text-lg font-semibold text-gray-900">@yield('title', 'Dashboard')</h1>
                <p class="text-sm text-gray-500">{{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}</p>
            </header>

            {{-- FLASH MESSAGES --}}
            @if (session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                     x-transition class="mx-8 mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                        {{ session('success') }}
                    </span>
                    <button @click="show = false" class="text-green-600 hover:text-green-800">&times;</button>
                </div>
            @endif

            @if (session('error'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                     x-transition class="mx-8 mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16h.01" /></svg>
                        {{ session('error') }}
                    </span>
                    <button @click="show = false" class="text-red-600 hover:text-red-800">&times;</button>
                </div>
            @endif

            @if ($errors->any())
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                     x-transition class="mx-8 mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <main class="flex-1 p-8">
                @yield('content')
            </main>
        </div>
    </div>

</body>
</html>
