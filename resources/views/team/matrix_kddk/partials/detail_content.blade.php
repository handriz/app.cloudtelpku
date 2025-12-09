{{-- PENANDA KONTEKS UNIT (PENTING UNTUK STATE CHECKBOX) --}}
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
            {{-- TOMBOL BENTUK KDDK (Muncul saat checkbox dicentang) --}}
            <button onclick="window.confirmGrouping()" id="btn-group-kddk" class="hidden px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded shadow transition flex items-center">
                <i class="fas fa-layer-group mr-2"></i> Bentuk Group KDDK
            </button>

            <button onclick="App.Tabs.loadTabContent(App.Utils.getActiveTabName(), '{{ route('team.matrix_kddk.index') }}')" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i> Kembali
            </button>
        </div>
    </div>

    <div id="kddk-notification-container"></div>
    
    {{-- SEARCH --}}
    <form action="{{ route('team.matrix_kddk.details', ['unit' => $unit]) }}" method="GET" class="mb-4 flex" onsubmit="event.preventDefault(); App.Tabs.loadTabContent(App.Utils.getActiveTabName(), this.action + '?search=' + this.search.value);">
        <input type="text" name="search" placeholder="Cari idpelanggan / nomor meter..." class="w-full md:w-1/3 rounded-l-md border-gray-300 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-indigo-500">
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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Idpelanggan / No Meter</th>
                    
                    {{-- KOLOM BARU: INFO SURVEY & ALAMAT --}}
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Data Survey
                    </th>
                    
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status DIL</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">KDDK Saat Ini</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($customers as $c)
                <tr class="hover:bg-indigo-50 dark:hover:bg-gray-700 transition">
                    <td class="px-4 py-3 text-center align-top">
                        <input type="checkbox" name="idpel_select[]" value="{{ $c->idpel }}" data-jenis="{{ $c->jenislayanan ?? 'UMUM' }}" class="row-checkbox rounded text-indigo-600 focus:ring-indigo-500">
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap align-top">
                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $c->idpel }}</div>
                        <div class="text-xs text-red-500">{{ $c->nomor_meter_kwh ?? '-' }} ({{ $c->merk_meter_kwh }})</div>
                        <div class="text-[10px] text-indigo-500 mt-1 font-semibold">{{ $c->tarif }}-{{ $c->daya }}</div>
                    </td>
                    
                    {{-- ISI KOLOM SURVEY --}}
                    <td class="px-4 py-3 text-sm text-gray-500 align-top">
                        
                        {{-- 1. Koordinat & Gardu --}}
                        <div class="flex items-center space-x-2 mb-2 text-xs">
                            @if($c->latitudey && $c->longitudex)
                                <a href="https://www.google.com/maps?q={{ $c->latitudey }},{{ $c->longitudex }}" target="_blank" 
                                   class="flex items-center text-green-700 hover:text-green-900 font-bold bg-green-100 px-2 py-1 rounded border border-green-200 transition">
                                    <i class="fas fa-map-marked-alt mr-1.5"></i>
                                    {{ number_format($c->latitudey, 5) }}, {{ number_format($c->longitudex, 5) }}
                                </a>
                            @else
                                <span class="flex items-center text-gray-400 bg-gray-100 px-2 py-1 rounded border border-gray-200 cursor-not-allowed">
                                    <i class="fas fa-map-marker-slash mr-1.5"></i> No Coord
                                </span>
                            @endif

                            @if($c->namagd)
                                <span class="flex items-center text-blue-600 bg-blue-50 px-2 py-1 rounded border border-blue-100" title="Nama Gardu">
                                    <i class="fas fa-bolt mr-1.5 text-yellow-500"></i> {{ $c->namagd }}
                                </span>
                            @endif
                        </div>

                        {{-- 2. Foto Survey --}}
                        <div class="flex items-center space-x-2 mb-2">
                            @if($c->foto_kwh)
                                <button class="image-zoom-trigger flex items-center space-x-1 px-2 py-1 bg-white hover:bg-gray-50 text-gray-600 hover:text-indigo-600 rounded border border-gray-300 transition text-xs shadow-sm"
                                        data-zoom-type="image">
                                    <i class="fas fa-camera"></i> <span>KWH</span>
                                    <img src="{{ asset('storage/' . $c->foto_kwh) }}" class="hidden">
                                </button>
                            @endif

                            @if($c->foto_bangunan)
                                <button class="image-zoom-trigger flex items-center space-x-1 px-2 py-1 bg-white hover:bg-gray-50 text-gray-600 hover:text-indigo-600 rounded border border-gray-300 transition text-xs shadow-sm"
                                        data-zoom-type="bangunan">
                                    <i class="fas fa-home"></i> <span>Rumah</span>
                                    <img src="{{ asset('storage/' . $c->foto_bangunan) }}" class="hidden">
                                </button>
                            @endif
                            
                            @if(!$c->foto_kwh && !$c->foto_bangunan)
                                <span class="text-[10px] text-gray-400 italic">Belum ada foto survey.</span>
                            @endif
                        </div>

                        {{-- 3. User Pendataan (Master Data) --}}
                        <div class="text-gray-800 dark:text-gray-300 max-w-sm leading-snug border-t border-gray-100 pt-1 mt-1">
                            <i class="fas fa-map-pin text-gray-400 mr-1 text-[10px]"></i> {{ $c->user_pendataan }}
                        </div>
                    </td>

                    <td class="px-4 py-3 whitespace-nowrap text-center align-top">
                        @php
                            $status = strtoupper(trim($c->status_dil));
                            $activeKeywords = ['1', 'NYALA', 'AKTIF', 'HIDUP', 'ON'];
                        @endphp
                        @if(in_array($status, $activeKeywords)) 
                            <span class="px-2 py-1 text-xs font-bold rounded-full bg-green-100 text-green-800 border border-green-200">
                                Aktif
                            </span>
                        @else
                            <span class="px-2 py-1 text-xs font-bold rounded-full bg-red-100 text-red-800 border border-red-200">
                                Non-Aktif
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-mono text-gray-700 dark:text-gray-300 align-top">
                        {{ $c->current_kddk ?? '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-check-circle text-4xl text-green-400 mb-3"></i>
                            <span class="font-medium text-lg">Semua Pelanggan Sudah Memiliki Grup!</span>
                            <span class="text-sm mt-1">Tidak ada data pelanggan tanpa KDDK di unit ini.</span>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginasi Ringkas --}}
    <div class="mt-4">{{ $customers->onEachSide(1)->links() }}</div>

    {{-- MODAL GENERATOR KDDK (STRUCTURE FIX) --}}
    <div id="modal-create-kddk" class="fixed inset-0 bg-gray-900 bg-opacity-80 hidden items-center justify-center z-[1500] p-4 backdrop-blur-sm">
        
        {{-- CONTAINER MODAL: Flex Column & Max Height --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-3xl transform transition-all flex flex-col max-h-[90vh] relative">
            
            <form action="{{ route('team.matrix_kddk.store_group') }}" 
                  method="POST" 
                  class="ajax-form flex flex-col h-full overflow-hidden" {{-- PENTING: Overflow Hidden agar child scrollable --}}
                  id="kddk-generator-form" 
                  data-success-redirect-tab="Matrix KDDK"
                  data-sequence-url="{{ route('team.matrix_kddk.next_sequence', '') }}">
                  
                @csrf
                <input type="hidden" name="unitup" value="{{ $unit }}">
                
                {{-- 1. HEADER (Fixed) --}}
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-lg flex justify-between items-center shrink-0">
                    <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-barcode text-indigo-600 mr-2"></i> Generator Kode KDDK</h3>
                    <span class="text-xs bg-indigo-100 text-indigo-800 px-2 py-1 rounded-full"><span id="count-selected">0</span> Plg Dipilih</span>
                </div>
                
                {{-- 2. BODY (Scrollable) --}}
                <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                    <div id="hidden-inputs-container"></div> 

                    {{-- INFO ESTIMASI --}}
                    <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-100 text-center">
                        <p class="text-[10px] font-bold text-gray-500 uppercase mb-1">Estimasi Kode</p>
                        <div class="flex justify-center items-center space-x-2 text-xl font-mono font-bold text-indigo-700">
                            <span id="preview-start">_______001__</span>
                            <span class="text-gray-400 text-sm"><i class="fas fa-arrow-right"></i></span>
                            <span id="preview-end">_______0XX__</span>
                        </div>
                        <p class="text-xs text-blue-600 mt-2">
                            Otomatis mengurutkan <span id="count-display" class="font-bold">0</span> pelanggan.
                        </p>
                        <input type="hidden" id="final_kddk_preview"> 
                        <p id="kddk_error_msg" class="text-xs text-red-500 mt-1 h-4"></p>
                    </div>

                    {{-- FORM BUILDER --}}
                    <div class="grid grid-cols-12 gap-4 bg-gray-50 p-5 rounded-lg border border-gray-200">
                        
                        {{-- 1-3. HIRARKI --}}
                        <div class="col-span-4">
                            <label class="block text-[10px] font-bold text-center text-gray-500 mb-1">UP3</label>
                            <input type="text" id="part_up3" value="{{ $autoCodes['up3'] ?? '_' }}" class="kddk-part w-full text-center font-mono font-bold text-lg uppercase bg-gray-200 border-gray-300 rounded-md cursor-not-allowed" readonly>
                        </div>
                        <div class="col-span-4">
                            <label class="block text-[10px] font-bold text-center text-gray-500 mb-1">ULP</label>
                            <input type="text" id="part_ulp" value="{{ $autoCodes['ulp'] ?? '_' }}" class="kddk-part w-full text-center font-mono font-bold text-lg uppercase bg-gray-200 border-gray-300 rounded-md cursor-not-allowed" readonly>
                        </div>
                        <div class="col-span-4">
                            <label class="block text-[10px] font-bold text-center text-gray-500 mb-1">SUB UNIT</label>
                            @if(isset($subUnits) && $subUnits->isNotEmpty())
                                <select id="part_sub" class="kddk-part w-full text-center font-mono font-bold text-lg uppercase border-gray-300 rounded-md bg-white border-yellow-400 focus:ring-yellow-500 shadow-sm">
                                    <option value="">-</option>
                                    <option value="A">A</option>
                                    @foreach($subUnits as $sub)
                                        <option value="{{ $sub->kddk_code }}">{{ $sub->kddk_code }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="text" id="part_sub" value="{{ $autoCodes['sub'] }}" class="kddk-part w-full text-center font-mono font-bold text-lg uppercase bg-gray-200 border-gray-300 rounded-md cursor-not-allowed" readonly>
                            @endif
                        </div>

                        <div class="col-span-12 border-t border-gray-200 my-1"></div>

                        {{-- 4 & 5. AREA --}}
                        <div class="col-span-6">
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                <span class="bg-indigo-100 text-indigo-800 px-1 rounded text-[10px] mr-1">DGT 4-5</span> KODE AREA
                            </label>
                            <select id="part_area" class="kddk-part w-full font-bold text-gray-800 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 text-sm py-2.5">
                                <option value="">-- Pilih --</option>
                                @foreach($kddkConfig['areas'] ?? [] as $area)
                                    <option value="{{ $area['code'] }}" data-label="{{ $area['label'] }}" data-routes="{{ json_encode($area['routes'] ?? []) }}">{{ $area['code'] }} - {{ Str::limit($area['label'], 30) }}</option>
                                @endforeach
                            </select>
                            <div id="area-label-display" class="text-[10px] text-indigo-600 mt-1 h-3 truncate font-semibold"></div>
                        </div>

                        {{-- 6 & 7. RUTE --}}
                        <div class="col-span-6">
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                <span class="bg-indigo-100 text-indigo-800 px-1 rounded text-[10px] mr-1">DGT 6-7</span> KODE RUTE
                            </label>
                            <select id="part_rute" class="kddk-part w-full font-bold text-gray-800 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 text-sm py-2.5" onchange="window.updateLabelDisplay && window.updateLabelDisplay()">
                                <option value="">-- Pilih --</option>
                            </select>
                            <div id="rute-label-display" class="text-[10px] text-indigo-600 mt-1 h-3 truncate font-semibold"></div>
                        </div>

                        <div class="col-span-12 border-t border-gray-200 my-1"></div>

                        {{-- 8-10. URUT --}}
                        <div class="col-span-8">
                            <label class="block text-[10px] font-bold text-gray-500 text-center">START URUT (AUTO)</label>
                            <input type="text" id="part_urut" value="..." class="kddk-part w-full text-center font-mono text-lg font-bold bg-gray-100 text-gray-500 rounded-md border-gray-300 cursor-not-allowed" readonly>
                        </div>
                        {{-- 11-12. SISIP --}}
                        <div class="col-span-4">
                            <label class="block text-[10px] font-bold text-center text-gray-500">SISIPAN</label>
                            <input type="text" id="part_sisip" maxlength="2" value="00" class="kddk-part w-full text-center font-mono text-lg font-bold border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    {{-- Hidden Inputs --}}
                    <input type="hidden" name="kddk_code" id="hidden_full_code_prefix">
                    <input type="hidden" name="prefix_code" id="hidden_prefix_code"> 
                    <input type="hidden" name="sisipan" id="hidden_sisipan">
                </div>

                {{-- 3. FOOTER (Fixed at Bottom) --}}
                <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-lg space-x-2 border-t border-gray-200 shrink-0">
                    <button type="button" onclick="window.closeKddkModal()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm font-medium shadow-sm">Batal</button>
                    <button type="submit" id="btn-save-kddk" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-md shadow hover:bg-indigo-700 text-sm opacity-50 cursor-not-allowed transition" disabled>Simpan Group</button>
                </div>
            </form>
        </div>
    </div>
    
    {{-- MODAL KONFIRMASI (Tetap Sama) --}}
    <div id="modal-confirm-selection" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden items-center justify-center z-[1600] p-4 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-sm transform transition-all">
             <div class="bg-indigo-600 p-3 text-white text-center rounded-t-xl">
                <h3 class="text-md font-bold">Konfirmasi Seleksi</h3>
            </div>
            <div class="p-5 text-center">
                <p class="text-gray-600 dark:text-gray-300 text-sm mb-3">
                    Total: <span id="confirm-total-count" class="font-bold text-indigo-600 text-lg">0</span> Pelanggan
                </p>
                <div class="bg-gray-50 dark:bg-gray-700 rounded p-2 mb-3 text-left text-xs border border-gray-200">
                    <ul id="confirm-detail-list" class="space-y-1"></ul>
                </div>
                <div class="flex justify-center space-x-2">
                    <button type="button" onclick="document.getElementById('modal-confirm-selection').classList.add('hidden')" class="px-3 py-1.5 bg-gray-200 rounded text-sm">Batal</button>
                    <button type="button" onclick="window.proceedToGenerator()" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm shadow">Lanjut</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL SUKSES GENERATOR --}}
    <div id="modal-success-generator" class="fixed inset-0 bg-gray-900 bg-opacity-80 hidden items-center justify-center z-[1700] p-4 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-sm transform transition-all scale-100 overflow-hidden text-center">
            
            {{-- Icon Sukses Animasi --}}
            <div class="bg-green-100 dark:bg-green-900/30 p-6 flex justify-center">
                <div class="w-20 h-20 bg-green-500 text-white rounded-full flex items-center justify-center text-4xl shadow-lg animate-bounce-short">
                    <i class="fas fa-check"></i>
                </div>
            </div>

            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Berhasil Dibentuk!</h3>
                
                {{-- Pesan Dinamis --}}
                <p id="success-modal-message" class="text-gray-600 dark:text-gray-300 text-sm mb-4">
                    Grup KDDK baru telah berhasil dibuat.
                </p>

                {{-- Info Tambahan --}}
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600 text-left text-xs space-y-1">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Kode Awal:</span>
                        <span id="success-start-code" class="font-mono font-bold text-indigo-600"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Jumlah Pelanggan:</span>
                        <span id="success-total-count" class="font-bold text-gray-800 dark:text-gray-200"></span>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-t border-gray-100 dark:border-gray-600">
                <button type="button" onclick="window.closeSuccessModal()" 
                        class="w-full px-4 py-2 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700 shadow-md transition transform active:scale-95">
                    Selesai
                </button>
            </div>
        </div>
    </div>
    
    <style>
        @keyframes bounce-short {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .animate-bounce-short { animation: bounce-short 0.5s ease-in-out 2; }
    </style>

</div>