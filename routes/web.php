<?php

use App\Http\Controllers\SensorFetcher;
use App\Jobs\TestJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\View;
use Symfony\Component\Process\Process;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;


Route::get('/testjson', function () {
    $json = "{\n    \"ip\": \"192.168.6.118\",\n    \"timestamp\": \"2025-10-19 20:53:00\",\n    \"status\": \"success\",\n    \"message\": \"Sensor data fetched successfully\",\n    \"data\": {\n        \"CPU0_Temp\": 56,\n        \"CPU1_Temp\": 69,\n        \"CPU0_FAN\": 0,\n        \"CPU1_FAN\": 1040\n    }\n}";
    dd(json_decode($json)->status);
});

// Lệnh sensor auto (fetch - cấm xóa) của /ipmi-grid
Route::get('/api/sensors', function () {
    $sensorFetcher = new SensorFetcher();
    return $sensorFetcher->all();
})->middleware(['auth']);

// Lệnh sensor fetch cụ thể
Route::get('/api/redis/sensor/{ip}', function ($ip){
    $key = "ipmi_sensor:".str_replace('.','_',$ip);
    Artisan::call('redis:fetch '. $key);
     $output = Artisan::output();

    return response()->json([
        'status' => 'ok',
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
        'status' => 'ok',
        'key' => $key,
        'output' => $output,
    ]);
})->name('status');

// Lệnh ipmi power
Route::get('/api/ipmi/power/{ip}/{action}', function ($ip,$action){
    $key = "ipmi_sensor:".str_replace('.','_',$ip);
    Artisan::call('ipmi:power '. "$action:$key");
     $output = Artisan::output();

    return response()->json([
        'status' => 'ok',
        'action' => $action,
        'key' => $key,
        'output' => $output,
    ]);
});

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
})->middleware(['auth'])->name('ipmi.grid');

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

