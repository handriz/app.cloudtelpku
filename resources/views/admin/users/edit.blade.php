<x-app-layout>
    <div class="pt-0 pb-0">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4">
            {{ __('Edit Pengguna') }}
        </h2>

        {{-- Notifikasi Sukses --}}
        @if (session('success'))
            <div id="success-alert" class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg shadow-md relative w-full mb-4">
                <strong class="font-bold">Berhasil!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
                <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer" onclick="document.getElementById('success-alert').style.display='none'">
                    <svg class="fill-current h-5 w-5 text-green-500 dark:text-green-300" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.03a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                </span>
            </div>
        @endif

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

        <hr class="border-gray-200 dark:border-gray-700 my-6">

        {{-- Kartu Formulir --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <form method="POST" action="{{ route('manajemen-pengguna.users.update', $user->id) }}">
                    @csrf
                    @method('PUT')

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

                    {{-- Password (Kosongkan untuk tidak mengubah) --}}
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password Baru (Opsional)</label>
                        <input type="password" name="password" id="password"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kosongkan jika tidak ingin mengubah password.</p>
                    </div>

                    {{-- Konfirmasi Password Baru --}}
                    <div class="mb-4">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>

                    {{-- Peran (Role) --}}
                    <div class="mb-4">
                        <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Peran</label>
                        @php
                            $isRoleDisabled = Auth::user()->hasRole('app_user') && $user->id !== Auth::id(); // App_user tidak bisa edit role orang lain
                            $selectedRoleId = old('role_id', $user->role_id);
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
                            $isHierarchyDisabled = Auth::user()->hasRole('app_user') && $user->id !== Auth::id(); // App_user tidak bisa edit hierarki orang lain
                            $selectedHierarchyCode = old('hierarchy_level_code', $user->hierarchy_level_code);
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
                        <input type="checkbox" name="is_approved" id="is_approved" value="1" {{ old('is_approved', $user->is_approved) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                        <label for="is_approved" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Disetujui (Dapat Login)</label>
                    </div>

                    {{-- Tombol Submit dan Kembali --}}
                    <div class="flex items-center justify-end mt-6">
                        <a href="{{ route('manajemen-pengguna.users.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 mr-4">Batal</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-800 transition ease-in-out duration-150">
                            {{ __('Update Pengguna') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
