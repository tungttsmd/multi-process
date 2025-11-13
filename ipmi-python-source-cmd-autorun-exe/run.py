# -*- coding: utf-8 -*-
import subprocess
import time
import os
import psutil
import webbrowser
import socket
from rich.console import Console
from rich.table import Table
from rich.panel import Panel

console = Console()

# === CẤU HÌNH ===
PROJECT_DIR = r"C:\wamp64\www\ipmiWebserver"
REDIS_EXE = r"C:\wamp64\www\ipmiWebserver\redis\redis-server.exe"
REDIS_CONF = r"C:\wamp64\www\ipmiWebserver\redis\redis.conf"
WEB_URL = "http://127.0.0.1:8000/index"
WAMP_EXE = r"C:\wamp64\wampmanager.exe"

CREATE_NO_WINDOW = 0x08000000  # Ẩn cửa sổ CMD

QUEUE_LIST = [
    "processor_user_power",
    "processor_user_sensor",
    "processor_user_update",
    "processor_user_execute",
]

def is_running(name):
    """Kiểm tra tiến trình theo tên"""
    for proc in psutil.process_iter(attrs=["name"]):
        if name.lower() in proc.info["name"].lower():
            return True
    return False

def is_queue_running(queue_name):
    """Kiểm tra xem queue:work --queue=xxx đã chạy chưa"""
    for proc in psutil.process_iter(attrs=["cmdline"]):
        try:
            cmdline = proc.info["cmdline"]
            if cmdline:  # Kiểm tra nếu cmdline không phải None
                cmdline_str = " ".join(cmdline)
                if f"--queue={queue_name}" in cmdline_str and "php" in cmdline_str:
                    return True
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess, TypeError):
            continue
    return False

def run_hidden(cmd, cwd=None):
    """Chạy lệnh nền (ẩn hoàn toàn, không mở cửa sổ mới)"""
    subprocess.Popen(
        cmd,
        cwd=cwd,
        shell=True,
        creationflags=CREATE_NO_WINDOW,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )

def is_port_open(host, port):
    """Check if a TCP port is open (for Redis or Laravel)"""
    try:
        with socket.create_connection((host, port), timeout=1):
            return True
    except OSError:
        return False

def wait_for_port(port, timeout=20):
    """Wait until a specific port is open"""
    start = time.time()
    while time.time() - start < timeout:
        if is_port_open("127.0.0.1", port):
            return True
        time.sleep(0.5)
    return False


def main():
    console.print(Panel("KHỞI ĐỘNG HỆ THỐNG IPMI LARAVEL (NỀN)", style="bold green"))

    summary = []


    # 0️⃣ WAMP
    console.print("[bold yellow]1. Khởi động WAMP Server[/bold yellow]")
    if not is_running("wampmanager.exe"):
        if os.path.exists(WAMP_EXE):
            console.print(" - WAMP chưa chạy → khởi động nền...")
            run_hidden(f'"{WAMP_EXE}"')
            time.sleep(8)
            if is_running("wampmanager.exe"):
                console.print(" - WAMP đã khởi động thành công.")
                summary.append(("WAMP Server", "Đã chạy mới"))
            else:
                console.print("[red] - WAMP không thể khởi động.[/red]")
                summary.append(("WAMP Server", "Lỗi khởi động"))
        else:
            console.print(f"[red] - Không tìm thấy file WAMP: {WAMP_EXE}[/red]")
            summary.append(("WAMP Server", "Thiếu file"))
    else:
        console.print(" - WAMP đang chạy sẵn.")
        summary.append(("WAMP Server", "Đã chạy nền"))

    # 1️⃣ Laravel Serve
    console.print("\n[bold yellow]2. Khởi động Laravel Server[/bold yellow]")
    if is_port_open("127.0.0.1", 8000):
        console.print(" - Laravel: đã chạy nền (port 8000).")
        summary.append(("Laravel Serve", "Đã chạy nền"))
    else:
        console.print(" - Laravel: đang khởi động php artisan serve...")
        run_hidden("php artisan serve", cwd=PROJECT_DIR)
        if wait_for_port(8000, 25):
            console.print(" - Laravel server đã sẵn sàng.")
            summary.append(("Laravel Serve", "Đã chạy mới"))
        else:
            console.print("[red] - Không thể mở cổng 8000 sau 25 giây.[/red]")
            summary.append(("Laravel Serve", "Lỗi khởi động"))

    webbrowser.open(WEB_URL)
    console.print(" - Đã mở trang web IPMI Dashboard.")

    # 1️⃣ Kiểm tra và khởi động Redis Server
    console.print("\n[bold yellow]3. Khởi động Redis[/bold yellow]")
    if not is_running("redis-server.exe"):
        if os.path.exists(REDIS_EXE):
            console.print(" - Redis chưa chạy → khởi động nền...")
            run_hidden("php artisan redis:run", cwd=PROJECT_DIR)
            time.sleep(5)
            if is_running("redis-server.exe"):
                console.print(" - Redis đã khởi động thành công.")
                summary.append(("Redis Server", "Đã chạy mới"))
            else:
                console.print("[red] - Lỗi: Redis không thể khởi động.[/red]")
                summary.append(("Redis Server", "Lỗi khởi động"))
                return
        else:
            console.print(f"[red] - Không tìm thấy file Redis: {REDIS_EXE}[/red]")
            summary.append(("Redis Server", "Thiếu file"))
            return
    else:
        console.print(" - Redis: [green]đã chạy nền[/green]")
        summary.append(("Redis Server", "Đã chạy nền"))


    # 2️⃣ Queue workers
    console.print("\n[bold yellow]4. Khởi động PHP queue[/bold yellow]")
    for queue in QUEUE_LIST:
        if is_queue_running(queue):
            console.print(f" - {queue}: [green]đã chạy nền[/green]")
            summary.append((queue, "Đã chạy nền"))
        else:
            console.print(f" - {queue}: [yellow]chưa chạy → khởi động nền...[/yellow]")
            run_hidden(f"php artisan queue:work --queue={queue}", cwd=PROJECT_DIR)
            summary.append((queue, "Đã chạy mới"))
            time.sleep(1)




    # 3️⃣ Tổng kết
    table = Table(title="TỔNG KẾT KHỞI ĐỘNG", show_header=True, header_style="bold cyan")
    table.add_column("Tiến trình", style="bold white")
    table.add_column("Trạng thái", justify="center")

    for name, status in summary:
        color = "green" if "Đang" in status or "Đã" in status else "red"
        table.add_row(name, f"[{color}]{status}[/{color}]")

    console.print("\n")
    console.print(table)
    console.print(Panel("TẤT CẢ CÁC TIẾN TRÌNH ĐÃ CHẠY NỀN THÀNH CÔNG", style="bold green"))
    console.print("Bạn có thể đóng cửa sổ này, hệ thống vẫn tiếp tục hoạt động nền.")


if __name__ == "__main__":
    main()