<x-app-layout>
    <div class="pt-3 pb-0">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4">
            {{ __('Tambah Pengguna Baru') }}
        </h2>

        {{-- Notifikasi Error Validasi --}}
        @if ($errors->any())
            <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg shadow-md mb-4">
                <strong class="font-bold">Ada kesalahan!</strong>
                <ul class="mt-3 list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

       <hr class="border-gray-200 dark:border-gray-700 mb-2    ">

        {{-- Kartu Formulir --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <form method="POST" action="{{ route('manajemen-pengguna.users.store') }}">
                    @csrf

                    {{-- Nama --}}
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>

                    {{-- Email --}}
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>

                    {{-- Password --}}
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                        <input type="password" name="password" id="password" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>

                    {{-- Konfirmasi Password --}}
                    <div class="mb-4">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>

                    {{-- Peran (Role) --}}
                    <div class="mb-4">
                        <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Peran</label>
                        @php
                            $isRoleDisabled = Auth::user()->hasRole('app_user');
                            $selectedRoleId = old('role_id', $isRoleDisabled ? \App\Models\Role::where('name', 'app_user')->first()->id : '');
                        @endphp
                        <select name="role_id" id="role_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                                {{ $isRoleDisabled ? 'disabled' : '' }}>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" {{ $selectedRoleId == $role->id ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        @if ($isRoleDisabled)
                            <input type="hidden" name="role_id" value="{{ $selectedRoleId }}">
                        @endif
                        @error('role_id')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Level Hierarki --}}
                    <div class="mb-4">
                        <label for="hierarchy_level_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Level Hierarki</label>
                        @php
                            $isHierarchyDisabled = Auth::user()->hasRole('app_user');
                            $currentUserHierarchyCode = Auth::user()->hierarchy_level_code;
                            $selectedHierarchyCode = old('hierarchy_level_code', $isHierarchyDisabled ? $currentUserHierarchyCode : '');
                        @endphp
                        <select name="hierarchy_level_code" id="hierarchy_level_code"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                                {{ $isHierarchyDisabled ? 'disabled' : '' }}>
                            <option value="">-- Pilih Level Hierarki --</option>
                            @include('admin.users._hierarchy_options', [
                                'hierarchyLevels' => $hierarchyLevels,
                                'selectedCode' => $selectedHierarchyCode
                            ])
                        </select>
                        @if ($isHierarchyDisabled)
                            <input type="hidden" name="hierarchy_level_code" value="{{ $selectedHierarchyCode }}">
                        @endif
                        @error('hierarchy_level_code')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Status Disetujui --}}
                    <div class="mb-4 flex items-center">
                        <input type="checkbox" name="is_approved" id="is_approved" value="1" {{ old('is_approved', 1) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                        <label for="is_approved" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Disetujui (Dapat Login)</label>
                    </div>

                    {{-- Tombol Submit dan Kembali --}}
                    <div class="flex items-center justify-end mt-6">
                        <a href="{{ route('manajemen-pengguna.users.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 mr-4">Batal</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            {{ __('Simpan Pengguna') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
