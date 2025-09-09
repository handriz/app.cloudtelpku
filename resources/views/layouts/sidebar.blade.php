{{-- resources/views/layouts/sidebar.blade.php --}}
<aside id="sidebarMenu" class="w-64 bg-white shadow-md flex-shrink-0 min-h-screen border-r border-gray-200 transition-all duration-300">
    {{-- Area Logo Aplikasi + Tombol Toggle --}}
    <div class="flex items-center justify-between h-16 border-b border-gray-200 px-4">
        <a href="{{ route('dashboard') }}" class="flex items-center space-x-2">
            <x-application-logo class="block h-9 w-auto fill-current text-indigo-600" />
            <span class="text-xl font-semibold text-gray-800 menu-text">CloudTelpku</span>
        </a>
    </div>

    {{-- Navigasi Menu Utama (dinamis) --}}
    <nav class="mt-4 px-3">
        <ul class="space-y-1">
            @foreach($menuItems as $menuItem)
                @php
                    $isActive = false;
                    if ($menuItem->route_name && Route::has($menuItem->route_name)) {
                        $isActive = request()->routeIs($menuItem->route_name);
                    }
                    if ($menuItem->children->isNotEmpty()) {
                        foreach ($menuItem->children as $childMenuItem) {
                            if ($childMenuItem->route_name && Route::has($childMenuItem->route_name)) {
                                if (request()->routeIs($childMenuItem->route_name)) {
                                    $isActive = true;
                                    break;
                                }
                            }
                        }
                    }
                @endphp

                @if($menuItem->children->isNotEmpty())
                    <li x-data="{ open: {{ $isActive ? 'true' : 'false' }} }" class="relative">
                        <a href="#"
                            @click.prevent="open = ! open"
                            class="flex items-center p-2 text-sm font-medium rounded-lg transition duration-150 ease-in-out
                                     {{ $isActive ? 'bg-indigo-500 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                            @if($menuItem->icon)
                                <i class="{{ $menuItem->icon }} w-5 h-5 mr-3"></i>
                            @else
                                <i class="fas fa-folder w-5 h-5 mr-3 text-indigo-400"></i>
                            @endif
                            <span class="flex-1 whitespace-nowrap menu-text">{{ $menuItem->name }}</span>
                            <svg class="ml-auto w-4 h-4 transform transition-transform" :class="{ 'rotate-180': open, 'rotate-0': ! open }" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </a>
                        <ul x-show="open"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                            class="py-2 space-y-1 ml-4" style="display: none;">
                            @foreach($menuItem->children as $childMenuItem)
                                @php
                                    $isChildActive = ($childMenuItem->route_name && Route::has($childMenuItem->route_name)) ? request()->routeIs($childMenuItem->route_name) : false;
                                @endphp
                                <li>
                                    @if($childMenuItem->route_name && Route::has($childMenuItem->route_name))
                                        {{-- KUNCI PERUBAHAN: Gunakan Str::replace untuk nama tab --}}
                                        <a href="{{ route($childMenuItem->route_name) }}"
                                           data-tab-link="{{ Str::replace(['-', '_'], ' ', $childMenuItem->name) }}"
                                           class="flex items-center p-2 text-sm font-normal rounded-lg transition duration-150 ease-in-out
                                            {{ $isChildActive ? 'bg-indigo-100 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                                            @if($childMenuItem->icon)
                                                <i class="{{ $childMenuItem->icon }} w-4 h-4 mr-3"></i>
                                            @else
                                                <i class="far fa-dot-circle w-4 h-4 mr-3 text-indigo-300"></i>
                                            @endif
                                            <span class="flex-1 whitespace-nowrap menu-text">{{ $childMenuItem->name }}</span>
                                        </a>
                                    @elseif($childMenuItem->url)
                                        <a href="{{ $childMenuItem->url }}"
                                            data-tab-link="{{ Str::replace(['-', '_'], ' ', $childMenuItem->name) }}"
                                           class="flex items-center p-2 text-sm font-normal rounded-lg transition duration-150 ease-in-out
                                            {{ request()->is(ltrim(parse_url($childMenuItem->url, PHP_URL_PATH), '/')) ? 'bg-indigo-100 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                                            @if($childMenuItem->icon)
                                                <i class="{{ $childMenuItem->icon }} w-4 h-4 mr-3"></i>
                                            @else
                                                <i class="far fa-dot-circle w-4 h-4 mr-3 text-indigo-300"></i>
                                            @endif
                                            <span class="flex-1 whitespace-nowrap menu-text">{{ $childMenuItem->name }}</span>
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @else
                    <li>
                        @if($menuItem->route_name && Route::has($menuItem->route_name))
                            {{-- KUNCI PERUBAHAN: Gunakan Str::replace untuk nama tab --}}
                            <a href="{{ route($menuItem->route_name) }}"
                               data-tab-link="{{ Str::replace(['-', '_'], ' ', $menuItem->name) }}"
                               class="flex items-center p-2 text-sm font-normal rounded-lg transition duration-150 ease-in-out
                                     {{ $isActive ? 'bg-indigo-500 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                @if($menuItem->icon)
                                    <i class="{{ $menuItem->icon }} w-5 h-5 mr-3"></i>
                                @endif
                                <span class="flex-1 whitespace-nowrap menu-text">{{ $menuItem->name }}</span>
                            </a>
                        @elseif($menuItem->url)
                            <a href="{{ $menuItem->url }}"
                               data-tab-link="{{ Str::replace(['-', '_'], ' ', $menuItem->name) }}"
                               class="flex items-center p-2 text-sm font-normal rounded-lg transition duration-150 ease-in-out
                                     {{ request()->is(ltrim(parse_url($menuItem->url, PHP_URL_PATH), '/')) ? 'bg-indigo-500 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                @if($menuItem->icon)
                                    <i class="{{ $menuItem->icon }} w-5 h-5 mr-3"></i>
                                @endif
                                <span class="flex-1 whitespace-nowrap menu-text">{{ $menuItem->name }}</span>
                            </a>
                        @else
                            <span class="flex items-center px-2 py-2 text-xs font-semibold uppercase text-gray-500 mt-4">
                                @if($menuItem->icon)
                                    <i class="{{ $menuItem->icon }} w-4 h-4 mr-2"></i>
                                @endif
                                {{ $menuItem->name }}
                            </span>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    </nav>
</aside>