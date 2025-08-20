<x-app-layout>

    <div> {{-- Hanya padding vertikal minimal, karena padding horizontal akan datang dari app.blade.php --}}
        
        {{-- JUDUL HALAMAN --}}
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4"> 
            {{ __('Manajemen Izin') }}
        </h2>

        {{-- KONTEN NOTIFIKASI --}}
        @if (session('success'))
            <div id="success-alert" class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg shadow-md relative w-full mb-4"> 
                <strong class="font-bold">Berhasil!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
                <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer" onclick="document.getElementById('success-alert').style.display='none'">
                    <svg class="fill-current h-5 w-5 text-green-500 dark:text-green-300" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l3.029-2.651-3.029-2.651a1.2 1.2 0 0 1 1.697-1.697L10 8.183l2.651-3.029a1.2 1.2 0 1 1 1.697 1.697L11.819 10l3.029 2.651a1.2 1.2 0 0 1 0 1.698z"/></svg>
                </span>
            </div>
        @endif
        
        @if (session('error'))
            <div id="error-alert" class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg shadow-md relative w-full mb-4"> 
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">{{ session('error') }}</span>
                <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer" onclick="document.getElementById('error-alert').style.display='none'">
                    <svg class="fill-current h-5 w-5 text-red-500 dark:text-red-300" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l3.029-2.651-3.029-2.651a1.2 1.2 0 0 1 1.697-1.697L10 8.183l2.651-3.029a1.2 1.2 0 1 1 1.697 1.697L11.819 10l3.029 2.651a1.2 1.2 0 0 1 0 1.698z"/></svg>
                </span>
            </div>
        @endif

        {{-- GARIS PEMISAH --}}
        <hr class="border-gray-300 dark:border-gray-700 my-4">

        {{-- KARTU KONTEN UTAMA HALAMAN (TABEL) --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg w-full">
            <div class="p-6 text-gray-900 dark:text-gray-100 w-full">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Manajemen Izin Berdasarkan Peran</h3>
                    <a href="{{ route('admin.permissions.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-2 transition ease-in-out duration-150">
                        <i class="fas fa-plus mr-2"></i> Tambah Izin Baru
                    </a>
                </div>

                {{-- FORMULIR UTAMA UNTUK MEMPERBARUI IZIN PERAN --}}
                <form action="{{ route('admin.permissions.updateRolePermissions') }}" method="POST">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                        No.
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                        Izin
                                    </th>
                                    @foreach($roles as $role)
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                            {{ ucfirst($role->name) }}
                                        </th>
                                    @endforeach
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider" style="width: 120px;">
                                        Aksi Izin
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($permissions as $index => $permission)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $index + 1 }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                <strong>{{ $permission->name }}</strong>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $permission->description ?? 'Tidak ada deskripsi' }}
                                            </div>
                                        </td>
                                        @foreach($roles as $role)
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <div class="form-check">
                                                    <input type="checkbox"
                                                           class="form-checkbox h-5 w-5 text-indigo-600 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                                           name="permissions[{{ $role->name }}][{{ $permission->id }}]"
                                                           value="1"
                                                           {{ in_array($permission->id, $rolePermissions[$role->name] ?? []) ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                        @endforeach
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <a href="{{ route('admin.permissions.edit', $permission->id) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-500 mr-2">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button type="button"
                                                    onclick="confirmDeletePermission({{ $permission->id }})"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-500">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($roles) + 2 }}" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada izin ditemukan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-2 transition ease-in-out duration-150 mt-4">
                        Perbarui Izin Peran
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Formulir Hapus Tersembunyi (Disubmit oleh JavaScript) --}}
    <form id="delete-permission-form" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    <script>
        function confirmDeletePermission(permissionId) {
            if (confirm('Apakah Anda yakin ingin menghapus izin ini? Ini akan menghapus semua penetapan izin ke peran yang menggunakannya.')) {
                const form = document.getElementById('delete-permission-form');
                form.action = '/admin/permissions/' + permissionId; // Sesuaikan dengan URL Anda
                form.submit();
            }
        }
    </script>
</x-app-layout>