// Configuration
const GRID_COLS = 20;
const GRID_ROWS = 8;
const TOTAL_CELLS = GRID_COLS * GRID_ROWS;

// DOM Elements
const grid = document.getElementById('ipmi-grid');
const powerModal = document.getElementById('powerModal');
const btnCloseModal = document.getElementById('btnCloseModal');
const btnPowerOn = document.getElementById('btnPowerOn');
const btnPowerOff = document.getElementById('btnPowerOff');
const btnPowerReset = document.getElementById('btnPowerReset');

// State
let currentServerId = null;
let serverData = [];

// CSRF helper
function getCsrfToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') : '';
}


// Fetch server data song song
async function fetchServerData() {
    try {
        // Gọi 2 API song song
        const [resSensors, resStatuses] = await Promise.all([
            fetch('/api/sensors'),
            fetch('/api/statuses')
        ]);

        // Đọc JSON song song
        const [sensorsResult, statusesResult] = await Promise.all([
            resSensors.json(),
            resStatuses.json()
        ]);

        if (sensorsResult.success && statusesResult.success) {
            const combinedData = sensorsResult.data.map(sensor => {
                const match = statusesResult.data.find(s => s.ip === sensor.ip);
                const power = (match?.power ?? match?.data?.power ?? match?.cpu1_fan?.power) ?? 'N/A';
                return {
                    ...sensor,
                    status: match ? match.status : 'unknown',
                    power
                };
            });

            serverData = combinedData;
            updateGrid();
        } else {
            console.error('Invalid data format:', sensorsResult, statusesResult);
            serverData = [];
            updateGrid();
        }
    } catch (error) {
        console.error('Error fetching server data:', error);
        serverData = [];
        updateGrid();
    }
}

// Update grid with server data
function updateGrid() {
    grid.innerHTML = '';

    // Render only the servers we actually have from backend
    for (let i = 0; i < serverData.length; i++) {
        const serverId = `server-${i + 1}`;
        const col = (i % GRID_COLS) + 1;
        const row = Math.floor(i / GRID_COLS) + 1;

        const server = serverData[i] || {};
        const metrics = {
            cpu0Temp: server.cpu0_temp ?? 'N/A',
            cpu1Temp: server.cpu1_temp ?? 'N/A',
            cpu0Fan: server.cpu0_fan ?? 'N/A',
            cpu1Fan: server.cpu1_fan ?? 'N/A',
            status: server.status ?? 'unknown'
        };
        console.log('======');
        console.log(metrics);

        const serverElement = createServerElement(serverId, {
            name: server.name || `Server ${i + 1}`,
            ip: server.ip || null,
            position: { row, col },
            status: metrics.status,
            power: server.power,
            metrics
        });

        // Optional: if your CSS uses CSS Grid positioning, set it here
        // serverElement.style.gridColumn = col;
        // serverElement.style.gridRow = row;

        grid.appendChild(serverElement);
    }
}

