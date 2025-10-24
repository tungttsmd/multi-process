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
                <a href="#" class="block px-3 py-2 font-medium rounded-lg hover:bg-blue-50">Giám sát</a>
                <a href="#" class="block px-3 py-2 font-medium rounded-lg hover:bg-blue-50">Cài đặt</a>
            </nav>
        </aside>

        <!-- Main content -->
        <main class="flex-1 p-6 overflow-y-auto">
            <h1 class="mb-6 text-2xl font-semibold">Bảng điều khiển</h1>

            <!-- Grid responsive -->
            <div style="gap: 4px; padding:0" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <!-- Card -->
                @foreach ($fetch as $index => $item)
                    <div class="p-2 transition-all bg-white rounded shadow b-1 hover:shadow-md">
                        <h3 class="mb-1 text-lg font-semibold">{{ $item->name ?? null }}</h3>
                        <div class="d-flex">
                            <button wire:click="powerAction('on')" type="button">Power ON |</button>
                            <button type="button">Power OFF |</button>
                            <button type="button">Power RESET</button>
                        </div>
                        <style>
                            .statistic-display {
                                display: none;
                            }
                        </style>
                        <div class="statistic-display">
                            <p class="mt-1 text-sm text-gray-500">CPU 1:
                                <span>{{ $item->sensor_log->data->CPU0_Temp ?? null }} °C</span>
                            </p>
                            <p class="mt-1 text-sm text-gray-500">CPU 2:
                                <span>{{ $item->sensor_log->data->CPU1_Temp ?? null }} °C</span>
                            </p>
                            <p class="mt-1 text-sm text-gray-500">FAN CPU 1:
                                <span>{{ $item->sensor_log->data->CPU0_FAN ?? null }} RPM</span>
                            </p>
                            <p class="mt-1 text-sm text-gray-500">FAN CPU 2:
                                <span>{{ $item->sensor_log->data->CPU1_FAN ?? null }} RPM</span>
                            </p>
                        </div>
                        <p>
                            <span class="mt-1 text-sm text-gray-500">{{ $item->ip ?? null }}</span>
                        </p>
                        <p>
                            @if (isset($item->power_log->status) && $item->power_log->status === 'success')
                                {{ strtoupper($item->power_log->data->power) }}
                            @else
                                N/A
                            @endif
                        </p>

                    </div>
                @endforeach


                <!-- Thêm card khác... -->
            </div>
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
