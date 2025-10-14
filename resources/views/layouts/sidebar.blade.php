{{-- resources/views/layouts/sidebar.blade.php --}}
<aside id="sidebarMenu" class="w-64 bg-white shadow-md flex-shrink-0 min-h-screen border-r border-gray-200 transition-all duration-300">
    {{-- Area Logo Aplikasi --}}
    <div class="flex items-center justify-center h-16 border-b border-gray-200">
        <a href="{{ route('dashboard') }}" class="flex items-center space-x-2">
            <x-application-logo class="block h-9 w-auto fill-current text-indigo-600" />
            <span class="text-xl font-semibold text-gray-800 menu-text">CloudTelpku</span>
        </a>
    </div>

    {{-- Navigasi Menu Utama (Dinamis) --}}
    <nav class="mt-4 px-3">
        <ul class="space-y-1">
            @foreach ($menuItems as $menu)
                {{-- Cek jika menu punya anak/submenu --}}
                @if ($menu->children->isNotEmpty())
                    {{-- Jika punya, buat sebagai menu dropdown --}}
                    <li x-data="{ open: {{ $menu->is_active ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center justify-between w-full p-2 text-sm font-medium text-left rounded-lg transition duration-150 ease-in-out {{ $menu->is_active ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700 hover:bg-indigo-100 hover:text-indigo-700' }}">
                            <div class="flex items-center">
                                <i class="{{ $menu->icon }} w-6 text-center"></i>
                                <span class="ml-2 menu-text">{{ $menu->name }}</span>
                            </div>
                            <i class="fas fa-chevron-down w-4 h-4 transform transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        
                        <ul x-show="open" x-collapse class="py-1 space-y-1 ml-4 mt-1">
                            @foreach ($menu->children as $child)
                                <li>
                                    <a href="{{ $child->route_name ? route($child->route_name) : '#' }}"
                                       data-tab-link="{{ $child->name }}"                                                                                    
                                       class="flex items-center p-2 text-sm font-normal rounded-lg transition duration-150 ease-in-out {{ $child->is_active ? 'text-indigo-700 bg-indigo-50' : 'text-gray-600 hover:bg-indigo-100 hover:text-indigo-700' }}">
                                        <i class="{{ $child->icon }} w-6 text-center"></i>
                                        <span class="ml-2 menu-text">{{ $child->name }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @else
                    {{-- Jika tidak punya anak, buat sebagai link tunggal --}}
                    <li>
                        <a href="{{ $menu->route_name ? route($menu->route_name) : '#' }}"
                           data-tab-link="{{ $menu->name }}"
                           class="flex items-center p-2 text-sm font-medium rounded-lg transition duration-150 ease-in-out {{ $menu->is_active ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700 hover:bg-gray-100' }}">
                            <i class="{{ $menu->icon }} w-6 text-center"></i>
                            <span class="ml-2 menu-text">{{ $menu->name }}</span>
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </nav>
</aside>