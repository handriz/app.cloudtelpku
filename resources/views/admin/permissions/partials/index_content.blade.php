<div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
    {{-- HEADER --}}
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">
            Manajemen Akses & Menu
        </h3>
        {{-- Tombol Tambah Permission --}}
        <a href="{{ route('admin.permissions.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-sm shadow transition">
            <i class="fas fa-plus mr-2"></i> Tambah Izin Baru
        </a>
    </div>

    {{-- NOTIFIKASI SUKSES --}}
    @if (session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm relative">
            <span class="block sm:inline">{{ session('success') }}</span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="this.parentElement.style.display='none';">
                <i class="fas fa-times"></i>
            </span>
        </div>
    @endif

    {{-- AREA SELEKSI (ROLE & USER) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        
        {{-- 1. PILIH ROLE (DEFAULT) --}}
        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
            <label for="role_id_main" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                <i class="fas fa-users-cog mr-1"></i> Kelola per Peran (Role):
            </label>
            <select name="role_id" id="role_id_main" 
                    onchange="const tabName = App.Utils.getActiveTabName(); const url = '{{ route('admin.permissions.index') }}?role_id=' + this.value; App.Tabs.loadTabContent(tabName, url);"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200">
                <option value="">-- Pilih Peran --</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ (isset($selectedRole) && $selectedRole->id == $role->id && !isset($selectedUser)) ? 'selected' : '' }}>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- 2. PILIH USER (OVERRIDE/KHUSUS) --}}
        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <label for="user_selector" class="block text-sm font-bold text-blue-800 dark:text-blue-300 mb-2">
                <i class="fas fa-user-edit mr-1"></i> Kelola per User (Spesifik):
            </label>
            <select id="user_selector" 
                    onchange="if(this.value) { const tabName = App.Utils.getActiveTabName(); const url = '{{ route('admin.permissions.index') }}?user_id=' + this.value; App.Tabs.loadTabContent(tabName, url); }"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-200">
                <option value="">-- Cari User --</option>
                {{-- Batasi user yg tampil agar tidak berat, atau gunakan ajax search di masa depan --}}
                @foreach(\App\Models\User::with('role')->orderBy('name')->limit(100)->get() as $u)
                    <option value="{{ $u->id }}" {{ (isset($selectedUser) && $selectedUser->id == $u->id) ? 'selected' : '' }}>
                        {{ $u->name }} ({{ $u->role->name }})
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                *Pilih ini jika ingin mengatur menu khusus untuk 1 orang (mengabaikan aturan Role).
            </p>
        </div>
    </div>

    @if($selectedRole)
        
        {{-- TABS NAVIGASI (LOKAL) --}}
        <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                <li class="mr-2">
                    <button class="tab-toggle-btn inline-block p-4 border-b-2 rounded-t-lg transition-colors duration-200 text-indigo-600 border-indigo-600 dark:text-indigo-500 dark:border-indigo-500" 
                            id="btn-tab-permissions" 
                            type="button" 
                            data-target="permissions">
                        <i class="fas fa-key mr-2"></i> Izin Fitur (Security)
                    </button>
                </li>
                <li class="mr-2">
                    <button class="tab-toggle-btn inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300 transition-colors duration-200" 
                            id="btn-tab-menus" 
                            type="button" 
                            data-target="menus">
                        <i class="fas fa-list mr-2"></i> Menu Sidebar (Visibility)
                    </button>
                </li>
            </ul>
        </div>

        {{-- ================================================= --}}
        {{-- KONTEN TAB 1: PERMISSIONS (KEAMANAN) --}}
        {{-- ================================================= --}}
        <div id="content-tab-permissions" class="local-tab-content">
            
            {{-- Form hanya update Role Permission (User tidak punya permission spesifik, hanya menu) --}}
            <form action="{{ route('admin.permissions.updateRolePermissions') }}" method="POST">
                @csrf
                <input type="hidden" name="role_id" value="{{ $selectedRole->id }}">
                
                <div class="mb-4 bg-yellow-50 dark:bg-yellow-900/30 p-3 rounded text-sm text-yellow-800 dark:text-yellow-200 border border-yellow-200 dark:border-yellow-800 flex items-start">
                    <i class="fas fa-shield-alt mt-1 mr-2"></i>
                    <div>
                        <strong>KEAMANAN SERVER:</strong> Centang fitur yang boleh dieksekusi.
                        <br>
                        <span class="text-xs">Pengaturan ini berlaku untuk SEMUA user dengan role <strong>{{ $selectedRole->name }}</strong>.</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-h-[60vh] overflow-y-auto p-1">
                    @foreach ($groupedPermissions as $groupName => $permissions)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden shadow-sm">
                            <div class="bg-gray-100 dark:bg-gray-700 px-4 py-2 flex justify-between items-center border-b border-gray-200 dark:border-gray-600">
                                <span class="font-bold text-gray-700 dark:text-gray-200">{{ $groupName }}</span>
                                <label class="text-xs flex items-center cursor-pointer hover:text-indigo-600 dark:hover:text-indigo-400">
                                    <input type="checkbox" class="group-select-all mr-1 form-checkbox rounded text-indigo-600" data-group="{{ Str::slug($groupName) }}"> Pilih Semua
                                </label>
                            </div>
                            <div class="p-3 bg-white dark:bg-gray-800 space-y-2">
                                @foreach($permissions as $perm)
                                    <div class="flex items-start">
                                        @php
                                            // Proteksi Hard Lock: Admin + Manage Permissions
                                            $isCritical = ($selectedRole->name === 'admin' && $perm->name === 'manage-permissions');
                                        @endphp

                                        <input type="checkbox" 
                                               name="{{ $isCritical ? '' : 'permissions[]' }}" 
                                               value="{{ $perm->id }}" 
                                               class="permission-item group-{{ Str::slug($groupName) }} mt-1 mr-2 form-checkbox h-4 w-4 text-indigo-600 rounded border-gray-300 {{ $isCritical ? 'opacity-50 cursor-not-allowed bg-gray-100' : '' }}"
                                               {{ in_array($perm->id, $rolePermissionIds) ? 'checked' : '' }}
                                               {{ $isCritical ? 'disabled' : '' }}>
                                        
                                        {{-- Input Hidden untuk Critical Permission --}}
                                        @if($isCritical)
                                            <input type="hidden" name="permissions[]" value="{{ $perm->id }}">
                                        @endif

                                        <div>
                                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300 {{ $isCritical ? 'cursor-not-allowed text-gray-500' : 'cursor-pointer' }}">
                                                {{ $perm->name }}
                                                @if($isCritical)
                                                    <i class="fas fa-lock text-xs text-red-500 ml-1" title="Dikunci permanen untuk Admin"></i>
                                                @endif
                                            </label>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $perm->description }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                
                <div class="mt-6 flex justify-end border-t border-gray-200 dark:border-gray-700 pt-4">
                    <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded shadow font-bold transition">
                        Simpan Izin Keamanan
                    </button>
                </div>
            </form>
        </div>

        {{-- ================================================= --}}
        {{-- KONTEN TAB 2: MENUS (VISIBILITY) --}}
        {{-- ================================================= --}}
        <div id="content-tab-menus" class="local-tab-content hidden">
            
            {{-- Cek Mode: Apakah sedang edit User Spesifik atau Role Default? --}}
            @php
                $isUserMode = isset($selectedUser);
                // Tentukan Action URL
                $formAction = $isUserMode ? route('admin.permissions.updateUserMenus') : route('admin.permissions.updateRoleMenus');
                // Tentukan data Checked (Gunakan $userMenuIds jika user mode, $roleMenuIds jika role mode)
                $checkedIds = $isUserMode ? ($userMenuIds ?? []) : ($roleMenuIds ?? []);
            @endphp

            @if($isUserMode)
                {{-- Info Box: Mode User --}}
                <div class="mb-4 bg-blue-50 border-l-4 border-blue-500 p-4 flex justify-between items-center">
                    <div>
                        <h4 class="font-bold text-blue-700">Mode Kustom: {{ $selectedUser->name }}</h4>
                        <p class="text-sm text-blue-600">
                            Status: 
                            @if($selectedUser->menuItems()->exists())
                                <span class="font-bold">MENGGUNAKAN MENU PRIBADI</span>
                            @else
                                <span class="font-bold opacity-75">MENGIKUTI ROLE (Default)</span>
                            @endif
                        </p>
                    </div>
                    @if($selectedUser->menuItems()->exists())
                        <form action="{{ route('admin.permissions.resetUserMenus') }}" method="POST" onsubmit="return confirm('Hapus menu pribadi dan kembali ikuti Role?');">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $selectedUser->id }}">
                            <button type="submit" class="text-xs bg-white text-red-600 px-3 py-1 rounded border border-red-200 hover:bg-red-50 shadow-sm">
                                <i class="fas fa-undo mr-1"></i> Reset ke Role
                            </button>
                        </form>
                    @endif
                </div>
            @else
                {{-- Info Box: Mode Role --}}
                <div class="mb-4 bg-purple-50 dark:bg-purple-900/30 p-3 rounded text-sm text-purple-800 dark:text-purple-200 border border-purple-200 dark:border-purple-800">
                    <i class="fas fa-eye mr-1"></i> <strong>TAMPILAN SIDEBAR:</strong> Mengatur menu default untuk Role <strong>{{ $selectedRole->name }}</strong>.
                </div>
            @endif

            <form action="{{ $formAction }}" method="POST">
                @csrf
                @if($isUserMode)
                    <input type="hidden" name="user_id" value="{{ $selectedUser->id }}">
                @else
                    <input type="hidden" name="role_id" value="{{ $selectedRole->id }}">
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[60vh] overflow-y-auto p-1">
                    @foreach ($groupedMenus as $parentMenu)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden shadow-sm">
                            {{-- Parent Menu --}}
                            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 flex items-center border-b border-gray-200 dark:border-gray-600">
                                @php
                                    // Hard Lock Menu: Jika Admin & Permission 'manage-permissions', kunci menu ini
                                    $isCriticalMenu = ($selectedRole->name === 'admin' && $parentMenu->permission_name === 'manage-permissions');
                                    
                                    // Cek juga anak-anaknya, jika ada anak kritis, bapaknya jadi kritis juga
                                    foreach($parentMenu->children as $c) {
                                        if ($selectedRole->name === 'admin' && $c->permission_name === 'manage-permissions') {
                                            $isCriticalMenu = true;
                                            break;
                                        }
                                    }
                                @endphp

                                <input type="checkbox" 
                                       name="{{ $isCriticalMenu ? '' : 'menu_ids[]' }}" 
                                       value="{{ $parentMenu->id }}"
                                       class="group-select-all mr-3 h-5 w-5 text-blue-600 rounded form-checkbox parent-menu-cb {{ $isCriticalMenu ? 'opacity-50 cursor-not-allowed bg-gray-100' : '' }}"
                                       data-group="menu-{{ $parentMenu->id }}"
                                       data-parent-id="{{ $parentMenu->id }}"
                                       {{ in_array($parentMenu->id, $checkedIds) ? 'checked' : '' }}
                                       {{ $isCriticalMenu ? 'disabled' : '' }}>
                                
                                @if($isCriticalMenu)
                                    <input type="hidden" name="menu_ids[]" value="{{ $parentMenu->id }}">
                                @endif

                                <span class="font-bold text-gray-700 dark:text-gray-200 flex items-center flex-1 select-none">
                                    <i class="{{ $parentMenu->icon }} mr-2 w-6 text-center text-gray-500 dark:text-gray-400"></i> 
                                    {{ $parentMenu->name }}
                                    @if($isCriticalMenu && !$isUserMode)
                                        <i class="fas fa-lock text-xs text-red-500 ml-2" title="Dikunci untuk Admin"></i>
                                    @endif
                                </span>
                            </div>
                            
                            {{-- Children Menu --}}
                            @if($parentMenu->children->count() > 0)
                                <div class="p-4 bg-white dark:bg-gray-800 ml-8 border-l-2 border-gray-100 dark:border-gray-600 space-y-3">
                                    @foreach($parentMenu->children as $child)
                                        <div class="flex items-center">
                                            @php
                                                $isCriticalChild = ($selectedRole->name === 'admin' && $child->permission_name === 'manage-permissions');
                                            @endphp

                                            <input type="checkbox" 
                                                   name="{{ $isCriticalChild ? '' : 'menu_ids[]' }}" 
                                                   value="{{ $child->id }}"
                                                   class="permission-item group-menu-{{ $parentMenu->id }} mr-3 h-4 w-4 text-blue-600 rounded form-checkbox child-menu-cb child-of-{{ $parentMenu->id }} {{ $isCriticalChild ? 'opacity-50 cursor-not-allowed bg-gray-100' : '' }}"
                                                   {{ in_array($child->id, $checkedIds) ? 'checked' : '' }}
                                                   {{ $isCriticalChild ? 'disabled' : '' }}>

                                            @if($isCriticalChild)
                                                <input type="hidden" name="menu_ids[]" value="{{ $child->id }}">
                                            @endif

                                            <span class="text-sm text-gray-700 dark:text-gray-300 select-none">
                                                <i class="{{ $child->icon }} mr-2 w-4 text-center text-gray-400"></i> {{ $child->name }}
                                                @if($isCriticalChild && !$isUserMode)
                                                    <i class="fas fa-lock text-xs text-red-500 ml-1"></i>
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex justify-end border-t border-gray-200 dark:border-gray-700 pt-4">
                    <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded shadow font-bold transition">
                        {{ $isUserMode ? 'Simpan Menu Pribadi User' : 'Simpan Menu Default Role' }}
                    </button>
                </div>
            </form>
        </div>

    @else
        {{-- EMPTY STATE --}}
        <div class="text-center py-12 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-800 mt-6">
            <i class="fas fa-user-shield text-4xl text-gray-400 mb-3"></i>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Pilih Target Konfigurasi</h3>
            <p class="text-gray-500 dark:text-gray-400">Silakan pilih <strong>Peran (Role)</strong> atau cari <strong>User Spesifik</strong> di atas.</p>
        </div>
    @endif
</div>
