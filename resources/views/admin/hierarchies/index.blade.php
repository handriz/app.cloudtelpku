<x-app-layout>
    <div class="pt-2 pb-0">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4">
            {{ __('Manajemen Hirarki Level') }}
        </h2>

        {{-- Notifikasi Sukses --}}
        @if (session('success'))
            <div id="success-alert" class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg shadow-md relative w-full mb-4">
                <strong class="font-bold">Berhasil!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
                <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer" onclick="document.getElementById('success-alert').style.display='none'">
                    <svg class="fill-current h-5 w-5 text-green-500 dark:text-green-300" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l3.029-2.651-3.029-2.651a1.2 1.2 0 0 1 1.697-1.697L10 8.183l2.651-3.029a1.2 1.2 0 1 1 1.697 1.697L11.819 10l3.029 2.651a1.2 1.2 0 0 1 0 1.698z"/></svg>
                </span>
            </div>
        @endif

        {{-- Notifikasi Error (jika ada) --}}
        @if (session('error'))
            <div id="error-alert" class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg shadow-md relative w-full mb-4">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">{{ session('error') }}</span>
                <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer" onclick="document.getElementById('error-alert').style.display='none'">
                    <svg class="fill-current h-5 w-5 text-red-500 dark:text-red-300" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l3.029-2.651-3.029-2.651a1.2 1.2 0 0 1 1.697-1.697L10 8.183l2.651-3.029a1.2 1.2 0 1 1 1.697 1.697L11.819 10l3.029 2.651a1.2 1.2 0 0 1 0 1.698z"/></svg>
                </span>
            </div>
        @endif

        <hr class="border-gray-200 dark:border-gray-700 my-6">

        {{-- Kartu Konten Utama --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg w-full">
            <div class="p-6 text-gray-900 dark:text-gray-100 w-full">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Daftar Level Hirarki</h3>
                    @can('create-hierarchy-level')
                        <a href="{{ route('admin.hierarchies.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-2 transition ease-in-out duration-150">
                            <i class="fas fa-plus mr-2"></i> Tambah Level Hirarki
                        </a>
                    @endcan
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 table-auto">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Level Hirarki
                                </th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Kode
                                </th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Induk
                                </th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Urutan
                                </th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Status
                                </th>
                                <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @if($hierarchyLevels->isNotEmpty())
                                {{-- Panggil partial rekursif untuk merender seluruh hirarki --}}
                                @include('admin.hierarchies._hierarchy_subtree', ['hierarchyItems' => $hierarchyLevels, 'level' => 0])
                            @else
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada level hirarki ditemukan.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Formulir Hapus Tersembunyi (Disubmit oleh JavaScript) --}}
    <form id="delete-hierarchy-form" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    <script>
        function confirmDeleteHierarchyLevel(hierarchyLevelId) {
            if (confirm('Apakah Anda yakin ingin menghapus level hirarki ini? Sub-level terkait mungkin akan menjadi tanpa induk.')) {
                const form = document.getElementById('delete-hierarchy-form');
                form.action = `/admin/hierarchies/${hierarchyLevelId}`; 
                form.submit();
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Mengambil semua tombol toggle
            const toggleButtons = document.querySelectorAll('.hierarchy-toggle');

            toggleButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const targetCode = this.dataset.target; // Kode hirarki dari item yang diklik
                    
                    // Temukan semua baris anak yang memiliki parent-code yang cocok
                    const childrenRows = document.querySelectorAll(`tr[data-parent-code="${targetCode}"]`);
                    
                    // Toggle visibility untuk setiap baris anak
                    childrenRows.forEach(childRow => {
                        // Jika anak disembunyikan, tampilkan. Jika ditampilkan, sembunyikan.
                        const isHidden = childRow.style.display === 'none';
                        childRow.style.display = isHidden ? '' : 'none'; // Tampilkan atau sembunyikan

                        // Jika menyembunyikan induk, pastikan semua cucu juga ikut tersembunyi
                        if (!isHidden) {
                            hideDescendants(childRow.dataset.hierarchyCode);
                        }
                    });

                    // Ubah ikon tombol
                    const iconClosed = this.querySelector('.hierarchy-icon-closed');
                    const iconOpen = this.querySelector('.hierarchy-icon-open');
                    iconClosed.classList.toggle('hidden');
                    iconOpen.classList.toggle('hidden');
                });
            });

            // Fungsi rekursif untuk menyembunyikan semua turunan
            function hideDescendants(parentCode) {
                const directChildren = document.querySelectorAll(`tr[data-parent-code="${parentCode}"]`);
                directChildren.forEach(childRow => {
                    childRow.style.display = 'none'; // Sembunyikan baris anak

                    // Dapatkan ikon toggle anak jika ada, dan pastikan mereka diatur ke "tertutup"
                    const childToggleButton = childRow.querySelector('.hierarchy-toggle');
                    if (childToggleButton) {
                        childToggleButton.querySelector('.hierarchy-icon-closed').classList.remove('hidden');
                        childToggleButton.querySelector('.hierarchy-icon-open').classList.add('hidden');
                    }
                    // Rekursif untuk cucu
                    hideDescendants(childRow.dataset.hierarchyCode);
                });
            }
        });
    </script>
</x-app-layout>