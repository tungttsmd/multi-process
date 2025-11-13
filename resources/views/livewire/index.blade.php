<div class="flex flex-col min-h-screen text-gray-900 bg-gray-50">
    <!-- 🔸 Header -->
    <header class="sticky top-0 z-50 flex items-center justify-between px-4 py-3 bg-white shadow-sm">
        <div class="flex items-center space-x-2">
            {{-- <img src="{{ asset('images/logo.webp') }}" alt="Logo" class=" w-28"> --}}
            <span
                class="text-xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-blue-500 animate-pulse">ServerGrid</span>
        </div>

        <nav class="hidden space-x-6 md:flex">
            <a href="#" class="font-medium text-gray-700 hover:text-blue-600">Dashboard</a>
            <a href="#" class="font-medium text-gray-700 hover:text-blue-600">Servers</a>
            <a href="#" class="font-medium text-gray-700 hover:text-blue-600">Analytics</a>
        </nav>

        <button class="p-2 border rounded-lg md:hidden hover:bg-gray-100" id="menuToggle">
            <!-- Hamburger icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </header>

    <!-- 🔸 Layout Grid -->
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar" class="hidden w-64 overflow-y-auto transition-all bg-white border-r shadow-md md:block">
            <nav class="p-4 space-y-2">
                <a href="#" class="block px-3 py-2 font-medium rounded-lg hover:bg-blue-50">Tổng quan</a>
                <a href="#" class="block px-3 py-2 font-medium rounded-lg hover:bg-blue-50">Quản lý máy chủ</a>
                <a href="#" class="px-3 py-2 font-medium rounded-lg bloc k hover:bg-blue-50">Giám sát</a>
                <a href="#" class="block px-3 py-2 font-medium rounded-lg hover:bg-blue-50">Cài đặt</a>
            </nav>
        </aside>

        <!-- Main content -->
        <main class="flex-1 p-6 overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-semibold">Bảng điều khiển</h1>

                <button wire:click="toggleView"
                    class="flex items-center px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    @if ($viewMode === 'grid')
                        <!-- Icon table -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        Chuyển sang bảng
                    @else
                        <!-- Icon grid -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h4v4H4V6zm0 8h4v4H4v-4zm8-8h4v4h-4V6zm0 8h4v4h-4v-4z" />
                        </svg>
                        Chuyển sang thẻ
                    @endif
                </button>
            </div>

            {{-- 🔳 GRID MODE --}}
            @if ($viewMode === 'grid')
                <div wire:poll.12s="fetchRefresh" style="gap: 4px; padding:0"
                    class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($fetch as $index => $item)
                        <x-partials.server-grid-card :item=$item />
                    @endforeach
                </div>
            @endif

            {{-- 📊 TABLE MODE --}}
            @if ($viewMode === 'table')
                <div wire:poll.12s="fetchRefresh"
                    class="overflow-x-auto bg-white rounded-lg shadow max-h-[80vh] border border-gray-200">
                    <table class="min-w-full text-sm text-left border-collapse">
                        <thead class="sticky top-0 z-10 bg-gray-100 border-b">
                            <tr>
                                <th class="px-4 py-3 font-medium text-gray-600">Máy chủ</th>
                                <th class="px-4 py-3 font-medium text-gray-600">IP</th>
                                <th class="px-4 py-3 font-medium text-center text-gray-600">Power</th>
                                <th class="px-4 py-3 font-medium text-center text-gray-600">CPU0 Temp</th>
                                <th class="px-4 py-3 font-medium text-center text-gray-600">CPU1 Temp</th>
                                <th class="px-4 py-3 font-medium text-center text-gray-600">CPU0 Fan</th>
                                <th class="px-4 py-3 font-medium text-center text-gray-600">CPU1 Fan</th>
                                <th class="px-4 py-3 font-medium text-center text-gray-600">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($fetch as $index => $item)
                                @php
                                    $t0 = $item->sensor_log->data->CPU0_Temp ?? null;
                                    $t1 = $item->sensor_log->data->CPU1_Temp ?? null;
                                    $maxTemp = max([$t0, $t1]);
                                    $rowBg =
                                        $maxTemp > 80 ? 'bg-red-50' : ($maxTemp > 65 ? 'bg-yellow-50' : 'bg-green-50');
                                @endphp
                                <tr class="hover:bg-gray-50 transition {{ $rowBg }}">
                                    <td class="px-4 py-3 font-semibold">{{ $item->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $item->ip ?? '' }}</td>
                                    <td class="px-4 py-3 font-medium text-center">
                                        @php
                                            $power = $item->power_log->data->power ?? null;
                                            $color = match ($power) {
                                                'on' => 'text-green-600',
                                                'off' => 'text-red-500',
                                                default => 'text-gray-400',
                                            };
                                        @endphp
                                        <span class="{{ $color }}">{{ strtoupper($power ?? '—') }}</span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center font-medium {{ $t0 > 70 ? 'text-red-500' : 'text-green-600' }}">
                                        {{ $t0 ?? '—' }}°C
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center font-medium {{ $t1 > 70 ? 'text-red-500' : 'text-green-600' }}">
                                        {{ $t1 ?? '—' }}°C
                                    </td>
                                    <td class="px-4 py-3 text-center">{{ $item->sensor_log->data->CPU0_FAN ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">{{ $item->sensor_log->data->CPU1_FAN ?? '—' }}
                                    </td>

                                    <td class="px-4 py-3 text-center">
                                        <div class="inline-flex space-x-1">
                                            <button
                                                class="px-2 py-1 text-xs font-medium text-white bg-green-500 rounded hover:bg-green-600"
                                                wire:click="powerAction('{{ $item->ip }}', 'on')">Bật</button>
                                            <button
                                                class="px-2 py-1 text-xs font-medium text-white bg-yellow-500 rounded hover:bg-yellow-600"
                                                wire:click="powerAction('{{ $item->ip }}', reset')">Reset</button>
                                            <button
                                                class="px-2 py-1 text-xs font-medium text-white bg-red-500 rounded hover:bg-red-600"
                                                wire:click="powerAction('{{ $item->ip }}', 'off')">Tắt</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </main>


    </div>

    <!-- 🔸 Footer -->
    <footer class="p-4 text-sm text-center text-gray-500 bg-white shadow-inner">
        &copy; 2025 TP Server. All rights reserved.
    </footer>

    <script>
        // Toggle sidebar trên mobile
        const menuBtn = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        menuBtn?.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
        });
    </script>
</div>
