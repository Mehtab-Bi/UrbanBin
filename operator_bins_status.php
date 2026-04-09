<?php
session_start();

// SECURITY: Only operator/admin can access
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

$sql = "SELECT * FROM bins ORDER BY bin_id ASC";
$res = $conn->query($sql);
$bins = [];

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $bins[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bin Status - Operator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-gray-100 p-6">
    <?php if (isset($_GET['serviced'])): ?>
    <div class="bg-green-100 text-green-700 p-3 mb-4 rounded shadow">
        Bin <b><?php echo htmlspecialchars($_GET['serviced']); ?></b> has been serviced successfully.
    </div>
<?php endif; ?>


    <!-- TOP NAV -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-purple-700">Bin Status</h1>

        <div class="flex items-center space-x-4 text-sm font-medium">

            <a href="operator_dashboard.php"
               class="px-3 py-1 rounded hover:bg-purple-100 transition">
               Dashboard
            </a>

            <a href="operator_bins_status.php"
               class="px-3 py-1 rounded bg-purple-200 text-purple-700">
               Bin Status
            </a>

            <a href="operator_sensor_simulator.php"
               class="px-3 py-1 rounded hover:bg-purple-100 transition">
               Sensor Simulator
            </a>

            <span class="text-gray-600">|</span>

            <span class="text-gray-700">
                User: <b><?php echo htmlspecialchars($_SESSION['username']); ?></b>
            </span>

            <a href="logout.php"
               class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition">
               Logout
            </a>
        </div>
    </div>

    <!-- GRID OF BIN CARDS -->
    <div id="binGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

        <?php foreach ($bins as $bin): ?>

            <?php
                // Calculate minutes ago
                $last = strtotime($bin['last_updated']);
                $diffMin = ($last) ? floor((time() - $last)/60) : null;

                // CARD COLOR BASED ON hygiene_status
                $status = $bin['hygiene_status'];
                $cardColor = "border-gray-300";
                if (str_contains($status, "Immediate") || str_contains($status, "Alert")) {
                    $cardColor = "border-red-500";
                } elseif (str_contains($status, "Recommended") ||
                          str_contains($status, "Ventilation") ||
                          str_contains($status, "Service Soon")) {
                    $cardColor = "border-yellow-500";
                } else {
                    $cardColor = "border-green-500";
                }

                // Gauge color
                $cap = (int)$bin['capacity_percent'];
                $gaugeColor = "#10b981"; // green
                if ($cap >= 95) $gaugeColor = "#dc2626"; // red
                elseif ($cap >= 75) $gaugeColor = "#f59e0b"; // amber
            ?>

            <div id="card-<?php echo $bin['bin_id']; ?>"
                 class="bg-white p-5 rounded-xl shadow border-2 <?php echo $cardColor; ?>">

                <!-- HEADER ROW -->
                <div class="flex justify-between items-center mb-2">
                    <h2 class="text-xl font-bold"><?php echo $bin['bin_id']; ?></h2>

                    <div class="flex space-x-2">

                        <!-- REFRESH ICON -->
                        <button onclick="refreshCard('<?php echo $bin['bin_id']; ?>')"
                                class="p-1 rounded hover:bg-gray-100">
                            <i data-lucide="refresh-ccw" class="w-5 h-5 text-gray-700"></i>
                        </button>

                        <!-- SINGLE UPDATE ICON -->
                        <button onclick="openUpdateModal('<?php echo $bin['bin_id']; ?>')"
                                class="p-1 rounded hover:bg-gray-100">
                            <i data-lucide="plus" class="w-5 h-5 text-purple-600"></i>
                        </button>
                        <!-- SERVICE BIN ICON -->
<form method="POST" action="operator_service_bin.php" onsubmit="return confirm('Mark this bin as serviced?');">
    <input type="hidden" name="bin_id" value="<?php echo $bin['bin_id']; ?>">
    <button class="p-1 rounded hover:bg-green-100" title="Mark as Serviced">
        <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
    </button>
</form>

                    </div>
                </div>

                <!-- LOCATION -->
                <p class="text-sm text-gray-600 mb-3">
                    <?php echo htmlspecialchars($bin['location']); ?>
                </p>

                <!-- FILL LEVEL -->
                <p class="text-sm font-semibold">
                    Fill Level:
                    <span id="captext-<?php echo $bin['bin_id']; ?>"
                          class="font-bold text-purple-700"><?php echo $bin['capacity_percent']; ?>%</span>
                </p>

                <div class="w-full h-3 bg-gray-200 rounded mt-1">
                    <div id="gauge-<?php echo $bin['bin_id']; ?>"
                         class="h-3 rounded"
                         style="width: <?php echo $bin['capacity_percent']; ?>%;
                                background: <?php echo $gaugeColor; ?>;">
                    </div>
                </div>

                <!-- STATUS -->
                <p class="mt-3 text-sm">
                    <b>Status:</b>
                    <span id="status-<?php echo $bin['bin_id']; ?>"
                          class="font-semibold">
                        <?php echo htmlspecialchars($bin['hygiene_status']); ?>
                    </span>
                </p>

                <!-- PREDICTION -->
                <p class="mt-1 text-sm text-gray-500">
                    <b>PREDICTED TIME-TO-FULLNESS:</b> <i>No rate data</i>
                </p>

                <!-- LAST UPDATED -->
                <p class="text-xs text-gray-500 mt-2 italic">
                    Last updated:
                    <?php echo ($diffMin === null) ? "N/A" : $diffMin . " min ago"; ?>
                </p>

            </div>
        <?php endforeach; ?>
    </div>

    <!-- UPDATE MODAL -->
    <div id="updateModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">
        <div class="bg-white p-6 rounded-xl w-96 shadow">

            <h2 class="text-lg font-bold mb-4">Update Bin Sensor</h2>

            <input type="hidden" id="modal_bin_id">

            <label class="block text-sm font-medium mb-1">Capacity %</label>
            <input id="modal_cap" type="number" min="0" max="100"
                   class="w-full border rounded-lg p-2 mb-3">

            <label class="block text-sm font-medium mb-1">CO₂ (PPM)</label>
            <input id="modal_co2" type="number" step="1"
                   class="w-full border rounded-lg p-2 mb-3">

            <label class="block text-sm font-medium mb-1">Ammonia (PPM)</label>
            <input id="modal_am" type="number" step="0.1"
                   class="w-full border rounded-lg p-2 mb-4">

            <div class="flex justify-end space-x-3">
                <button onclick="closeModal()"
                        class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>

                <button onclick="submitUpdate()"
                        class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                    Update
                </button>
            </div>

        </div>
    </div>

<script>
lucide.createIcons();

const API = "api_update.php";

/* --------------------------
   OPEN / CLOSE MODAL
--------------------------- */
function openUpdateModal(binId) {
    document.getElementById("modal_bin_id").value = binId;
    document.getElementById("updateModal").classList.remove("hidden");
    document.getElementById("updateModal").classList.add("flex");
}

function closeModal() {
    document.getElementById("updateModal").classList.add("hidden");
    document.getElementById("updateModal").classList.remove("flex");
}

/* --------------------------
   SUBMIT MANUAL UPDATE
--------------------------- */
async function submitUpdate() {
    const binId = document.getElementById("modal_bin_id").value;
    const cap = parseInt(document.getElementById("modal_cap").value);
    const co2 = parseFloat(document.getElementById("modal_co2").value);
    const am = parseFloat(document.getElementById("modal_am").value);

    const payload = {
        bin_id: binId,
        capacity_percent: cap,
        co2: co2,
        ammonia: am
    };

    const res = await fetch(API, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });

    const data = await res.json();

    refreshCard(binId);
    closeModal();
}

