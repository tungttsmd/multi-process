<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Laravel'))</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    @livewireStyles
</head>

<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen">
        @includeWhen(View::hasSection('header'), 'layouts.header')

        <!-- Page Content -->
        <main>
            @yield('content')
        </main>
    </div>

    @includeWhen(View::hasSection('footer'), 'layouts.footer')

    @stack('scripts')
    @stack('modals')
    @livewireScripts
</body>

</html>
