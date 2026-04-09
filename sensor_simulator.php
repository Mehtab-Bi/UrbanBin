<?php
session_start();
// Include the database connection setup to fetch bin list
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db"; // Ensure this matches your database name

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all bins to populate the simulator list
$sql = "SELECT bin_id, location, capacity_percent, last_updated FROM bins ORDER BY bin_id ASC";
$result = $conn->query($sql);
$bins = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $bins[] = $row;
    }
}
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Bin Sensor Simulator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f0f4f8; }
        .control-card { transition: all 0.2s ease-in-out; border-radius: 12px; }
        .control-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .gauge {
            width: 100%;
            height: 10px;
            background-color: #e5e7eb;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        .gauge-fill {
            height: 100%;
            transition: width 0.3s ease-in-out, background-color 0.3s ease-in-out;
            border-radius: 5px;
        }
        .status-box {
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 0.875rem; /* text-sm */
            font-weight: 700; /* font-bold */
            text-align: center;
            display: inline-block;
        }
        .log-entry {
            padding: 4px 0;
            border-bottom: 1px dashed #374151;
        }
        .log-entry:last-child {
            border-bottom: none;
        }
        .range-input {
            height: 6px;
            -webkit-appearance: none;
            width: 100%;
            background: #d1d5db; /* gray-300 */
            border-radius: 3px;
            cursor: pointer;
        }
        .range-input::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #4f46e5; /* indigo-600 */
            cursor: pointer;
            box-shadow: 0 0 5px rgba(0,0,0,0.2);
            transition: background 0.3s;
        }
    </style>
