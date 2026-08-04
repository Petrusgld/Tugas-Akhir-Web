<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk · KPI Master Emanuel Corp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: { 50: '#F0FDF4', 500: '#22C55E', 600: '#16A34A', 700: '#15803D', 900: '#14532D' } } } }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-gray-50">

    <div class="min-h-screen grid grid-cols-1 lg:grid-cols-2">

        {{-- LEFT: BRAND PANEL --}}
        <div class="hidden lg:flex flex-col justify-between bg-gray-900 text-white p-12 relative overflow-hidden">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-brand-500/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-32 -left-16 w-80 h-80 bg-brand-500/10 rounded-full blur-3xl"></div>

            <div class="relative flex items-center gap-2">
                <div class="w-9 h-9 rounded-lg bg-brand-500 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18" /><path d="M7 15l4-5 3 3 5-7" />
                    </svg>
                </div>
                <span class="font-semibold">KPI Master</span>
            </div>

            <div class="relative">
                <h1 class="text-3xl font-bold leading-snug mb-4">
                    Pantau performa<br />seluruh unit bisnis<br />dalam satu dashboard.
                </h1>
                <p class="text-gray-400 text-sm max-w-sm">
                    Web admin KPI Master membantu Anda memantau pencapaian, mengelola karyawan,
                    dan meninjau laporan seluruh unit bisnis Emanuel Corp Holding secara real-time.
                </p>
            </div>

            <p class="relative text-xs text-gray-500">© {{ date('Y') }} Emanuel Corp Holding</p>
        </div>

        {{-- RIGHT: LOGIN FORM --}}
        <div class="flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-sm">

                <div class="lg:hidden flex items-center gap-2 mb-8">
                    <div class="w-9 h-9 rounded-lg bg-brand-500 flex items-center justify-center text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18" /><path d="M7 15l4-5 3 3 5-7" />
                        </svg>
                    </div>
                    <span class="font-semibold text-gray-900">KPI Master</span>
                </div>

                <h2 class="text-2xl font-bold text-gray-900 mb-1">Masuk ke akun Anda</h2>
                <p class="text-sm text-gray-500 mb-8">Emanuel Corp Holding · Web Admin</p>

                @if (session('error'))
                    <div class="mb-5 bg-red-50 border border-red-100 text-red-700 text-sm px-4 py-3 rounded-xl flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16h.01" /></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                <form action="{{ route('login.post') }}" method="POST" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                               placeholder="nama@emanuelcorp.id"
                               class="w-full px-4 py-3 border border-gray-200 bg-gray-50 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition">
                        @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div x-data="{ show: false }">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" name="password" required
                                   placeholder="Masukkan password"
                                   class="w-full px-4 py-3 border border-gray-200 bg-gray-50 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition pr-11">
                            <button type="button" @click="show = !show"
                                    class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600">
                                <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" /><circle cx="12" cy="12" r="3" /></svg>
                                <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0112 20c-7 0-11-7-11-7a21.6 21.6 0 015.06-6.06M9.9 4.24A10.4 10.4 0 0112 4c7 0 11 7 11 7a21.6 21.6 0 01-2.61 3.68M14.12 14.12a3 3 0 11-4.24-4.24" /><path d="M1 1l22 22" /></svg>
                            </button>
                        </div>
                        @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit"
                            class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-3 rounded-xl transition shadow-lg shadow-brand-500/25">
                        Masuk ke Sistem
                    </button>
                </form>

                <p class="text-xs text-gray-400 text-center mt-8">
                    Hanya untuk akun admin, owner, dan manajer unit bisnis.
                </p>
            </div>
        </div>
    </div>

</body>
</html>
