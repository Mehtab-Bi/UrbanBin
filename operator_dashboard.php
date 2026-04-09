<?php
session_start();

// -----------------------------------------------
// SECURITY CHECK (Operator Only)
// -----------------------------------------------
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "operator") {
    header("Location: operator_portal.php");
    exit;
}

$operator_id = $_SESSION["id"];

// -----------------------------------------------
// DB CONNECTION
// -----------------------------------------------
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

// -----------------------------------------------
// FETCH BIN LOCATIONS
// (Now reading capacity_percent instead of status)
// -----------------------------------------------
$bins_result = $conn->query("
    SELECT bin_id, latitude, longitude, capacity_percent 
    FROM bins 
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL
");

$bins = [];
while ($row = $bins_result->fetch_assoc()) {
    $bins[] = $row;
}

// -----------------------------------------------
// REPORT FILTERING
// -----------------------------------------------
$status_filter = $_GET["status"] ?? "All";

$query = "
    SELECT r.*, u.username AS citizen_name 
    FROM reports r 
    JOIN users u ON r.user_id = u.id
";

if ($status_filter !== "All") {
    $query .= " WHERE r.report_status = '" . $conn->real_escape_string($status_filter) . "'";
}

$query .= " ORDER BY r.reported_at DESC";
$reports = $conn->query($query);

// COUNT SUMMARY
$total_reports     = $conn->query("SELECT COUNT(*) AS total FROM reports")->fetch_assoc()["total"];
$pending_reports   = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE report_status='Pending'")->fetch_assoc()["total"];
$in_progress       = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE report_status='In Progress'")->fetch_assoc()["total"];
$resolved_reports  = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE report_status='Resolved'")->fetch_assoc()["total"];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Operator Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <style>
        #map { 
            height: 420px; 
            border-radius: 14px;
            z-index: 1;
        }
    </style>
</head>