</head>
<body class="p-8">

    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Smart Bin Sensor Simulator</h1>
        <p class="text-gray-500 mb-8">Send capacity and gas level data to your bins in real-time.</p>

        <!-- MASTER MANUAL CONTROL FORM -->
        <div class="bg-white p-6 rounded-xl shadow-lg mb-8 border border-indigo-200">
            <h2 class="text-xl font-bold text-indigo-700 mb-4 border-b pb-2">Master Manual Control</h2>
            <form id="masterSendForm" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <!-- Bin Selector -->
                <div>
                    <label for="master_bin_id" class="block text-sm font-medium text-gray-700 mb-1">Select Bin</label>
                    <select id="master_bin_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        <?php foreach ($bins as $bin): ?>
                            <option value="<?php echo $bin['bin_id']; ?>"><?php echo $bin['bin_id']; ?> (<?php echo $bin['location']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Capacity -->
                <div class="col-span-1 md:col-span-2">
                    <label for="master_capacity" class="block text-sm font-medium text-gray-700 mb-1">Capacity %: <span id="capacity_label" class="font-bold text-indigo-600">50</span>%</label>
                    <input type="range" id="master_capacity" min="0" max="100" value="50" class="range-input" oninput="document.getElementById('capacity_label').textContent=this.value">
                </div>
                <!-- Ammonia -->
                <div>
                    <label for="master_ammonia" class="block text-sm font-medium text-gray-700 mb-1">Ammonia (PPM)</label>
                    <input type="number" step="0.1" id="master_ammonia" value="0.5" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <!-- CO2 -->
                <div>
                    <label for="master_co2" class="block text-sm font-medium text-gray-700 mb-1">CO2 (PPM)</label>
                    <input type="number" step="10" id="master_co2" value="450" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <!-- Submit -->
                <button type="button" onclick="sendMasterManualUpdate()" class="md:col-span-1 w-full py-2.5 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Send Data
                </button>
            </form>
        </div>
        
        <!-- AUTO MODE CONTROLS -->
        <div class="flex space-x-4 mb-8 items-center justify-between p-4 bg-white rounded-xl shadow-lg border border-gray-200">
             <div class="flex items-center space-x-4">
                <button id="autoModeToggle" class="px-6 py-3 rounded-full shadow-lg font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition duration-300 transform hover:scale-105">
                    Start Auto Mode (Sending Data)
                </button>
                <span id="statusMessage" class="text-sm font-medium text-gray-600">Manual Mode Active.</span>
            </div>
            
            <p class="text-xs text-gray-400">Auto mode sends random data every 5 seconds to all bins.</p>
        </div>


        <!-- LOG CONTAINER -->
        <div id="logContainer" class="bg-gray-800 text-white p-6 rounded-xl shadow-inner mb-8 h-64 overflow-y-scroll text-sm">
            <p class="font-bold text-lg border-b border-gray-600 pb-2 mb-2 text-indigo-400">Simulator Log</p>
            <p class="log-entry">API connection path verified (using proxy: `/server/HygieneApp/api_update.php`).</p>
        </div>

        <!-- BIN CARD DISPLAY (for visual feedback) -->
        <div id="binCards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($bins as $bin): ?>
            <div id="card-<?php echo $bin['bin_id']; ?>" class="control-card bg-white p-6 rounded-xl shadow-lg border-2 border-gray-200">
                <div class="flex justify-between items-start mb-4">
                    <h2 class="text-2xl font-extrabold text-gray-900"><?php echo $bin['bin_id']; ?></h2>
                    <span class="text-sm text-gray-600 bg-gray-100 px-3 py-1 rounded-full font-medium"><?php echo $bin['location']; ?></span>
                </div>
                
                <div class="mb-4">
                    <p class="text-lg font-medium text-gray-700 flex justify-between items-center">
                        Fill Level: <span id="capacity-<?php echo $bin['bin_id']; ?>" class="font-extrabold text-indigo-600 text-xl"><?php echo $bin['capacity_percent']; ?>%</span>
                    </p>
                    <div class="gauge mt-2">
                        <div id="gauge-<?php echo $bin['bin_id']; ?>" class="gauge-fill" style="width: <?php echo $bin['capacity_percent']; ?>%;"></div>
                    </div>
                </div>

                <div class="space-y-3 text-base pt-3 border-t border-gray-100">
                    <p class="text-gray-600 flex justify-between">
                        CO2 (PPM): <span id="co2-<?php echo $bin['bin_id']; ?>" class="font-semibold text-gray-800">0.00</span>
                    </p>
                    <p class="text-gray-600 flex justify-between">
                        Ammonia (PPM): <span id="ammonia-<?php echo $bin['bin_id']; ?>" class="font-semibold text-gray-800">0.00</span>
                    </p>
                    <div class="flex justify-between items-center pt-2">
                        <p class="text-lg font-bold text-gray-900">Status:</p>
                        <span id="status-<?php echo $bin['bin_id']; ?>" class="status-box bg-gray-200 text-gray-800">Initial</span>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    </div>


    <script>
        const API_URL = 'api_update.php'; // Use local path since you are testing in XAMPP/local host
        const logContainer = document.getElementById('logContainer');
        const statusMessage = document.getElementById('statusMessage');
        const autoModeToggle = document.getElementById('autoModeToggle');
        const bins = <?php echo json_encode($bins); ?>;

        let autoUpdateInterval = null;
        let isAutoMode = false;
        
        // New Global State for tracking gas levels (to simulate accumulation)
        const binGasState = {};
        // Initialize state based on available bins
        bins.forEach(bin => {
            binGasState[bin.bin_id] = { co2: 450, ammonia: 0.1 }; // Initial low levels
        });

        // --- Utility Functions ---

        function log(message, type = 'info') {
            const p = document.createElement('p');
            let color = 'text-gray-400';
            if (type === 'success') color = 'text-green-400';
            if (type === 'error') color = 'text-red-400';
            if (type === 'warning') color = 'text-yellow-400';

            p.className = `${color} text-xs log-entry`;
            p.innerHTML = `[${new Date().toLocaleTimeString()}] **[${type.toUpperCase()}]** ${message}`;

            // Prepend new log message
            logContainer.querySelector('.font-bold').after(p);
            // Auto-scroll to the top of the log
            logContainer.scrollTop = 0;
        }

        function updateCard(binId, capacity, co2, ammonia, status) {
            const capElement = document.getElementById(`capacity-${binId}`);
            const gaugeFill = document.getElementById(`gauge-${binId}`);
            const co2Element = document.getElementById(`co2-${binId}`);
            const ammoniaElement = document.getElementById(`ammonia-${binId}`);
            const statusElement = document.getElementById(`status-${binId}`);
            const card = document.getElementById(`card-${binId}`);

            if (!capElement) return;

            // Update fill level
            capElement.textContent = `${capacity}%`;
            gaugeFill.style.width = `${capacity}%`;
            
            // --- Aesthetics and Colors ---
            
            // Gauge color
            if (capacity > 90) {
                gaugeFill.style.backgroundColor = '#dc2626'; // Red
            } else if (capacity > 70) {
                gaugeFill.style.backgroundColor = '#f59e0b'; // Amber
            } else {
                gaugeFill.style.backgroundColor = '#10b981'; // Green
            }

            // Update gas readings
            co2Element.textContent = co2.toFixed(2);
            ammoniaElement.textContent = ammonia.toFixed(2);

            // Update hygiene status
            statusElement.textContent = status;
            statusElement.className = 'status-box';
            
            // Card Border and Status Box Styling
            card.classList.remove('border-gray-200', 'border-red-500', 'border-amber-500', 'border-green-500', 'border-blue-500');
            statusElement.classList.remove('bg-gray-200', 'text-gray-800', 'bg-red-200', 'text-red-800', 'bg-yellow-200', 'text-yellow-800', 'bg-green-200', 'text-green-800', 'bg-blue-200', 'text-blue-800');

            if (status.includes('Alert') || status.includes('Immediate')) {
                statusElement.classList.add('bg-red-200', 'text-red-800');
                card.classList.add('border-red-500');
            } else if (status.includes('Recommended') || status.includes('Ventilation') || status.includes('Service Soon')) {
                 statusElement.classList.add('bg-yellow-200', 'text-yellow-800');
                 card.classList.add('border-amber-500');
            } else if (status.includes('Normal')) {
                statusElement.classList.add('bg-green-200', 'text-green-800');
                card.classList.add('border-green-500');
            } else { // Fallback for 'Initial' or other states
                 statusElement.classList.add('bg-blue-200', 'text-blue-800');
                 card.classList.add('border-blue-500');
            }
        }

        // --- Data Generation ---

        function generateRandomData(binId) {
            // -----------------------------------------------------------
            // 1. Capacity (slow random walk - remains the same)
            // -----------------------------------------------------------
            const currentCapacityEl = document.getElementById(`capacity-${binId}`);
            let currentCapacity = currentCapacityEl ? parseFloat(currentCapacityEl.textContent.replace('%', '')) : 50;
            
            const capacityChange = Math.random() * 3 - 1.5; // Change between -1.5 and +1.5
            let newCapacity = Math.max(0, Math.min(100, currentCapacity + capacityChange));
            newCapacity = Math.round(newCapacity);

            // -----------------------------------------------------------
            // 2. CO2 and Ammonia (Simulate accumulation/decay)
            // -----------------------------------------------------------
            let currentState = binGasState[binId];

            // Determine base accumulation rate (higher if capacity is high, but always exists)
            const capacityFactor = newCapacity / 100; // 0.0 to 1.0

            // Small random accumulation (always positive baseline)
            let co2Accumulation = 5 + (Math.random() * 15) * capacityFactor; // Base 5, plus up to 15 * capacityFactor
            let ammoniaAccumulation = 0.01 + (Math.random() * 0.05) * capacityFactor; // Base 0.01, plus up to 0.05 * capacityFactor

            // Decay/Normalization: If capacity is very low, allow slight decay
            if (newCapacity < 20) {
                co2Accumulation -= 10; // Can make CO2 drop slightly
                ammoniaAccumulation -= 0.02; // Can make Ammonia drop slightly
            }
            
            // Weighted spike factor (e.g., BIN002 is high-risk/high-traffic area)
            if (binId === 'BIN002' && Math.random() < 0.2) { 
                co2Accumulation += 50 + Math.random() * 100;
                ammoniaAccumulation += 0.5 + Math.random() * 1.5; 
            }
            
            // Apply changes, ensuring minimum safe levels are maintained
            let newCo2 = Math.max(400, currentState.co2 + co2Accumulation);
            let newAmmonia = Math.max(0.01, currentState.ammonia + ammoniaAccumulation);

            // Hard limits to prevent runaway values
            newCo2 = Math.min(5000, newCo2);
            newAmmonia = Math.min(6.0, newAmmonia);

            // Update state for the next run
            binGasState[binId] = { co2: newCo2, ammonia: newAmmonia };

            return {
                bin_id: binId,
                capacity_percent: newCapacity,
                co2: parseFloat(newCo2.toFixed(2)),
                ammonia: parseFloat(newAmmonia.toFixed(2))
            };
        }
        
        // --- API Communication ---

        async function sendData(payload) {
            log(`Sending data for **${payload.bin_id}**: Cap=${payload.capacity_percent}%, CO2=${payload.co2}, NH3=${payload.ammonia}`, 'info');

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const contentType = response.headers.get("content-type");
                
                if (response.ok) {
                    if (contentType && contentType.includes("application/json")) {
                        const result = await response.json();
                        const status = result.hygiene_status || 'Data Saved';
                        log(`**${payload.bin_id}** updated successfully. Status: ${status}`, 'success');
                        updateCard(payload.bin_id, payload.capacity_percent, payload.co2, payload.ammonia, status);
                    } else {
                         const text = await response.text();
                         log(`**${payload.bin_id}** updated successfully (HTTP ${response.status}), but server returned unexpected format.`, 'warning');
                         updateCard(payload.bin_id, payload.capacity_percent, payload.co2, payload.ammonia, 'Data Saved (Check DB)');
                    }
                } else {
                    let error_message = `HTTP ${response.status} error`;
                    const responseText = await response.text();

                    try {
                        const errorResult = JSON.parse(responseText);
                        error_message = errorResult.message || `API Error: ${response.status}`;
                    } catch (e) {
                        error_message += `. Server returned non-JSON error. (Possible PHP error in api_update.php)`;
                    }
                    
                    log(`**${payload.bin_id}**: FAILED (${error_message})`, 'error');
                }
            } catch (error) {
                log(`**${payload.bin_id}**: FAILED (Network/Fetch Error: ${error.message})`, 'error');
            }
        }

        // --- Mode Control ---

        function runAutoUpdate() {
            if (isAutoMode) {
                bins.forEach(bin => {
                    const payload = generateRandomData(bin.bin_id);
                    sendData(payload);
                });
            }
        }
        
        function toggleAutoMode() {
            if (isAutoMode) {
                clearInterval(autoUpdateInterval);
                autoUpdateInterval = null;
                isAutoMode = false;
                autoModeToggle.textContent = 'Start Auto Mode (Sending Data)';
                autoModeToggle.classList.remove('bg-red-600', 'hover:bg-red-700');
                autoModeToggle.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
                statusMessage.textContent = 'Manual Mode Active.';
                log('Auto Mode stopped by user.', 'warning');
            } else {
                isAutoMode = true;
                autoModeToggle.textContent = 'Stop Auto Mode (Sending Data)';
                autoModeToggle.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                autoModeToggle.classList.add('bg-red-600', 'hover:bg-red-700');
                statusMessage.textContent = 'Auto Mode Running... Data sent every 5 seconds.';
                log('Auto Mode started. Sending data every 5 seconds.', 'success');
                
                // Run immediately and then every 5 seconds
                runAutoUpdate(); 
                autoUpdateInterval = setInterval(runAutoUpdate, 5000); 
            }
        }

        // --- Manual Mode Functions ---

        function sendMasterManualUpdate() {
            if (isAutoMode) {
                log('Cannot send manual data while Auto Mode is running. Please stop Auto Mode first.', 'error');
                return;
            }
            
            const binId = document.getElementById('master_bin_id').value;
            const capacity = document.getElementById('master_capacity').value;
            const ammonia = document.getElementById('master_ammonia').value;
            const co2 = document.getElementById('master_co2').value;

            // --- IMPORTANT: Update the internal state when manual mode is used ---
            binGasState[binId] = { 
                co2: parseFloat(co2), 
                ammonia: parseFloat(ammonia) 
            };
            // --------------------------------------------------------------------


            const payload = {
                bin_id: binId,
                capacity_percent: parseInt(capacity),
                ammonia: parseFloat(ammonia),
                co2: parseFloat(co2),
            };

            sendData(payload);
        }

        // --- Initialization ---

        window.onload = function() {
             // Attach event listener for the toggle button
            autoModeToggle.addEventListener('click', toggleAutoMode);
            
            // Initialize cards with current DB data
            bins.forEach(bin => {
                 // The PHP template only initializes capacity. Set gas to the initial state value.
                 updateCard(bin.bin_id, bin.capacity_percent, binGasState[bin.bin_id].co2, binGasState[bin.bin_id].ammonia, 'Initial'); 
            });
        };

    </script>
</body>
</html>
