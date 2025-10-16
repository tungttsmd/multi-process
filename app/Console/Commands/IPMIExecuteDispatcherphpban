<?php

namespace App\Console\Commands;

use App\Jobs\PowerExecutor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IPMIExecuteDispatcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:exe 
    {--a= : Hành động cần thực hiện (on/off/reset)} 
    {--h= : Tên host cần thao tác}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->option('a');
        $host = $this->option('h');

        if (in_array($action, ['on', 'off', 'reset', 'rs'])) {
            if ($action == 'rs') {
                $action = 'reset';
            };
            $host = DB::table('hosts')->where('ip', $host)->first();

            if ($host == null) {
                $this->error("Host:$host không tồn tại");
            } else {
                $confirm = $this->confirm("Xác nhận: ($host->ip) == chassis power $action ==");
                if ($confirm) {
                    try {
                        $this->action($action, $host->username, $host->password, $host->ip);
                        $this->success("Gửi lệnh thành công: ($host->ip) == chassis power $action ==");
                    } catch (\Exception $e) {
                        $this->error("Gửi lệnh thất bại (lỗi): ($host->ip) == chassis power $action ==");
                    }
                } else {
                    $this->error("Đã huỷ thao tác dispatch: ($host->ip) == chassis power $action ==");
                }
            }
        } else {
            $this->error('Action không hợp lệ');
        }
    }
    protected function action(string $action, string $username, string $password, string $host)
    {
        if (in_array($action, ['on', 'off', 'reset'])) {
            if ($action === 'on') {
                dispatch(new PowerExecutor($host, $username, $password, $action));
            } elseif ($action === 'off') {
                dispatch(new PowerExecutor($host, $username, $password, $action));
            } elseif ($action === 'reset') {
                dispatch(new PowerExecutor($host, $username, $password, $action));
            }
        } else {
            return false;
        }
    }
    protected function success(string $message)
    {
        $this->line("<fg=green>{$message}</>");
    }
    protected function clean()
    {
        $this->output->write("\033c"); // reset screen (ANSI)
    }
    protected function pause()
    {
        fgets(STDIN);
    }
}
