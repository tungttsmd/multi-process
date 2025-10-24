<?php

use App\Http\Controllers\IndexController;
use App\Http\Controllers\PowerAction;
use App\Http\Controllers\PowerFetcher;
use App\Http\Controllers\SensorFetcher;
use App\Livewire\IndexLw;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Artisan;

// ========= start Nguồn dữ liệu =========
Route::get('/api/ipmi/power/fetch', function () {
    $powerFetcher = new PowerFetcher();
    return $powerFetcher->fetchAll();
});

Route::get('/api/ipmi/sensor/fetch', function () {
    $sensorFetcher = new SensorFetcher();
    return $sensorFetcher->fetchAll();
});

Route::get('/api/ipmi/power/{ip}/{action}', function ($ip,$action){
    $powerAction = new PowerAction();
    return $powerAction->action($ip, $action);
});
// ========= end Nguồn dữ liệu =========

// ========= start Giao diện ===========
Route::get('/index', [IndexController::class, 'index']);

// ========= start Giao diện ===========


Route::get('/testjson', function () {
return json_decode(file_get_contents(storage_path('app/hosts.json')), true);
});

// Lệnh sensor fetch cụ thể
Route::get('/api/redis/sensor/{ip}', function ($ip){
    $key = "ipmi_sensor:".str_replace('.','_',$ip);
    Artisan::call('redis:fetch '. $key);
    $output = Artisan::output();

    return response()->json([
        'status' => 'success',
        'key' => $key,
        'output' => $output,
    ]);
})->name('sensors');

// Lệnh status fetch cụ thể
Route::get('/api/redis/status/{ip}', function ($ip){
    $key = "ipmi_status:".str_replace('.','_',$ip);
    Artisan::call('redis:fetch '. $key);
    $output = Artisan::output();

    return response()->json([
        'status' => 'success',
        'key' => $key,
        'output' => $output,
    ]);
})->name('power');

// Lệnh ipmi power


// Power control API
// Route::post('/api/power', function (Request $request) {
//     $action = $request->input('action');
//     $ip = $request->input('ip');

//     if (!in_array($action, ['on', 'off', 'reset'], true) || empty($ip)) {
//         return response()->json([
//             'success' => false,
//             'error' => 'Invalid payload. Expecting action in [on, off, reset] and ip.'
//         ], 422);
//     }

//     $phpBin = PHP_BINARY ?: 'php';
//     $cmd = [$phpBin, 'artisan', 'ipmi:power', $action . ':' . $ip];

//     try {
//         $process = new Process($cmd, base_path());
//         $process->setTimeout(60);
//         $process->run();

//         if (!$process->isSuccessful()) {
//             return response()->json([
//                 'success' => false,
//                 'error' => trim($process->getErrorOutput()) ?: 'Command failed',
//                 'output' => trim($process->getOutput()),
//             ], 500);
//         }

//         return response()->json([
//             'success' => true,
//             'output' => trim($process->getOutput()),
//         ]);
//     } catch (\Throwable $e) {
//         return response()->json([
//             'success' => false,
//             'error' => $e->getMessage(),
//         ], 500);
//     }
// })->middleware(['auth']);

// IPMI Grid View
Route::get('/ipmi-grid', function () {
    return view('ipmi-grid');
})->name('ipmi-grid');

Route::get('/', function () {
    return view('welcome'); // hoặc view tương ứng
})->name('home');
Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
// Route::get('/redis', function () {
//     $keys = Redis::keys('*');
//     $result = [];
//     dump($keys);
//     foreach ($keys as $key) {
//         // Lấy type của key
//         $type = Redis::type($key);
//         $value = null;

//         switch ($type) {
//             case 'list':
//                 // Đọc toàn bộ phần tử list
//                 $value = Redis::lrange($key, 0, -1);
//                 break;
//             case 'string':
//                 $value = Redis::get($key);
//                 break;
//             case 'set':
//                 $value = Redis::smembers($key);
//                 break;
//             case 'hash':
//                 $value = Redis::hgetall($key);
//                 break;
//             default:
//                 $value = '(unsupported type)';
//         }

//         $result[] = [
//             'key' => $key,
//             'type' => $type,
//             'value' => $value,
//         ];
//     }

//     dd(response()->json($result, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
//     return;
// });


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

