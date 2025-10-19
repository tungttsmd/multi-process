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

// Fetch server data
async function fetchServerData() {
    try {
        const response = await fetch('/api/sensors');
        const result = await response.json();

        if (result.success && Array.isArray(result.data)) {
            serverData = result.data;
            updateGrid();
        } else {
            console.error('Invalid data format:', result);
            // Initialize with empty data if the response is not in the expected format
            serverData = [];
            updateGrid();
        }
    } catch (error) {
        console.error('Error fetching server data:', error);
        // Initialize with empty data on error
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

        const serverElement = createServerElement(serverId, {
            name: server.name || `Server ${i + 1}`,
            ip: server.ip || null,
            position: { row, col },
            status: metrics.status,
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
    const serverNumber = parseInt(id.replace('server-', '')); // Get the 1-based server number
    const serverName = data.name || `Server ${serverNumber}`; // Use server name or fallback to number
    serverElement.innerHTML = `
        <div class="flex justify-between items-center">
            <h3 class="text-xl font-bold" title="${data.ip || 'N/A'}">${serverName}</h3>
            <button class="power-button" data-server="${id}" title="Power Control">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </button>
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

        <div class="power-options">
            <div class="flex justify-between items-center mb-2">
                <h4 class="font-medium">Power ${serverNumber}</h4>
                <button class="close-button" data-action="cancel" title="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="space-y-2">
                <button class="w-full bg-green-500 hover:bg-green-600 text-white py-1.5 px-3 rounded text-sm" data-action="power-on">
                    Power On
                </button>
                <button class="w-full bg-red-500 hover:bg-red-600 text-white py-1.5 px-3 rounded text-sm" data-action="power-off">
                    Power Off
                </button>
                <button class="w-full bg-yellow-500 hover:bg-yellow-600 text-white py-1.5 px-3 rounded text-sm" data-action="reset">
                    Reset
                </button>
            </div>
        </div>
    `;

    return serverElement;
}

// Helper functions
function getTemperatureStatus(temp) {
    if (temp >= 70) return 'critical';
    if (temp >= 60) return 'high-temp';
    return '';
}

function getFanStatus(fanSpeed) {
    if (fanSpeed < 2000) return 'low-fan';
    return '';
}

function getStatusClass(type, value) {
    if (type === 'temp') {
        if (value >= 70) return 'status-critical';
        if (value >= 60) return 'status-warning';
        return 'status-ok';
    } else if (type === 'fan') {
        if (value < 2000) return 'status-warning';
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

    if (confirm(`Are you sure you want to ${actionLabel[action]} ${name} (${ip})?`)) {
        try {
            const res = await fetch('/api/power', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({ action: actionValue, ip })
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                alert(`Power command failed: ${data.error || 'Unknown error'}`);
            } else {
                // Optionally reflect state in UI
                if (action === 'power-off') {
                    serverElement.classList.add('bg-gray-100');
                } else if (action === 'power-on') {
                    serverElement.classList.remove('bg-gray-100');
                }
            }
        } catch (err) {
            console.error('Power command error', err);
            alert('Failed to send power command.');
        }
    }

    // Close the power options
    serverElement.classList.remove('show-options');
}

// Highlight servers based on search term
function highlightServers(searchTerm) {
    const serverElements = document.querySelectorAll('.server-cell');
    let firstMatch = null;

    serverElements.forEach(server => {
        const serverNumber = server.dataset.id.replace('server-', '');

        // Check if server number starts with the search term
        const isMatch = searchTerm !== '' && serverNumber.startsWith(searchTerm);

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

// Refresh data every 5 seconds
setInterval(fetchServerData, 5000);

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
                } else {
                    highlightServers(searchTerm);
                    // Scroll to the first matching server
                    const firstMatch = document.querySelector('.server-cell.highlighted');
                    if (firstMatch) {
                        firstMatch.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

    // Event delegation for power buttons and actions
    document.addEventListener('click', (e) => {
        // Handle power button click
        if (e.target.closest('.power-button')) {
            handlePowerButtonClick(e);
            return;
        }

        // Handle power actions (on/off/reset/cancel)
        const actionButton = e.target.closest('[data-action]');
        if (actionButton) {
            handlePowerAction(e);
            return;
        }

        // Click outside any server cell or power options - close all power options
        const clickedOnServerCell = e.target.closest('.server-cell');
        if (!clickedOnServerCell) {
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
        initializeGrid,
        createServerElement,
        getTemperatureStatus,
        getFanStatus,
        getStatusClass
    };
}
