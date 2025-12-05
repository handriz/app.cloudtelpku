<div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">
            Manajemen Struktur Organisasi (Unit)
        </h3>
        <a href="{{ route('admin.hierarchies.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition shadow" data-modal-link>
            <i class="fas fa-plus mr-2"></i> Tambah Unit Baru
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            {{ session('success') }}
        </div>
    @endif

    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tipe</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Kode Unit</th>
                    <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Kode KDDK</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama Unit (Hirarki)</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Parent Code</th>
                    <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {{-- PANGGIL PARTIAL REKURSIF DISINI --}}
                @include('admin.hierarchies.partials._hierarchy_row', [
                    'items' => $hierarchies, // Kirim data Root (Parent Utama)
                    'level' => 0             // Mulai dari level 0
                ])

                @if($hierarchies->isEmpty())
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">Belum ada data unit.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
    
    <div class="mt-4">
        {{ $hierarchies->links() }}
    </div>
</div>