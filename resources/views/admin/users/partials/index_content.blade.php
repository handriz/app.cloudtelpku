    <div class="pt-2 pb-0 ">
    <div class="flex justify-between items-center mb-4">
        
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight ml-4 sm:ml-6 lg:ml-8">
            {{ __('Manajemen Pengguna') }}
        </h2>

        <form id="user-search-form" action="{{ route('manajemen-pengguna.users.index') }}" method="GET" class="w-1/2 max-w-sm">
            <div class="flex items-center">
                <div class="relative w-full">
                    <input type="text" name="search"
                        class="form-input block w-full pl-3 pr-10 sm:text-sm sm:leading-5 rounded-l-md dark:bg-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Cari nama atau email..."
                        value="{{ $search ?? '' }}"
                        autocomplete="off">
                    
                    <button type="button" id="clear-search-button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 hidden">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold text-xs uppercase tracking-widest rounded-r-md hover:bg-indigo-500">
                    Cari
                </button>
            </div>
        </form>

    </div>
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

        <hr class="border-gray-400 dark:border-gray-700 my-2">

        {{-- Kartu Konten Utama --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg w-full">
            <div class="p-2 text-gray-900 dark:text-gray-100 w-full">
                <div class="flex justify-between items-center mb-4">
                    <!-- <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Daftar Pengguna Sistem</h3> -->
                    <!-- @can('create-user') {{-- Hanya tampil jika user punya izin 'create-user' --}}
                        <a href="{{ route('manajemen-pengguna.users.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-2 transition ease-in-out duration-150">
                            <i class="fas fa-plus mr-2"></i> Tambah Pengguna
                        </a>
                    @endcan -->
                </div>               

                <div class="overflow-x-auto"> {{-- Ini yang memungkinkan tabel discroll jika terlalu lebar --}}
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 table-auto"> {{-- Tambahkan table-auto --}}
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap"> {{-- Kurangi px, tambah whitespace-nowrap --}}
                                    No.
                                </th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Nama
                                </th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Email
                                </th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Peran
                                </th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Level Akses
                                </th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Akses Web
                                </th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Akses Mobile
                                </th>                                
                                 @if (Auth::user()->hasRole('admin'))
                                <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider whitespace-nowrap">
                                    Aksi
                                </th>
                                  @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($users as $index => $user)
                                <tr>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100"> {{-- Kurangi px --}}
                                        {{ $users->firstItem() + $index }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $user->name }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $user->email }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $user->role->description ?? 'Tidak Ada Peran' }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                         @if($user->hierarchyLevel)
                                            {{ $user->hierarchyLevel->code }} - {{ $user->hierarchyLevel->name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm">
                                        @if($user->is_approved)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100">Disetujui</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100">Menunggu</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm">
                                        @if($user->mobile_app)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-100">Diizinkan</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100">Diblokir</span>
                                        @endif
                                    </td>                                    
                                    <td class="px-3 py-2 whitespace-nowrap text-center text-sm font-medium">
                                        @can('edit-user')
                                        <a href="{{ route('manajemen-pengguna.users.edit', $user->id) }}"
                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-500 mr-2"
                                        data-modal-link="true"
                                        data-modal-title="Edit Pengguna: {{ $user->name }}">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        @endcan
                                        @can('delete-user')
                                            <button type="button"
                                                    data-delete-url="{{ route('manajemen-pengguna.users.destroy', $user->id) }}"
                                                    data-user-name="{{ $user->name }}"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-500">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada pengguna ditemukan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Bagian Paginasi --}}
                <div class="mt-4" data-pagination-container>
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>