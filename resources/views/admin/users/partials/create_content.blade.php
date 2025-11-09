<div id="kddk-notification-container" class="space-y-6">
<div class="space-y-6">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4">
        {{ __('Tambah Pengguna Baru') }}
    </h2>

    {{-- Wadah untuk Notifikasi Error AJAX --}}
    <div id="ajax-errors" class="hidden bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg shadow-md mb-4">
        <strong class="font-bold">Ada kesalahan!</strong>
        <ul id="error-list" class="mt-3 list-disc list-inside"></ul>
    </div>
    
    {{-- Notifikasi Sukses AJAX --}}
    <div id="ajax-success" class="hidden bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg shadow-md mb-4">
        <strong class="font-bold">Berhasil!</strong>
        <span class="block sm:inline" id="success-message">Pengguna berhasil ditambahkan.</span>
    </div>

   <hr class="border-gray-200 dark:border-gray-700 mb-2    ">

    {{-- Kartu Formulir --}}
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900 dark:text-gray-100">
            <form id="create-user-form" class="ajax-form" method="POST" action="{{ route('manajemen-pengguna.users.store') }}"
                  data-success-redirect-tab="Daftar Pengguna" 
                  data-success-redirect-url="{{ route('manajemen-pengguna.users.index') }}">
                @csrf

                {{-- Nama --}}
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Lengkap</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                </div>

                {{-- Email --}}
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Alamat Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required  autocomplete="username"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                </div>

                {{-- Peran (Role) --}}
                <div class="mb-4">
                    <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Peran Pengguna</label>
                    <select name="role_id" id="role_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                {{ $role->description }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Hirarki Level (Hierarchy Level) --}}
                <div class="mb-4">
                    <label for="hierarchy_level_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Level Hierarki</label>
                    <select name="hierarchy_level_code" id="hierarchy_level_code" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        <option value="">-- Pilih Level Hierarki --</option>
                        
                        {{-- KUNCI PERBAIKAN: Tentukan titik awal rekursi --}}
                        @php
                            $loggedInUser = Auth::user();
                            $startingParentCode = $loggedInUser->hasRole('admin') ? null : $loggedInUser->hierarchy_level_code;
                        @endphp

                        {{-- Tampilkan level hierarki pengguna yang sedang login sebagai opsi pertama --}}
                        @if(!$loggedInUser->hasRole('admin'))
                            @php
                                $userHierarchyLevel = $hierarchyLevels->where('code', $loggedInUser->hierarchy_level_code)->first();
                            @endphp
                            @if($userHierarchyLevel)
                                <option value="{{ $userHierarchyLevel->code }}" 
                                        {{ ($userHierarchyLevel->code == old('hierarchy_level_code')) ? 'selected' : '' }}>
                                    {{ $userHierarchyLevel->name }} ({{ $userHierarchyLevel->code }})
                                </option>
                            @endif
                        @endif

                        {{-- Kemudian panggil komponen untuk merender anak-anaknya dengan level 1 --}}
                        @include('admin.users.partials._hierarchy_options', [
                            'hierarchyLevels' => $hierarchyLevels,
                            'parentCode' => $startingParentCode,
                            'selectedCode' => old('hierarchy_level_code'),
                            'level' => $loggedInUser->hasRole('admin') ? 0 : 1
                        ])
                    </select>
                </div>

                {{-- Kata Sandi (Password) --}}
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kata Sandi</label>
                    <input type="password" name="password" id="password" required autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                </div>

                {{-- Konfirmasi Kata Sandi --}}
                <div class="mb-4">
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Konfirmasi Kata Sandi</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                </div>

                {{-- Status Persetujuan --}}
                <div class="mb-4">
                    <input type="checkbox" name="is_approved" id="is_approved" value="1" {{ old('is_approved', 1) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                    <label for="is_approved" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Disetujui (Dapat Login)</label>
                </div>

                {{-- Tombol Submit dan Kembali --}}
                <div class="flex items-center justify-end mt-6">
                    <a href="{{ route('manajemen-pengguna.users.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 mr-4">Batal</a>
                    <button type="submit" 
                            id="save-user-button" 
                            class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                        Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>