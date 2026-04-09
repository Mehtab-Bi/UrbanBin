<?php
session_start();

// SECURITY: Only Operator or Admin can access
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true ||
   !in_array($_SESSION['role'], ['operator','admin'])) {
    header("Location: login_selection.php");
    exit;
}

// DB Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch bin list
$sql = "SELECT bin_id, location, capacity_percent, hygiene_status, last_updated FROM bins ORDER BY bin_id ASC";
$result = $conn->query($sql);
$bins = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bins[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sensor Simulator</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-6">

    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-purple-700">Sensor Simulator</h1>

        <a href="operator_dashboard.php"
           class="px-4 py-2 bg-gray-700 text-white rounded-lg shadow hover:bg-gray-800 transition">
            ← Back to Dashboard
        </a>
    </div>

    <!-- MASTER CONTROL -->
    <div class="bg-white p-6 rounded-xl shadow border mb-8">
        <h2 class="text-xl font-semibold text-purple-600 mb-4">Manual Sensor Update</h2>

        <form id="manualForm" class="grid grid-cols-1 md:grid-cols-4 gap-4">

            <!-- BIN SELECT -->
            <div>
                <label class="block text-sm font-medium mb-1">Select Bin</label>
                <select id="sim_bin" class="w-full border rounded-lg p-2">
                    <?php foreach ($bins as $bin): ?>
                        <option value="<?= $bin['bin_id'] ?>">
                            <?= $bin['bin_id'] ?> (<?= $bin['location'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- CAPACITY -->
            <div>
                <label class="block text-sm font-medium mb-1">
                    Capacity %: <span id="cap_label" class="font-bold">50</span>%
                </label>
                <input type="range" id="sim_capacity" min="0" max="100" value="50"
                       class="w-full" oninput="cap_label.textContent=this.value">
            </div>

            <!-- Ammonia -->
            <div>
                <label class="block text-sm font-medium mb-1">Ammonia (PPM)</label>
                <input type="number" step="0.1" id="sim_ammonia" value="0.5"
                       class="w-full border rounded-lg p-2">
            </div>

            <!-- CO2 -->
            <div>
                <label class="block text-sm font-medium mb-1">CO2 (PPM)</label>
                <input type="number" step="10" id="sim_co2" value="450"
                       class="w-full border rounded-lg p-2">
            </div>
        </form>

        <button onclick="sendManualUpdate()"
                class="mt-4 px-6 py-3 bg-purple-600 text-white font-semibold rounded-lg shadow hover:bg-purple-700 transition">
            Send Update
        </button>
    </div>

    <!-- AUTO MODE -->
    <div class="bg-white p-6 rounded-xl shadow border mb-8">
        <h2 class="text-xl font-semibold text-purple-600 mb-2">Auto Mode</h2>

        <button id="autoBtn"
            class="px-6 py-3 bg-green-600 text-white rounded-lg shadow hover:bg-green-700 transition">
            Start Auto Mode
        </button>

        <p id="autoStatus" class="text-gray-600 mt-2">Manual Mode Active</p>
    </div>

    <!-- LOG -->
    <div class="bg-black text-white p-4 rounded-xl h-64 overflow-y-scroll text-sm">
        <p class="font-bold text-purple-300">Simulation Log</p>
        <div id="log"></div>
    </div>

<script>
const API_URL = "api_update.php";
const bins = <?= json_encode($bins) ?>;

let autoRunning = false;
let interval = null;

// ----- Logging -----
function logMessage(msg, color="white") {
    const logDiv = document.getElementById("log");
    const p = document.createElement("p");
    p.style.color = color;
    p.innerHTML = `[${new Date().toLocaleTimeString()}] ${msg}`;
    logDiv.prepend(p);
}

// ----- Manual Update -----
function sendManualUpdate() {
    const payload = {
        bin_id: document.getElementById('sim_bin').value,
        capacity_percent: parseInt(document.getElementById('sim_capacity').value),
        ammonia: parseFloat(document.getElementById('sim_ammonia').value),
        co2: parseFloat(document.getElementById('sim_co2').value)
    };

    sendPayload(payload);
}

// ----- Auto Update -----
function randomData(binId) {
    return {
        bin_id: binId,
        capacity_percent: Math.floor(Math.random() * 100),
        ammonia: (Math.random() * 4).toFixed(2),
        co2: (400 + Math.random() * 2000).toFixed(2)
    };
}

function toggleAutoMode() {
    const btn = document.getElementById("autoBtn");
    const status = document.getElementById("autoStatus");

    if (!autoRunning) {
        autoRunning = true;
        btn.textContent = "Stop Auto Mode";
        btn.classList.remove("bg-green-600");
        btn.classList.add("bg-red-600");
        status.textContent = "Auto Mode Running…";

        interval = setInterval(() => {
            bins.forEach(bin => sendPayload(randomData(bin.bin_id)));
        }, 5000);

    } else {
        autoRunning = false;
        btn.textContent = "Start Auto Mode";
        btn.classList.remove("bg-red-600");
        btn.classList.add("bg-green-600");
        status.textContent = "Manual Mode Active";
        clearInterval(interval);
    }
}

document.getElementById("autoBtn").addEventListener("click", toggleAutoMode);

// ----- API Communication -----
async function sendPayload(payload) {
    logMessage(`Sending to ${payload.bin_id}…`, "yellow");

    try {
        const res = await fetch(API_URL, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        logMessage(`SUCCESS: ${payload.bin_id} → ${data.hygiene_status}`, "lightgreen");

    } catch (err) {
        logMessage(`ERROR: ${err.message}`, "red");
    }
}
</script>

</body>
</html>
