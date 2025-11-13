{{-- 
  File: resources/views/admin/menu/partials/index_content.blade.php
  (Versi ini sudah kompatibel dengan tab-manager.js)
--}}

{{-- 1. Kontainer Notifikasi (PENTING untuk AJAX) --}}
<div id="kddk-notification-container">
    {{-- Notifikasi Sukses dari full page reload (jika terjadi) --}}
    @if (session('success'))
        <div id="success-alert"
             class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg shadow-md relative w-full mb-4">
            <strong class="font-bold">Berhasil!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
            <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer"
                  onclick="document.getElementById('success-alert').style.display='none'">
                <svg class="fill-current h-5 w-5 text-green-500 dark:text-green-300"
                     role="button"
                     xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 20 20">
                    <title>Close</title>
                    <path
                        d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l3.029-2.651-3.029-2.651a1.2 1.2 0 0 1 1.697-1.697L10 8.183l2.651-3.029a1.2 1.2 0 1 1 1.697 1.697L11.819 10l3.029 2.651a1.2 1.2 0 0 1 0 1.698z" />
                </svg>
            </span>
        </div>
    @endif

    {{-- Notifikasi Error --}}
    @if (session('error'))
        <div id="error-alert"
             class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg shadow-md relative w-full mb-4">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
            <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer"
                  onclick="document.getElementById('error-alert').style.display='none'">
                <svg class="fill-current h-5 w-5 text-red-500 dark:text-red-300"
                     role="button"
                     xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 20 20">
                    <title>Close</title>
                    <path
                        d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l3.029-2.651-3.029-2.651a1.2 1.2 0 0 1 1.697-1.697L10 8.183l2.651-3.029a1.2 1.2 0 1 1 1.697 1.697L11.819 10l3.029 2.651a1.2 1.2 0 0 1 0 1.698z" />
                </svg>
            </span>
        </div>
    @endif
</div>

<div class="pt-0 pb-0">
    {{-- Kartu Konten Utama --}}
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg w-full">
        <div class="p-6 text-gray-900 dark:text-gray-100 w-full">

            {{-- Header --}}
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Control Menu Aplikasi</h3>
                
                {{-- PERBAIKAN: Tombol "Tambah Menu" diubah agar membuka modal --}}
                <a href="{{ route('admin.menu.create') }}" 
                   data-modal-link="true" {{-- <-- ATRIBUT PENTING UNTUK MODAL --}}
                   class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-2 transition ease-in-out duration-150">
                    <i class="fas fa-plus mr-2"></i> Tambah Menu Baru
                </a>
            </div>

            {{-- Tabel --}}
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 table-auto">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                Menu Item
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                Rute
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                Ikon
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                Izin
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                Urutan
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                Aktif
                            </th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($menuItems as $menuItem)
                            {{-- Menu Utama --}}
                            <tr class="bg-gray-50 dark:bg-gray-700">
                                <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    <i class="{{ $menuItem->icon }} mr-2 text-indigo-500"></i>
                                    {{ $menuItem->name }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $menuItem->route_name ?? '-' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $menuItem->icon ?? '-' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        {{ $menuItem->permission_name ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-700 dark:text-indigo-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $menuItem->permission_name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $menuItem->order }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm">
                                    @if ($menuItem->is_active)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100">Aktif</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-center text-sm font-medium">
                                    {{-- PERBAIKAN: Link "Edit" (data-modal-link) --}}
                                    <a href="{{ route('admin.menu.edit', ['menu' => $menuItem->id]) }}"
                                       data-modal-link="true" {{-- <-- ATRIBUT PENTING UNTUK MODAL --}}
                                       class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-500 mr-2">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    
                                    {{-- PERBAIKAN: Tombol "Hapus" (data-delete-url) --}}
                                    <button type="button"
                                            data-delete-url="{{ route('admin.menu.destroy', $menuItem->id) }}" {{-- <-- ATRIBUT PENTING UNTUK HAPUS AJAX --}}
                                            data-user-name="{{ $menuItem->name }}" {{-- <-- Atribut untuk konfirmasi --}}
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-500">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </td>
                            </tr>

                            {{-- Sub-menu --}}
                            @foreach ($menuItem->children as $childMenuItem)
                                <tr>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100 pl-8">
                                        <i class="{{ $childMenuItem->icon ?? 'far fa-dot-circle' }} mr-2 text-gray-500 dark:text-gray-400"></i>
                                        {{ $childMenuItem->name }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $childMenuItem->route_name ?? '-' }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $childMenuItem->icon ?? '-' }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            {{ $childMenuItem->permission_name ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-700 dark:text-indigo-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                            {{ $childMenuItem->permission_name ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $childMenuItem->order }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm">
                                        @if ($childMenuItem->is_active)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100">Aktif</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-center text-sm font-medium">
                                        {{-- PERBAIKAN: Link "Edit" (data-modal-link) --}}
                                        <a href="{{ route('admin.menu.edit', ['menu' => $childMenuItem->id]) }}"
                                           data-modal-link="true" {{-- <-- ATRIBUT PENTING UNTUK MODAL --}}
                                           class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-500 mr-2">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        {{-- PERBAIKAN: Tombol "Hapus" (data-delete-url) --}}
                                        <button type="button"
                                                data-delete-url="{{ route('admin.menu.destroy', $childMenuItem->id) }}"
                                                data-user-name="{{ $childMenuItem->name }}"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-500">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="7"
                                    class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Tidak ada item menu ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>