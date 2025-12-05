{{-- PENANDA KONTEKS UNIT --}}
<input type="hidden" id="page-context-unit" value="{{ $unit }}">

<div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 relative">
    
    {{-- HEADER --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                Detail Pelanggan: <span class="text-indigo-600">{{ $unit }}</span>
            </h3>
            <p class="text-sm text-gray-500">Pilih pelanggan untuk membentuk Kelompok KDDK.</p>
        </div>
        
        <div class="flex space-x-2">
            {{-- TOMBOL BENTUK KDDK --}}
            <button onclick="window.confirmGrouping()" id="btn-group-kddk" class="hidden px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded shadow transition flex items-center">
                <i class="fas fa-layer-group mr-2"></i> Bentuk Group KDDK
            </button>

            <button onclick="App.Tabs.loadTabContent(App.Utils.getActiveTabName(), '{{ route('team.matrix_kddk.index') }}')" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i> Kembali
            </button>
        </div>
    </div>
    
    {{-- SEARCH --}}
    <form action="{{ route('team.matrix_kddk.details', ['unit' => $unit]) }}" method="GET" class="mb-4 flex" onsubmit="event.preventDefault(); App.Tabs.loadTabContent(App.Utils.getActiveTabName(), this.action + '?search=' + this.search.value);">
        <input type="text" name="search" placeholder="Cari IDPEL / Nama..." class="w-full md:w-1/3 rounded-l-md border-gray-300 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-indigo-500">
        <button type="submit" class="px-4 bg-indigo-600 text-white rounded-r-md hover:bg-indigo-700"><i class="fas fa-search"></i></button>
    </form>

    {{-- TABEL PELANGGAN --}}
    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-center w-10">
                        <input type="checkbox" id="check-all-rows" class="rounded text-indigo-600 focus:ring-indigo-500">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Idpel / No Meter</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jenis Layanan</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status DIL</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">KDDK Saat Ini</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($customers as $c)
                <tr class="hover:bg-indigo-50 dark:hover:bg-gray-700 transition">
                    <td class="px-4 py-3 text-center">
                        {{-- HAPUS ONCHANGE DARI SINI --}}
                        <input type="checkbox" name="idpel_select[]" value="{{ $c->idpel }}" data-jenis="{{ $c->jenislayanan ?? 'UNKNOWN' }}" class="row-checkbox rounded text-indigo-600 focus:ring-indigo-500">
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $c->idpel }}</div>
                        <div class="text-xs text-gray-500">{{ $c->nomor_meter_kwh ?? '-' }}</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 max-w-xs truncate" title="{{ $c->jenislayanan }}">{{ $c->jenislayanan }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">
                            @php
                                // Normalisasi status (Hapus spasi, Uppercase)
                                $status = strtoupper(trim($c->status_dil));
                                // Daftar kata kunci yang dianggap AKTIF
                                $activeKeywords = ['1', 'NYALA', 'AKTIF', 'HIDUP', 'ON'];
                            @endphp

                            @if(in_array($status, $activeKeywords)) 
                                <span class="px-2 py-1 text-xs font-bold rounded-full bg-green-100 text-green-800">
                                    Aktif ({{ $c->status_dil }})
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-bold rounded-full bg-red-100 text-red-800">
                                    Non-Aktif ({{ $c->status_dil ?? 'NULL' }})
                                </span>
                            @endif
                        </td>
                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-mono text-gray-700 dark:text-gray-300">
                        {{ $c->current_kddk ?? '-' }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->onEachSide(1)->links() }}</div>

    {{-- MODAL GENERATOR KDDK --}}
    <div id="modal-create-kddk" class="fixed inset-0 bg-gray-900 bg-opacity-80 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl transform transition-all">
            
            {{-- PENTING: Pastikan ID 'kddk-generator-form' dan 'data-sequence-url' ada --}}
            <form action="{{ route('team.matrix_kddk.store_group') }}" 
                  method="POST" 
                  class="ajax-form" 
                  id="kddk-generator-form" 
                  data-success-redirect-tab="Matrix KDDK"
                  data-sequence-url="{{ route('team.matrix_kddk.next_sequence', '') }}">
                  
                @csrf
                <input type="hidden" name="unitup" value="{{ $unit }}">
                
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-lg flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-barcode text-indigo-600 mr-2"></i> Generator Kode KDDK</h3>
                    <span class="text-xs bg-indigo-100 text-indigo-800 px-2 py-1 rounded-full"><span id="count-selected">0</span> Plg Dipilih</span>
                </div>
                
                <div class="p-6">
                    <div id="hidden-inputs-container"></div> 

                    {{-- INFO ESTIMASI --}}
                    <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-100 text-center">
                        <p class="text-xs font-bold text-gray-500 uppercase mb-1">Estimasi Kode</p>
                        <div class="flex justify-center items-center space-x-2 text-xl font-mono font-bold text-indigo-700">
                            <span id="preview-start">_______001__</span>
                            <span class="text-gray-400 text-sm"><i class="fas fa-arrow-right"></i></span>
                            <span id="preview-end">_______0XX__</span>
                        </div>
                        <p class="text-xs text-blue-600 mt-2">
                            Otomatis mengurutkan <span id="count-display" class="font-bold">0</span> pelanggan.
                        </p>
                        {{-- Input dummy untuk validasi visual saja --}}
                        <input type="hidden" id="final_kddk_preview"> 
                        <p id="kddk_error_msg" class="text-xs text-red-500 mt-1 h-4"></p>
                    </div>

                    {{-- FORM BUILDER --}}
                    <div class="grid grid-cols-12 gap-3 bg-gray-50 p-4 rounded-lg border border-gray-200">
                        
                        {{-- 1. UP3 --}}
                        <div class="col-span-2">
                            <label class="block text-[10px] font-bold text-center">UP3</label>
                            <input type="text" id="part_up3" value="{{ $autoCodes['up3'] ?? '_' }}" class="kddk-part w-full text-center font-mono font-bold uppercase bg-gray-200 border-gray-300 rounded-md cursor-not-allowed" readonly>
                        </div>
                        {{-- 2. ULP --}}
                        <div class="col-span-2">
                            <label class="block text-[10px] font-bold text-center">ULP</label>
                            <input type="text" id="part_ulp" value="{{ $autoCodes['ulp'] ?? '_' }}" class="kddk-part w-full text-center font-mono font-bold uppercase bg-gray-200 border-gray-300 rounded-md cursor-not-allowed" readonly>
                        </div>
                        {{-- 3. SUB --}}
                        <div class="col-span-2">
                            <label class="block text-[10px] font-bold text-center">SUB</label>
                            @if(isset($subUnits) && $subUnits->isNotEmpty())
                                {{-- HAPUS ONCHANGE --}}
                                <select id="part_sub" class="kddk-part w-full text-center font-mono font-bold uppercase border-gray-300 rounded-md text-xs py-2 bg-yellow-50 border-yellow-300">
                                    <option value="">-</option>
                                    <option value="A">A (Ktr ULP)</option>
                                    @foreach($subUnits as $sub)
                                        <option value="{{ $sub->kddk_code }}">{{ $sub->kddk_code }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="text" id="part_sub" value="{{ $autoCodes['sub'] }}" class="kddk-part w-full text-center font-mono font-bold uppercase bg-gray-200 border-gray-300 rounded-md cursor-not-allowed" readonly>
                            @endif
                        </div>

                        {{-- 4 & 5. AREA --}}
                        <div class="col-span-3">
                            <label class="block text-[10px] font-bold text-center">AREA</label>
                            {{-- HAPUS ONCHANGE, BIARKAN JS YG HANDLE --}}
                            <select id="part_area" class="kddk-part w-full text-center font-bold font-mono uppercase border-gray-300 rounded-md text-xs py-2">
                                <option value="">--</option>
                                @foreach($kddkConfig['areas'] ?? [] as $area)
                                    <option value="{{ $area['code'] }}" data-routes="{{ json_encode($area['routes'] ?? []) }}">{{ $area['code'] }} ({{ Str::limit($area['label'], 10) }})</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 6 & 7. RUTE --}}
                        <div class="col-span-3">
                            <label class="block text-[10px] font-bold text-center">RUTE</label>
                            {{-- HAPUS ONCHANGE --}}
                            <select id="part_rute" class="kddk-part w-full text-center font-bold font-mono uppercase border-gray-300 rounded-md text-xs py-2">
                                <option value="">--</option>
                            </select>
                        </div>

                        <div class="col-span-12 border-t border-gray-300 my-1"></div>

                        {{-- 8-10. URUT (Readonly, diisi AJAX) --}}
                        <div class="col-span-6">
                            <label class="block text-[10px] font-bold text-center">NO. URUT (Auto)</label>
                            <input type="text" id="part_urut" value="..." class="kddk-part w-full text-center font-mono font-bold bg-gray-100 text-gray-500 rounded-md border-gray-300 cursor-not-allowed" readonly>
                        </div>
                        {{-- 11-12. SISIP --}}
                        <div class="col-span-6">
                            <label class="block text-[10px] font-bold text-center">SISIPAN</label>
                            {{-- Hapus oninput manual, biarkan JS handle --}}
                            <input type="text" id="part_sisip" maxlength="2" value="00" class="kddk-part w-full text-center font-mono font-bold border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    {{-- Hidden Inputs untuk Submit --}}
                    <input type="hidden" name="kddk_code" id="hidden_full_code_prefix"> {{-- Digunakan untuk validasi prefix di backend --}}
                    <input type="hidden" name="prefix_code" id="hidden_prefix_code"> 
                    <input type="hidden" name="sisipan" id="hidden_sisipan">
                </div>

                <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-lg space-x-2">
                    <button type="button" onclick="window.closeKddkModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Batal</button>
                    <button type="submit" id="btn-save-kddk" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded shadow hover:bg-indigo-700 opacity-50 cursor-not-allowed" disabled>Simpan Group</button>
                </div>
            </form>
        </div>
    </div>
    
    {{-- MODAL KONFIRMASI SELEKSI --}}
    <div id="modal-confirm-selection" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden items-center justify-center z-[60] p-4 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md transform transition-all scale-100 overflow-hidden">
            
            {{-- Header --}}
            <div class="bg-indigo-600 p-4 text-white text-center">
                <div class="mx-auto w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mb-2">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold">Konfirmasi Seleksi</h3>
            </div>

            <div class="p-6">
                <p class="text-center text-gray-600 dark:text-gray-300 mb-4">
                    Anda telah memilih <span id="confirm-total-count" class="font-bold text-indigo-600 text-xl">0</span> pelanggan.
                </p>

                {{-- Tabel Rincian --}}
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 mb-4 border border-gray-200 dark:border-gray-600">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-2 text-center">Berdasarkan Jenis Layanan</p>
                    <ul id="confirm-detail-list" class="space-y-2 text-sm">
                        {{-- List akan diisi JS: Contoh "PASCABAYAR : 10 plg" --}}
                    </ul>
                </div>

                <p class="text-xs text-center text-gray-400">
                    Pastikan data yang dipilih sudah sesuai sebelum melanjutkan ke pembentukan kode.
                </p>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 flex justify-between border-t border-gray-100 dark:border-gray-600">
                <button type="button" onclick="document.getElementById('modal-confirm-selection').classList.add('hidden')" 
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium shadow-sm transition">
                    Batalkan
                </button>
                <button type="button" onclick="proceedToGenerator()" 
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-bold shadow-md transition transform active:scale-95">
                    Lanjutkan <i class="fas fa-arrow-right ml-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>