// Create a server cell element
function createServerElement(id, data) {
    const { name, position, metrics } = data;
    const { cpu0Temp, cpu1Temp, cpu0Fan, cpu1Fan } = metrics;

    // Determine status classes
    const tempStatus = getTemperatureStatus(Math.max(cpu0Temp, cpu1Temp));
    const fanStatus = getFanStatus(Math.min(cpu0Fan, cpu1Fan));

    // Create the server cell
    const serverElement = document.createElement('div');
    serverElement.className = `server-cell ${tempStatus} ${fanStatus}`;
    serverElement.dataset.id = id;
    serverElement.dataset.ip = data.ip || '';
    serverElement.dataset.name = name || '';

    // Server content - simplified to show server name, temperatures and fan speeds
    const serverNumber = parseInt(id.replace('server-', ''));
    const serverName = data.name || `Server ${serverNumber}`;
    const power = data.power ?? 'N/A';
    const powerLabel = power === 'on' ? 'control: on' : power === 'off' ? 'control: off' : 'control: none';
    const powerClass = power === 'on' ? 'online' : power === 'off' ? 'offline' : 'danger';
    serverElement.innerHTML = `
        <div class="flex justify-between items-center">
            <h3 class="text-xl font-bold" title="${data.ip || 'N/A'}">${serverName}</h3>
            <div style="display: flex; gap: 10px;">
            <button class="detail-button ml-2" data-server="${id}" title="Details">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z" />
                </svg>
            </button>
            <button class="power-button ${power === 'on' ? 'text-green-500 hover:text-green-600' : power === 'off' ? 'text-gray-400 hover:text-gray-500' : 'text-red-500 hover:text-red-600'}" data-server="${id}" title="Power Control">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    ${power === 'on' || power === 'off'
            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />'
            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />'}
                </svg>
            </button>
            </div>

        </div>


        <div class="mt-2 space-y-1 text-sm">

            <div class="flex items-center justify-between">
                <span>CPU0: ${cpu0Temp}${typeof cpu0Temp === 'number' ? '°C' : ''}</span>
                ${typeof cpu0Temp === 'number' ? `<span class="status-indicator ${getStatusClass('temp', cpu0Temp)}"></span>` : ''}
            </div>
            <div class="flex items-center justify-between">
                <span>CPU1: ${cpu1Temp}${typeof cpu1Temp === 'number' ? '°C' : ''}</span>
                ${typeof cpu1Temp === 'number' ? `<span class="status-indicator ${getStatusClass('temp', cpu1Temp)}"></span>` : ''}
            </div>
            <div class="flex items-center justify-between">
                <span>FAN0: ${cpu0Fan}${typeof cpu0Fan === 'number' ? ' RPM' : ''}</span>
                ${typeof cpu0Fan === 'number' ? `<span class="status-indicator ${getStatusClass('fan', cpu0Fan)}"></span>` : ''}
            </div>
            <div class="flex items-center justify-between">
                <span>FAN1: ${cpu1Fan}${typeof cpu1Fan === 'number' ? ' RPM' : ''}</span>
                ${typeof cpu1Fan === 'number' ? `<span class="status-indicator ${getStatusClass('fan', cpu1Fan)}"></span>` : ''}
            </div>
        </div>

        <div class="flex items-center justify-between">
            <small class="font-semibold ${powerClass === 'online' ? 'text-green-500' : powerClass === 'offline' ? 'text-gray-400' : 'text-red-500'}">${data.ip}</small>
            <div class="flex items-center">
                <svg style="scale:1.4; margin:0;padding:0; margin-right:6px" class="h-3.5 w-3.5 ${powerClass === 'online' ? 'text-green-500' : powerClass === 'offline' ? 'text-gray-400' : 'text-red-500'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    ${power === 'on' || power === 'off'
            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20v-4m4 4v-8m4 8v-12m4 12v-16" />'
            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />'}
                </svg>
            </div>
        </div>
        <div class="power-options">

            <div class="flex justify-between items-center mb-2">
                <h2 class="font-medium m-0">Power ${serverName}</h2>

                <button class="close-button" data-action="cancel" title="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>


            <div class="space-y-2">
                <a href="/api/ipmi/power/${data.ip}/reset"
                class="w-full bg-yellow-500 hover:bg-yellow-600 text-white py-1.5 px-3 rounded text-sm block text-center">
                    Reset máy
                </a>

                <a href="/api/ipmi/power/${data.ip}/on"
                class="w-full bg-green-500 hover:bg-green-600 text-white py-1.5 px-3 rounded text-sm block text-center">
                    Bật máy
                </a>

                <a href="/api/ipmi/power/${data.ip}/off"
                class="w-full bg-red-500 hover:bg-red-600 text-white py-1.5 px-3 rounded text-sm block text-center">
                    Tắt máy
                </a>

            </div>
                <small class="font-semibold pt-2 ${powerClass === 'online' ? 'text-green-500' : powerClass === 'offline' ? 'text-gray-400' : 'text-red-500'}">${data.ip}</small>

        </div>
    `;

    return serverElement;
}

// Helper functions
function getTemperatureStatus(temp) {
    if (temp > 75) return 'critical';
    if (temp > 70) return 'high-temp';
    return '';
}

