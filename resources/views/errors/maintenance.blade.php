<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sedang Pemeliharaan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">

    <div class="max-w-lg w-full bg-white p-8 rounded-lg shadow-lg text-center mx-4">
        
        {{-- Ikon Maintenance --}}
        <div class="mb-6">
            <div class="h-24 w-24 bg-red-100 rounded-full flex items-center justify-center mx-auto text-red-500">
                <i class="fas fa-tools text-4xl"></i>
            </div>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">Sedang Pemeliharaan</h1>
        
        <p class="text-gray-600 mb-8 leading-relaxed">
            Mohon maaf, aplikasi sedang dalam perbaikan sistem atau pembaruan database.
            <br>
            Akses ditutup sementara untuk User selain Administrator.
        </p>

        {{-- INFO LOGIN (Opsional, memberitahu user siapa mereka) --}}
        @if(Auth::check())
            <div class="bg-gray-50 border border-gray-200 rounded p-3 mb-6 text-sm text-gray-500">
                Anda login sebagai: <span class="font-bold text-gray-700">{{ Auth::user()->name }}</span>
            </div>
        @endif

        {{-- TOMBOL LOGOUT (SOLUSI MASALAH ANDA) --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" 
                    class="w-full sm:w-auto px-6 py-2.5 bg-gray-800 hover:bg-gray-900 text-white font-medium rounded-lg transition duration-200 flex items-center justify-center mx-auto gap-2">
                <i class="fas fa-sign-out-alt"></i> Keluar (Logout)
            </button>
        </form>
        
        <p class="mt-6 text-xs text-gray-400">
            &copy; {{ date('Y') }} Tim IT Support
        </p>
    </div>

</body>
</html>