/* --------------------------
   REFRESH A SINGLE CARD
--------------------------- */
async function refreshCard(binId) {
    const res = await fetch("refresh_bin.php?bin_id=" + binId);
    const data = await res.json();

    // Update UI
    document.getElementById("captext-" + binId).textContent = data.capacity_percent + "%";
    document.getElementById("gauge-" + binId).style.width = data.capacity_percent + "%";

    // Gauge color
    let gaugeColor = "#10b981";
    if (data.capacity_percent >= 95) gaugeColor = "#dc2626";
    else if (data.capacity_percent >= 75) gaugeColor = "#f59e0b";
    document.getElementById("gauge-" + binId).style.background = gaugeColor;

    document.getElementById("status-" + binId).textContent = data.hygiene_status;

    // Update card border color
    const card = document.getElementById("card-" + binId);
    card.classList.remove("border-red-500","border-yellow-500","border-green-500");
    if (data.hygiene_status.includes("Alert") || data.hygiene_status.includes("Immediate"))
        card.classList.add("border-red-500");
    else if (data.hygiene_status.includes("Recommended") ||
             data.hygiene_status.includes("Soon") ||
             data.hygiene_status.includes("Ventilation"))
        card.classList.add("border-yellow-500");
    else
        card.classList.add("border-green-500");
}
</script>

</body>

</html>