function getFanStatus(fanSpeed) {
    if (fanSpeed < 1040) return 'low-fan';
    return '';
}

function getStatusClass(type, value) {
    if (type === 'temp') {
        if (value > 75) return 'status-critical';
        if (value > 70) return 'status-warning';
        return 'status-ok';
    } else if (type === 'fan') {
        if (value < 1040) return 'status-warning';
        return 'status-ok';
    }
    return '';
}

// Event Handlers
function handlePowerButtonClick(e) {
    e.stopPropagation(); // Prevent event bubbling

    const button = e.target.closest('.power-button');
    if (!button) return;

    // Close any open power options first
    document.querySelectorAll('.server-cell').forEach(el => {
        if (el !== button.closest('.server-cell')) {
            el.classList.remove('show-options');
        }
    });

    // Toggle the clicked server's power options
    const serverElement = button.closest('.server-cell');
    if (serverElement) {
        serverElement.classList.toggle('show-options');
    }
}

async function handleDetailClick(e) {
    e.stopPropagation();
    const serverElement = e.target.closest('.server-cell');
    if (!serverElement) return;
    const ip = serverElement.dataset.ip;
    const name = serverElement.dataset.name || serverElement.dataset.id;
    if (!ip) {
        alert('Missing host IP for this server.');
        return;
    }
    if (confirm(`Xem chi tiết sensor của ${name} (${ip})?`)) {
        try {
            const res = await fetch(`/api/redis/sensor/${ip}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                }
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'ok') {
                alert(`Fetch failed: ${data.message || 'Unknown error'}`);
            } else {
                const out = typeof data.output === 'string' ? data.output : JSON.stringify(data, null, 2);
                alert(`Details for ${name} (${ip}):\n\n${out}`);
                console.log('Sensor details response:', data);
            }
        } catch (err) {
            console.error('Detail fetch error', err);
            alert('Failed to load details.');
        }
    }
}

async function handlePowerAction(e) {
    const button = e.target.closest('[data-action]');
    if (!button) return;

    const action = button.dataset.action;
    const serverElement = e.target.closest('.server-cell');

    if (action === 'cancel') {
        serverElement.classList.remove('show-options');
        return;
    }

    const serverId = serverElement.dataset.id;
    const ip = serverElement.dataset.ip;
    const name = serverElement.dataset.name || serverId;
    const actionLabel = {
        'power-on': 'Power On',
        'power-off': 'Power Off',
        'reset': 'Reset'
    };
    const actionValue = {
        'power-on': 'on',
        'power-off': 'off',
        'reset': 'reset'
    }[action];

    if (!ip) {
        alert('Missing host IP for this server.');
        return;
    }


    // Nơi mấy cái lệnh power thực thi
    if (confirm(`Are you sure you want to ${actionLabel[action]} ${name} (${ip})?`)) {
        try {

            // Sợ lỗi nên không chơi power trong này
            const res = await fetch(`/api/redis/sensor/${ip}`, {
                method: 'GET', // route của bạn là GET nên đổi lại
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                }
            });

            const data = await res.json();

            if (!res.ok || data.status !== 'ok') {
                alert(`Power command failed: ${data.message || 'Unknown error'}`);
            } else {
                // Thay đổi giao diện theo trạng thái
                if (action === 'power-off') {
                    serverElement.classList.add('bg-gray-100');
                } else if (action === 'power-on') {
                    serverElement.classList.remove('bg-gray-100');
                }

                // Hiển thị dữ liệu sensor từ API
                console.log(`${name} Sensor data:`, data);
                alert(`✅ ${data.ip} - CPU0: ${data.output}°C, FAN0: ${data.status} RPM`);
            }
        } catch (err) {
            console.error('Power command error', err);
            alert('Failed to send power command.');
        }

    }

    // Close the power options
    serverElement.classList.remove('show-options');
}

// Highlight servers based on search term (searches by IPMI name)
function highlightServers(searchTerm) {
    const serverElements = document.querySelectorAll('.server-cell');
    let firstMatch = null;
    const searchLower = searchTerm.toLowerCase();

    serverElements.forEach(server => {
        // Get server name from the h3 element inside the server cell
        const serverNameElement = server.querySelector('h3');
        const serverName = serverNameElement ? serverNameElement.textContent.trim().toLowerCase() : '';
        const serverIp = server.dataset.ip || '';

        // Check if server name starts with the search term (case insensitive)
        // Only check IP if search term contains a dot (.)
        const isMatch = searchTerm !== '' &&
            (serverName.startsWith(searchLower) ||
                (searchTerm.includes('.') && serverIp.includes(searchTerm)));

        // Toggle highlight class
        if (isMatch) {
            server.classList.add('highlighted');
            // Store the first match for scrolling
            if (!firstMatch) {
                firstMatch = server;
            }
        } else {
            server.classList.remove('highlighted');
        }
    });
}

// Refresh data every 12 seconds
setInterval(fetchServerData, 12000);

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    // Initialize the grid with server data
    fetchServerData();

    // Add search functionality
    const searchInput = document.getElementById('serverSearch');
    if (searchInput) {
        let searchTimeout;

        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.trim();

            // Clear previous timeout
            clearTimeout(searchTimeout);

            // Set a new timeout to avoid too many re-renders
            searchTimeout = setTimeout(() => {
                if (searchTerm === '') {
                    // Clear all highlights if search is empty
                    document.querySelectorAll('.server-cell').forEach(el => {
                        el.classList.remove('highlighted');
                    });
                    // Hide search count when search is empty
                    const searchCountContainer = document.querySelector('[data-search-count]')?.closest('small');
                    if (searchCountContainer) {
                        searchCountContainer.style.display = 'none';
                    }
                } else {
                    highlightServers(searchTerm);
                    // Scroll to the first matching server with offset
                    const matchedServers = document.querySelectorAll('.server-cell.highlighted');
                    const matchCount = matchedServers.length;
                    
                    // Update search count
                    const searchCountElement = document.querySelector('[data-search-count]');
                    const searchCountContainer = searchCountElement?.closest('small');
                    
                    if (searchCountElement) {
                        searchCountElement.textContent = matchCount;
                        // Show/hide based on search term and matches
                        if (searchTerm && searchCountContainer) {
                            searchCountContainer.style.display = 'inline';
                        } else if (searchCountContainer) {
                            searchCountContainer.style.display = 'none';
                        }
                    }
                    
                    // Scroll to first match if any
                    if (matchedServers.length > 0) {
                        const yOffset = -220; // Adjust this value as needed
                        const y = matchedServers[0].getBoundingClientRect().top + window.pageYOffset + yOffset;
                        window.scrollTo({ top: y, behavior: 'smooth' });
                    }
                }
            }, 200); // 200ms debounce
        });

        // Clear highlights when clicking the clear button (if present)
        searchInput.addEventListener('search', () => {
            if (searchInput.value === '') {
                document.querySelectorAll('.server-cell').forEach(el => {
                    el.classList.remove('highlighted');
                });
            }
        });
    }

    // Event delegation for all clicks
    document.addEventListener('click', (e) => {
        // Handle close button click
        if (e.target.closest('.close-button') || e.target.closest('[data-action="cancel"]')) {
            const serverCell = e.target.closest('.server-cell');
            if (serverCell) {
                serverCell.classList.remove('show-options');
            }
            return;
        }

        // Handle power button click
        if (e.target.closest('.power-button')) {
            handlePowerButtonClick(e);
            return;
        }

        // Handle detail button click
        if (e.target.closest('.detail-button')) {
            handleDetailClick(e);
            return;
        }

        // Click outside - close all power options
        if (!e.target.closest('.power-options') && !e.target.closest('.power-button')) {
            document.querySelectorAll('.server-cell').forEach(el => {
                el.classList.remove('show-options');
            });
        }
    });

    // Close power options when pressing Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.server-cell').forEach(el => {
                el.classList.remove('show-options');
            });
        }
    });
});

// Export for testing if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        createServerElement,
        getTemperatureStatus,
        getFanStatus,
        getStatusClass,
        fetchServerData,
        updateGrid,
        highlightServers,
        handlePowerAction,
        handlePowerButtonClick,
        handleDetailClick
    };
}
