<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download Aplikasi Mobile</title>
    
    {{-- Menggunakan Tailwind CSS dari CDN agar ringan & cepat --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .bg-pattern {
            background-color: #f3f4f6;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23e5e7eb' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
    </style>
</head>
<body class="bg-pattern min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl overflow-hidden border border-gray-100">
        
        {{-- HEADER IMAGE / BRANDING --}}
        <div class="relative h-32 bg-gradient-to-r from-indigo-600 to-blue-500 flex items-center justify-center">
            <div class="absolute inset-0 bg-black opacity-10"></div>
            {{-- Logo Instansi (Ganti src dengan logo Anda jika ada) --}}
            <div class="w-20 h-20 bg-white rounded-2xl shadow-lg flex items-center justify-center transform translate-y-8">
                <i class="fas fa-bolt text-4xl text-indigo-600"></i>
            </div>
        </div>

        {{-- CONTENT BODY --}}
        <div class="pt-12 pb-8 px-6 text-center">
            
            <h1 class="text-2xl font-bold text-gray-800">Aplikasi TE Assistant</h1>
            <p class="text-sm text-gray-500 font-medium uppercase tracking-wide mt-1">
                {{ App\Models\AppSetting::findValue('app_company_label', null, 'PT PLN (Persero)') }}
            </p>

            {{-- VERSI BADGE --}}
            <div class="mt-4 flex justify-center gap-3">
                <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-bold border border-gray-200">
                    Versi {{ $version }}
                </span>
                <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-bold border border-gray-200">
                    {{ $fileSize }}
                </span>
            </div>

            {{-- TOMBOL DOWNLOAD --}}
            <div class="mt-8">
                @if($fileExists)
                    <a href="{{ $downloadUrl }}" class="block w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-lg shadow-indigo-500/30 transition transform active:scale-95 flex items-center justify-center gap-3">
                        <i class="fab fa-android text-2xl"></i>
                        <div class="text-left leading-tight">
                            <span class="block text-xs font-medium text-indigo-200 uppercase">Download APK</span>
                            <span class="block text-lg font-bold">Install Sekarang</span>
                        </div>
                    </a>
                    <p class="text-xs text-gray-400 mt-3">
                        *Diupdate: {{ \Carbon\Carbon::parse($lastUpload)->diffForHumans() }}
                    </p>
                @else
                    <button disabled class="w-full py-4 bg-gray-200 text-gray-400 rounded-xl cursor-not-allowed font-bold">
                        Belum Tersedia
                    </button>
                    <p class="text-xs text-red-400 mt-2">Admin belum mengupload file APK.</p>
                @endif
            </div>

            {{-- WHATS NEW SECTION --}}
            @if(!empty($updateMsg))
                <div class="mt-8 text-left bg-blue-50 rounded-xl p-4 border border-blue-100">
                    <h3 class="text-sm font-bold text-blue-800 mb-2 flex items-center gap-2">
                        <i class="fas fa-star text-yellow-500"></i> Apa yang baru?
                    </h3>
                    <p class="text-sm text-blue-700 leading-relaxed">
                        {!! nl2br(e($updateMsg)) !!}
                    </p>
                </div>
            @endif

        </div>

        {{-- FOOTER --}}
        <div class="bg-gray-50 px-6 py-4 text-center border-t border-gray-100">
            <p class="text-xs text-gray-400">
                &copy; {{ date('Y') }} Tim CloudTE Support. All rights reserved.
            </p>
        </div>
    </div>

</body>
</html>