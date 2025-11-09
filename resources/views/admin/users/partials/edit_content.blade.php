
<form id="edit-user-form" class="ajax-form" method="POST" action="{{ route('manajemen-pengguna.users.update', $user->id) }}"
        data-success-redirect-tab="Daftar Pengguna" 
        data-success-redirect-url="{{ route('manajemen-pengguna.users.index') }}">          
        @csrf
    @method('PUT')

    <div class="p-6 space-y-4">
        
        {{-- ====================================================== --}}
        {{-- HEADER MODAL --}}
        {{-- ====================================================== --}}
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Edit Pengguna: <span class="text-indigo-600">{{ $user->name }}</span>
            </h2>
            <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                 <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <hr class="border-gray-200 dark:border-gray-700">

        {{-- ====================================================== --}}
        {{-- BODY MODAL (ISIAN FORM) --}}
        {{-- ====================================================== --}}
        <div class="space-y-4">
            {{-- Area Error Validasi --}}
            <div id="edit-user-errors" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative"></div>

            {{-- Grid 2 Kolom --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Kolom Kiri --}}
                <div class="space-y-4">
                    {{-- Nama --}}
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>

                    {{-- Email --}}
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required autocomplete="username"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>
                </div>
                
                {{-- Kolom Kanan --}}
                <div class="space-y-4">
                    {{-- Peran (Role) --}}
                    <div class="relative z-20">
                        <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Peran</label>
                        <select name="role_id" id="role_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                                    {{ $role->description }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Level Hierarki --}}
                    <div class="relative z-10">
                        <label for="hierarchy_level_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Level Hierarki</label>
                        <select name="hierarchy_level_code" id="hierarchy_level_code"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                            <option value="">-- Pilih Level Hierarki --</option>
                            @php
                                $loggedInUser = Auth::user();
                                $startingParentCode = $loggedInUser->hasRole('admin') ? null : $loggedInUser->hierarchy_level_code;
                            @endphp

                            @if(!$loggedInUser->hasRole('admin'))
                                @php
                                    $userHierarchyLevel = $hierarchyLevels->where('code', $loggedInUser->hierarchy_level_code)->first();
                                @endphp
                                @if($userHierarchyLevel)
                                    <option value="{{ $userHierarchyLevel->code }}" 
                                            {{ ($userHierarchyLevel->code == old('hierarchy_level_code', $user->hierarchy_level_code)) ? 'selected' : '' }}>
                                        {{ $userHierarchyLevel->name }} ({{ $userHierarchyLevel->code }})
                                    </option>
                                @endif
                            @endif

                            @include('admin.users.partials._hierarchy_options', [
                                'hierarchyLevels' => $hierarchyLevels,
                                'parentCode' => $startingParentCode,
                                'selectedCode' => old('hierarchy_level_code', $user->hierarchy_level_code),
                                'level' => $loggedInUser->hasRole('admin') ? 0 : 1
                            ])
                        </select>
                    </div>
                </div>
            </div> {{-- End Grid 2 Kolom --}}

            {{-- Password (Full Width) --}}
            <div class="pt-2">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Kosongkan password jika tidak ingin mengubahnya.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password Baru</label>
                        <input type="password" name="password" id="password" autocomplete="new-password"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>
                </div>
            </div>

            {{-- Checkboxes (Full Width) --}}
            <div class="pt-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Akses Web --}}
                <div>
                    <label for="is_approved" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status Akun Backoffice</label>
                    <div class="mt-2 flex items-center">
                        <input type="hidden" name="is_approved" value="0">
                        <input type="checkbox" name="is_approved" id="is_approved" value="1"
                               class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               {{ old('is_approved', $user->is_approved) ? 'checked' : '' }}>
                        <label for="is_approved" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                            Aktif - (Pengguna dapat login Web)
                        </label>
                    </div>
                </div>
                {{-- Akses Mobile --}}
                <div>
                    <label for="mobile_app" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status Akun Mobile</label>
                    <div class="mt-2 flex items-center">
                        <input type="hidden" name="mobile_app" value="0">
                        <input type="checkbox" name="mobile_app" id="mobile_app" value="1"  
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                {{ old('mobile_app', $user->mobile_app) ? 'checked' : '' }}>
                        <label for="mobile_app" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Aktif - (Izinkan Akses Aplikasi Mobile)
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- ====================================================== --}}
        {{-- FOOTER MODAL (TOMBOL AKSI) --}}
        {{-- ====================================================== --}}
        <hr class="border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-end space-x-2">
            <button type="button" data-modal-close class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                Batal
            </button>
            <button type="submit" 
                    data-modal-submit-button
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Update Pengguna
            </button>
        </div>
        
    </div>
</form>