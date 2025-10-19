<?php

use App\Jobs\TestJob;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\View;

Route::get('/ping', function () {
    return "Ping đang chạy qua queue!";
});


// IPMI Grid View
Route::get('/ipmi-grid', function () {
    return view('ipmi-grid');
})->middleware(['auth'])->name('ipmi.grid');

Route::get('/', function () {
    return view('welcome'); // hoặc view tương ứng
})->name('home');

Route::get('/redis', function () {
    $keys = Redis::keys('*');
    $result = [];
    dump($keys);
    foreach ($keys as $key) {
        // Lấy type của key
        $type = Redis::type($key);
        $value = null;

        switch ($type) {
            case 'list':
                // Đọc toàn bộ phần tử list
                $value = Redis::lrange($key, 0, -1);
                break;
            case 'string':
                $value = Redis::get($key);
                break;
            case 'set':
                $value = Redis::smembers($key);
                break;
            case 'hash':
                $value = Redis::hgetall($key);
                break;
            default:
                $value = '(unsupported type)';
        }

        $result[] = [
            'key' => $key,
            'type' => $type,
            'value' => $value,
        ];
    }

    dd(response()->json($result, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return;
});
Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

require __DIR__ . '/auth.php';

