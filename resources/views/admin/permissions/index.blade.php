<x-app-layout>
    <div class="pt-0 pb-0">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4">
            {{ __('Manajemen Izin') }}
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

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Atur Izin Peran</h3>

                {{-- Formulir untuk memilih peran dan menampilkan/memperbarui izin --}}
                <form action="{{ route('admin.permissions.updateRolePermissions') }}" method="POST" id="permissions-form">
                    @csrf
                    <div class="mb-4">
                        <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Peran</label>
                        <select name="role_id" id="role_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                            <option value="">Pilih Peran...</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id', $selectedRole->id ?? '') == $role->id ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Bagian pengelompokan izin --}}
                    @if ($groupedPermissions->isNotEmpty())
                        @foreach ($groupedPermissions as $groupName => $groupItems)
                            <div class="mb-6 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                                {{-- Header Grup dengan Toggle dan Pilih Semua --}}
                                <div class="flex items-center justify-between p-4 bg-gray-100 dark:bg-gray-750 cursor-pointer permission-group-toggle" data-target-group="{{ Str::slug($groupName) }}">
                                    <h4 class="font-semibold text-gray-800 dark:text-gray-100 text-md">
                                        <i class="fas fa-caret-right group-icon-closed mr-2"></i>
                                        <i class="fas fa-caret-down group-icon-open hidden mr-2"></i>
                                        {{ $groupName }}
                                    </h4>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" class="form-checkbox h-5 w-5 text-indigo-600 rounded permission-group-select-all" data-group-name="{{ Str::slug($groupName) }}">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Pilih Semua</span>
                                    </label>
                                </div>

                                {{-- Konten Grup (disembunyikan secara default) --}}
                                <div id="group-{{ Str::slug($groupName) }}" class="permission-group-content hidden">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-700">
                                                <tr>
                                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                                        No.
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                                        Izin
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                                        Deskripsi
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                                        Pilih
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                @forelse($groupItems as $index => $permission)
                                                    <tr>
                                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                            {{ $permissions->firstItem() + $index }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                            {{ $permission->name }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                            {{ $permission->description }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                                            <input type="hidden" name="permissions[{{ $selectedRole->name ?? 'undefined_role' }}][{{ $permission->id }}]" value="0">
                                                            <input type="checkbox" name="permissions[{{ $selectedRole->name ?? 'undefined_role' }}][{{ $permission->id }}]" value="1"
                                                                class="form-checkbox h-5 w-5 text-indigo-600 rounded permission-checkbox group-{{ Str::slug($groupName) }}"
                                                                {{ (isset($selectedRole) && $selectedRole->permissions->contains($permission->id)) ? 'checked' : '' }}>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada izin ditemukan dalam grup ini.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="overflow-x-auto mb-4">
                             <p class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada izin ditemukan.</p>
                        </div>
                    @endif


                    {{-- Tautan Paginasi --}}
                    <div class="mt-4">
                        {{ $permissions->appends(request()->query())->links() }}
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-2 transition ease-in-out duration-150">
                            Perbarui Izin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role_id');
            const permissionsForm = document.getElementById('permissions-form');

            // --- Logika untuk Mempertahankan Pilihan Peran Saat Navigasi/Ganti Peran ---
            roleSelect.addEventListener('change', function() {
                const selectedRoleId = this.value;
                const url = new URL(window.location.href);
                if (selectedRoleId) {
                    url.searchParams.set('role_id', selectedRoleId);
                } else {
                    url.searchParams.delete('role_id');
                }
                url.searchParams.delete('page'); // Hapus parameter halaman saat ganti peran
                window.location.href = url.toString();
            });

            // --- Logika Expand/Collapse Grup Izin ---
            document.querySelectorAll('.permission-group-toggle').forEach(toggleButton => {
                toggleButton.addEventListener('click', function() {
                    const targetGroupId = this.dataset.targetGroup;
                    const groupContent = document.getElementById(`group-${targetGroupId}`);
                    
                    if (groupContent) {
                        groupContent.classList.toggle('hidden');
                        this.querySelector('.group-icon-closed').classList.toggle('hidden');
                        this.querySelector('.group-icon-open').classList.toggle('hidden');
                    }
                });
            });

            // --- Logika Pilih Semua Per Grup ---
            document.querySelectorAll('.permission-group-select-all').forEach(selectAllCheckbox => {
                selectAllCheckbox.addEventListener('change', function() {
                    const groupName = this.dataset.groupName;
                    const groupCheckboxes = document.querySelectorAll(`.permission-checkbox.group-${groupName}`);
                    
                    groupCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            });

            // --- Logika untuk memastikan checkbox "Pilih Semua" sesuai dengan status individual checkbox ---
            // Saat halaman dimuat atau peran dipilih, periksa status "Pilih Semua"
            function updateSelectAllCheckboxes() {
                document.querySelectorAll('.permission-group-select-all').forEach(selectAllCheckbox => {
                    const groupName = selectAllCheckbox.dataset.groupName;
                    const groupCheckboxes = document.querySelectorAll(`.permission-checkbox.group-${groupName}`);
                    
                    // Jika tidak ada checkbox dalam grup, tandai "Pilih Semua" sebagai tidak aktif
                    if (groupCheckboxes.length === 0) {
                        selectAllCheckbox.checked = false;
                        selectAllCheckbox.disabled = true;
                        return;
                    }

                    const allChecked = Array.from(groupCheckboxes).every(checkbox => checkbox.checked);
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.disabled = false; // Pastikan tidak dinonaktifkan jika ada checkbox
                });
            }

            // Panggil saat DOM siap
            updateSelectAllCheckboxes();

            // Panggil setiap kali checkbox individual diubah
            document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectAllCheckboxes);
            });
            
            // Ini memastikan bahwa form update selalu mengirimkan role_id yang saat ini aktif
            permissionsForm.addEventListener('submit', function(event) {
                const selectedRoleId = roleSelect.value;
                if (selectedRoleId) {
                    const hiddenRoleIdInput = document.createElement('input');
                    hiddenRoleIdInput.type = 'hidden';
                    hiddenRoleIdInput.name = 'current_role_id'; // Nama baru untuk role_id yang dikirim
                    hiddenRoleIdInput.value = selectedRoleId;
                    permissionsForm.appendChild(hiddenRoleIdInput);
                }
            });
        });
    </script>
</x-app-layout>