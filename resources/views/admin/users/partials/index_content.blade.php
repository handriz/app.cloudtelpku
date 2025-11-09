{{-- Wadah untuk notifikasi AJAX (Tambah/Edit/Hapus Pengguna) --}}
<div id="kddk-notification-container" class="space-y-6">

{{-- Kontainer utama yang menyusun semua bagian secara vertikal --}}
<div class="space-y-6">

    {{-- ====================================================== --}}
    {{-- BAGIAN ATAS (Tombol Aksi di Kiri, Pencarian di Kanan) --}}
    {{-- ====================================================== --}}
    <div class="flex flex-col md:flex-row justify-between items-start gap-6">
        
        {{-- Tombol Aksi (Kiri) --}}
        <div class="flex-shrink-0 flex space-x-2">

        </div>
        
        {{-- Form Pencarian (Kanan) - Versi Baru --}}
        <div class="w-full md:w-1/3">
            <form id="user-search-form" action="{{ route('manajemen-pengguna.users.index') }}" autocomplete="off" method="GET">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-search text-gray-400"></i>
                    </span>
                    <input type="text" name="search" value="{{ $search ?? '' }}" 
                           class="w-full pl-10 pr-10 py-2 border rounded-md dark:bg-gray-700 dark:border-gray-600" 
                           placeholder="Cari Nama atau Email..." autocomplete="off">
                    <button type="button" id="clear-search-button" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 {{ (isset($search) && $search) ? '' : 'hidden' }}">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </button>                
                </div>
            </form>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- BAGIAN BAWAH (Tabel Data) --}}
    {{-- ====================================================== --}}
    <div class="w-full">
        <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="text-xs text-gray-900 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-200">
                    {{-- HEADER TABEL ASLI ANDA --}}
                    <tr>
                        <th scope="col" class="py-2 px-6 w-16">No.</th>
                        <th scope="col" class="py-2 px-6 text-left">Nama</th>
                        <th scope="col" class="py-2 px-6 text-left">Email</th>
                        <th scope="col" class="py-2 px-6 text-left">Peran</th>
                        <th scope="col" class="py-2 px-6 text-left">Level Akses</th>
                        <th scope="col" class="py-2 px-6 text-left">Akses Web</th>
                        <th scope="col" class="py-2 px-6 text-left">Akses Mobile</th>
                        <th scope="col" class="relative py-2 px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    {{-- ISI TABEL ASLI ANDA --}}
                    @forelse ($users as $index => $user)
                        <tr class="text-sm text-gray-700 dark:text-gray-300 {{ $user->id == Auth::id() ? 'bg-red-50 dark:bg-red-900/50 font-bold' : '' }}">
                            <td class="py-2 px-6 text-center font-medium">{{ $users->firstItem() + $index }}</td>
                            <td class="py-2 px-6 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</td>
                            <td class="py-2 px-6 whitespace-nowrap">{{ $user->email }}</td>
                            <td class="py-2 px-6 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    {{ $user->role->description ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="py-2 px-6 whitespace-nowrap">{{ $user->hierarchyLevel->name ?? 'N/A' }}</td>
                            <td class="py-2 px-6 whitespace-nowrap text-center">
                                @if ($user->is_approved)
                                    <i class="fas fa-check-circle text-green-500 fa-lg" title="Disetujui"></i>
                                @else
                                    <i class="fas fa-clock text-yellow-500 fa-lg" title="Menunggu Persetujuan"></i>
                                @endif
                            </td>
                            <td class="py-2 px-6 whitespace-nowrap text-center">
                                @if ($user->mobile_app)
                                    <i class="fas fa-check-circle text-green-500 fa-lg" title="Ya"></i>
                                @else
                                    <i class="fas fa-times-circle text-gray-400 fa-lg" title="Tidak"></i>
                                @endif
                            </td>
                            <td class="py-2 px-6 whitespace-nowrap text-right font-medium">
                                {{-- Tombol Edit --}}
                                @can('update', $user)
                                    <a href="{{ route('manajemen-pengguna.users.edit', $user) }}" 
                                       class="text-indigo-600 hover:text-indigo-900 mr-4"
                                       data-modal-link="true"
                                       title="Edit Pengguna">
                                        <i class="fas fa-pencil-alt fa-lg"></i>
                                    </a>
                                @endcan

                                {{-- Tombol Hapus --}}
                                @can('delete', $user)
                                    <a href="#" 
                                       class="text-red-600 hover:text-red-900" 
                                       data-delete-url="{{ route('manajemen-pengguna.users.destroy', $user) }}" 
                                       data-user-name="{{ $user->name }}"
                                       title="Hapus Pengguna">
                                        <i class="fas fa-trash-alt fa-lg"></i>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-4 px-6 text-center text-gray-500"> {{-- Pastikan colspan="8" --}}
                                @if(isset($search) && $search)
                                    Tidak ada pengguna ditemukan untuk pencarian "{{ $search }}".
                                @else
                                    Tidak ada data pengguna ditemukan.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        {{-- Link Paginasi --}}
        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </div>
</div>