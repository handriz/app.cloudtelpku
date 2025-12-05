{{-- 
    KOMPONEN PENDUKUNG DRAG & DROP / INTERAKSI 
    (Context Menu, Modal Pindah, Hidden Inputs)
--}}

{{-- 1. CONTEXT MENU (KLIK KANAN) --}}
<div id="custom-context-menu" class="fixed z-[60] hidden bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 w-56 overflow-hidden transform transition-opacity duration-100 font-sans">
    {{-- Header Info (IDPEL yang diklik) --}}
    <div class="px-4 py-2 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 text-xs font-bold text-gray-500 dark:text-gray-300" id="ctx-header">
        Pelanggan
    </div>
    <ul class="text-sm text-gray-700 dark:text-gray-200">
        {{-- Opsi Pindah --}}
        <li>
            <button type="button" id="ctx-btn-move" class="w-full text-left px-4 py-2.5 hover:bg-indigo-50 dark:hover:bg-indigo-900/50 hover:text-indigo-600 flex items-center transition cursor-pointer">
                <i class="fas fa-exchange-alt mr-3 text-indigo-500"></i> Pindah ke Rute Lain...
            </button>
        </li>
        {{-- Opsi Hapus --}}
        <li class="border-t border-gray-100 dark:border-gray-700">
            <button type="button" id="ctx-btn-remove" class="w-full text-left px-4 py-2.5 hover:bg-red-50 dark:hover:bg-red-900/30 hover:text-red-600 flex items-center transition cursor-pointer">
                <i class="fas fa-trash-alt mr-3 text-red-500"></i> Keluarkan dari Grup
            </button>
        </li>
    </ul>
</div>

{{-- 2. MODAL PINDAH RUTE (MOVE CUSTOMER) --}}
<div id="modal-move-route" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden items-center justify-center z-[70] p-4 backdrop-blur-sm">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md transform transition-all scale-100">
        <div class="p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Pindahkan Pelanggan</h3>
            <p class="text-sm text-gray-500 mb-4">
                IDPEL: <span id="move-modal-idpel" class="font-mono font-bold text-indigo-600 bg-indigo-50 px-1 rounded"></span>
            </p>

            {{-- Form Pindah --}}
            <div class="space-y-4">
                {{-- Info Prefix Unit --}}
                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600 text-xs text-gray-500 flex justify-between items-center">
                    <span>
                        <i class="fas fa-building mr-1"></i> Unit: 
                        <strong class="text-gray-700 dark:text-gray-300">{{ $hierarchy->parent->kddk_code ?? '?' }}{{ $hierarchy->kddk_code ?? '?' }}</strong>
                    </span>
                    <span>
                        <i class="fas fa-code-branch mr-1"></i> Sub: 
                        <strong class="text-gray-700 dark:text-gray-300">A (Default)</strong>
                    </span>
                </div>

                {{-- Pilih Area Tujuan --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1 uppercase">Pilih Area Tujuan</label>
                    <select id="move-area" class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm" onchange="updateMoveRouteOptions()">
                        <option value="">-- Pilih Area --</option>
                        {{-- Menggunakan variable $kddkConfig dari Controller --}}
                        @if(isset($kddkConfig) && isset($kddkConfig['areas']))
                            @foreach($kddkConfig['areas'] as $area)
                                <option value="{{ $area['code'] }}" data-routes="{{ json_encode($area['routes'] ?? []) }}">
                                    {{ $area['code'] }} - {{ $area['label'] }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                {{-- Pilih Rute Tujuan --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1 uppercase">Pilih Rute Tujuan</label>
                    <select id="move-route-select" class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm" disabled>
                        <option value="">-- Pilih Area Dulu --</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Footer Modal --}}
        <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 rounded-b-xl flex justify-end space-x-2 border-t border-gray-100 dark:border-gray-600">
            <button type="button" onclick="document.getElementById('modal-move-route').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 text-sm font-medium transition">
                Batal
            </button>
            <button type="button" onclick="executeMoveRoute()" class="px-4 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 text-sm shadow-md transition transform active:scale-95">
                Pindahkan
            </button>
        </div>
    </div>
</div>

{{-- 3. HIDDEN INPUTS UNTUK JS --}}
{{-- Menyimpan IDPEL yang sedang diklik kanan --}}
<input type="hidden" id="ctx-selected-idpel">
{{-- Menyimpan Prefix Unit (Digit 1-2) untuk pembentukan kode baru --}}
<input type="hidden" id="ctx-unit-prefix" value="{{ $hierarchy->parent->kddk_code ?? '' }}{{ $hierarchy->kddk_code ?? '' }}">