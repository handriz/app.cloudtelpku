<div class="pt-3 pb-0">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4">
        Edit Pengguna: <span class="text-indigo-600">{{ $user->name }}</span>
    </h2>

    <hr class="border-gray-200 dark:border-gray-700 my-2">

    <div class="bg-white dark:bg-gray-800 w-full">
        <div class="p-6 text-gray-900 dark:text-gray-100">

            {{-- 1. Area untuk menampilkan error validasi dari JavaScript --}}
            <div id="edit-user-errors" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"></div>

            {{-- 2. Tambahkan ID pada form dan sesuaikan method --}}
            <form id="edit-user-form" method="POST" action="{{ route('manajemen-pengguna.users.update', $user->id) }}">
                @csrf
                @method('PUT') {{-- Mengikuti konvensi Anda --}}

                {{-- Nama --}}
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                </div>

                {{-- Email --}}
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                </div>
                
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Kosongkan password jika tidak ingin mengubahnya.</p>
                {{-- Password & Konfirmasi --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password Baru</label>
                        <input type="password" name="password" id="password"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>
                </div>

                {{-- Peran (Role) --}}
                <div class="mb-4">
                    <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Peran</label>
                    <select name="role_id" id="role_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                                {{ $role->description }} {{-- Menggunakan description agar lebih user-friendly --}}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Level Hierarki (Menggunakan @include Anda) --}}
                <div class="mb-4">
                    <label for="hierarchy_level_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Level Hierarki</label>
                    <select name="hierarchy_level_code" id="hierarchy_level_code"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        <option value="">-- Pilih Level Hierarki --</option>
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

                <div class="mb-4">
                    <label for="is_approved" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status Akun Web</label>
                        <div class="mt-2 flex items-center">
                            {{-- Trik untuk mengirim nilai 0 jika checkbox tidak dicentang --}}
                            <input type="hidden" name="is_approved" value="0">
                            <input type="checkbox" name="is_approved" id="is_approved" value="1"
                                   class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                   {{ old('is_approved', $user->is_approved) ? 'checked' : '' }}>
                            <label for="is_approved" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                                Aktif - (Pengguna dapat login Web)
                        </div>
                    </label>        
                </div>
                <div class="mb-4">
                    <label for="is_approved" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status Akun Mobile</label>
                        <div class="mt-2 flex items-center">
                            <input type="hidden" name="mobile_app" value="0">
                            <input type="checkbox" name="mobile_app" id="mobile_app" value="1"  
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    {{ old('mobile_app', $user->mobile_app) ? 'checked' : '' }}>
                            <label for="mobile_app" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Aktif - (Izinkan Akses Aplikasi Mobile)</label>
                        </div>
                </div>
                {{-- Tombol Aksi --}}
                <div class="flex items-center justify-end mt-6">
                    {{-- 3. Ganti link "Batal" menjadi tombol yang memanggil closeModal() --}}
                    <button type="button" data-modal-close class="text-gray-600 dark:text-gray-300 mr-4">Batal</button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Update Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>