<body class="bg-gray-100 p-8">

    <!-- HEADER -->
    <div class="max-w-6xl mx-auto flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-indigo-700">Operator Dashboard</h1>

        <a href="operator_sensor_simulator.php"
           class="px-5 py-2 bg-purple-600 text-white font-semibold rounded-lg shadow hover:bg-purple-700">
            Sensor Simulator
        </a>

        <div class="flex items-center space-x-4">
            <a href="operator_dashboard.php" class="px-3 py-1 rounded hover:bg-purple-100">Dashboard</a>
            <a href="operator_bins_status.php" class="px-3 py-1 rounded hover:bg-purple-100">Bin Status</a>
            <a href="operator_sensor_simulator.php" class="px-3 py-1 rounded hover:bg-purple-100">Sensor Simulator</a>

            <span>|</span>
            <span>User: <b><?php echo htmlspecialchars($_SESSION['username']); ?></b></span>

            <a href="logout.php"
               class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">
               Logout
            </a>
        </div>
    </div>

    <!-- BIN MAP CARD -->
    <div class="max-w-6xl mx-auto mb-10 bg-white p-6 rounded-xl shadow-lg">
        <h2 class="text-xl font-bold mb-4">Live Bin Locations</h2>

        <input 
            type="text" 
            id="searchBinInput" 
            placeholder="Search Bin ID…" 
            class="w-full mb-4 p-3 border rounded-lg shadow-sm"
        />

        <div id="map"></div>

        <div class="flex space-x-6 mt-4 text-sm">
            <div><span class="inline-block w-3 h-3 bg-green-600 rounded-full"></span> Normal</div>
            <div><span class="inline-block w-3 h-3 bg-yellow-500 rounded-full"></span> Warning</div>
            <div><span class="inline-block w-3 h-3 bg-red-600 rounded-full"></span> Alert</div>
        </div>
    </div>

    <!-- STATUS CARDS -->
    <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
        <div class="bg-white p-6 rounded-xl shadow border-l-4 border-indigo-500">
            <p>Total Reports</p>
            <p class="text-3xl font-bold"><?php echo $total_reports; ?></p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow border-l-4 border-yellow-500">
            <p>Pending</p>
            <p class="text-3xl font-bold"><?php echo $pending_reports; ?></p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow border-l-4 border-blue-500">
            <p>In Progress</p>
            <p class="text-3xl font-bold"><?php echo $in_progress; ?></p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow border-l-4 border-green-500">
            <p>Resolved</p>
            <p class="text-3xl font-bold"><?php echo $resolved_reports; ?></p>
        </div>
    </div>

    <!-- REPORT TABLE -->
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-xl shadow-lg">
        <h2 class="text-xl font-bold mb-4">Reported Issues</h2>
        <div class="overflow-x-auto">

            <table class="min-w-full border rounded-lg">
                <thead class="bg-gray-100 text-sm">
                    <tr>
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">Citizen</th>
                        <th class="px-4 py-2">Bin</th>
                        <th class="px-4 py-2">Issue</th>
                        <th class="px-4 py-2 text-center">Status</th>
                        <th class="px-4 py-2 text-center">Reported At</th>
                        <th class="px-4 py-2 text-center">Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php while ($row = $reports->fetch_assoc()): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2"><?= $row['id']; ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($row['citizen_name']); ?></td>
                        <td class="px-4 py-2"><?= $row['bin_id']; ?></td>
                        <td class="px-4 py-2"><?= $row['issue_type']; ?></td>

                        <td class="px-4 py-2 text-center">
                            <?php
                            $colors = [
                                "Pending" => "bg-yellow-200 text-yellow-800",
                                "In Progress" => "bg-blue-200 text-blue-800",
                                "Resolved" => "bg-green-200 text-green-800",
                                "Rejected" => "bg-red-200 text-red-800"
                            ];
                            $badge = $colors[$row["report_status"]] ?? "bg-gray-200";
                            ?>
                            <span class="px-3 py-1 rounded-full text-sm <?= $badge ?>">
                                <?= $row['report_status']; ?>
                            </span>
                        </td>

                        <td class="px-4 py-2 text-center"><?= $row['reported_at']; ?></td>

                        <td class="px-4 py-2 text-center">
                            <a href="operator_update_report.php?id=<?= $row['id']; ?>"
                               class="bg-indigo-600 text-white px-3 py-2 rounded-lg text-sm shadow hover:bg-indigo-700">
                                View / Update
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>

            </table>

        </div>
    </div>

<script>

// Load bins as JS array
const bins = <?php echo json_encode($bins); ?>;

// Initialize map
let map = L.map("map");

// Prepare coordinates
let binCoords = bins
    .filter(b => b.latitude && b.longitude)
    .map(b => [parseFloat(b.latitude), parseFloat(b.longitude)]);

// Fit to markers
if (binCoords.length > 0) {
    map.fitBounds(binCoords, { padding: [50, 50] });
} else {
    map.setView([12.9716, 77.5946], 12);
}

// Tile layer
L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19
}).addTo(map);

// Color logic based on capacity_percent
function getColor(cap) {
    if (cap >= 80) return "red";      // Alert
    if (cap >= 50) return "orange";   // Warning
    return "green";                   // Normal
}

// Add markers
bins.forEach(bin => {
    if (!bin.latitude || !bin.longitude) return;

    L.circleMarker(
        [parseFloat(bin.latitude), parseFloat(bin.longitude)],
        {
            radius: 8,
            color: getColor(bin.capacity_percent),
            fillColor: getColor(bin.capacity_percent),
            fillOpacity: 0.9
        }
    )
    .bindPopup(`<b>${bin.bin_id}</b><br>Fill Level: ${bin.capacity_percent}%`)
    .addTo(map);
});

// Search
document.getElementById("searchBinInput").addEventListener("input", e => {
    const text = e.target.value.toLowerCase();
    const match = bins.find(b => b.bin_id.toLowerCase().includes(text));

    if (match) {
        map.setView(
            [parseFloat(match.latitude), parseFloat(match.longitude)], 
            16
        );
    }
});

</script>

<script>
    lucide.createIcons();
</script>

</body>
</html>
