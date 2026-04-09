<?php 
session_start();

// SECURITY CHECK
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login_selection.php");
    exit;
}

// DB
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "smart_hygiene_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

$bins = [];
$res = $conn->query("SELECT bin_id, latitude, longitude, capacity_percent, co2, ammonia FROM bins");
while ($row = $res->fetch_assoc()) $bins[] = $row;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Heatmap Intelligence - Smart Hygiene</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tailwind + Icons -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Leaflet Heat Plugin -->
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        #heatmap { height: calc(100vh - 20px); width: 100%; }

        .map-btn {
            @apply px-4 py-2 text-sm font-semibold rounded-lg shadow bg-white hover:bg-slate-100;
        }
        .map-btn-active {
            @apply bg-blue-600 text-white hover:bg-blue-700;
        }
    </style>
</head>

<body class="bg-slate-100">

<div class="flex">

    <!-- SIDEBAR COPY FROM admin_home.php -->
    <aside class="bg-slate-900 text-slate-100 w-64 flex flex-col">
        <div class="flex items-center justify-between px-4 py-4 border-b border-slate-800">
            <div class="flex items-center">
                <div class="bg-violet-600 rounded-lg p-2 mr-2">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-widest text-slate-400">Smart Hygiene</p>
                    <p class="text-sm font-semibold">Admin Console</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 px-2 py-4 space-y-1 text-sm">
            <a href="admin_home.php" class="flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2"></i> Dashboard Overview
            </a>
            <a href="manage_bins.php" class="flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i> Manage Bins
            </a>
            <a href="admin_reports.php" class="flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="clipboard-list" class="w-4 h-4 mr-2"></i> Reports & Escalations
            </a>
            <a href="admin_users.php" class="flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="users" class="w-4 h-4 mr-2"></i> Users & Operators
            </a>
            <a href="admin_rewards.php" class="flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="award" class="w-4 h-4 mr-2"></i> Reward Analytics
            </a>
            <a href="admin_prediction.php" class="flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="line-chart" class="w-4 h-4 mr-2"></i> Prediction & Insights
            </a>
            <a href="admin_heatmap.php" class="flex items-center px-3 py-2 rounded-lg bg-slate-800">
                <i data-lucide="map" class="w-4 h-4 mr-2"></i> City Heatmap
            </a>
            <a href="admin_logs.php" class="flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="activity" class="w-4 h-4 mr-2"></i> System Logs
            </a>
        </nav>
    </aside>



    <!-- MAIN AREA -->
    <main class="flex-1 p-4">

        <!-- Title -->
        <div class="flex justify-between items-center mb-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">City Heatmap Intelligence</h1>
                <p class="text-slate-500 text-sm">Waste Density · Hygiene Index · Hybrid Risk</p>
            </div>
        </div>

        <!-- Mode Switch Buttons -->
        <div class="mb-4 flex gap-2">
            <button id="btnWaste" class="map-btn map-btn-active">Waste Level</button>
            <button id="btnHygiene" class="map-btn">Hygiene Index</button>
            <button id="btnHybrid" class="map-btn">Hybrid Intelligence</button>
        </div>

        <!-- Map -->
        <div id="heatmap"></div>

    </main>
</div>

<script>
    lucide.createIcons();

    const bins = <?php echo json_encode($bins); ?>;

    // Initialize Map
    const map = L.map('heatmap').setView([12.9716, 77.5946], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    let heatLayer;


    /* ============================================================
       VISIBILITY BOOSTER — solves "just a plain map" problem
    ============================================================ */
    function boostIntensity(value) {
        // prevents invisibility
        let boosted = value * 2.5;

        // Min intensity so that dot is visible
        if (boosted < 0.3) boosted = 0.3;

        // Max intensity (Leaflet supports beyond 1)
        if (boosted > 3) boosted = 3;

        return boosted;
    }

    function normalize(x) {
        return Math.min(1, Math.max(0, x));
    }


    /* ============================================================
       HEATMAP MODES
    ============================================================ */

    function loadWasteHeatmap() {
        setActiveButton("btnWaste");

        const points = bins.map(b => [
            parseFloat(b.latitude),
            parseFloat(b.longitude),
            boostIntensity(b.capacity_percent / 100)
        ]);

        redrawHeat(points);
    }

    function loadHygieneHeatmap() {
        setActiveButton("btnHygiene");

        const points = bins.map(b => {
            const hygieneIndex = normalize((b.co2 / 3000) + (b.ammonia / 5));

            return [
                parseFloat(b.latitude),
                parseFloat(b.longitude),
                boostIntensity(hygieneIndex)
            ];
        });

        redrawHeat(points);
    }

    function loadHybridHeatmap() {
        setActiveButton("btnHybrid");

        const points = bins.map(b => {
            const waste = b.capacity_percent / 100;
            const hygiene = normalize((b.co2 / 3000) + (b.ammonia / 5));

            const hybrid = (waste * 0.6) + (hygiene * 0.4);

            return [
                parseFloat(b.latitude),
                parseFloat(b.longitude),
                boostIntensity(hybrid)
            ];
        });

        redrawHeat(points);
    }


    /* ============================================================
       DRAW HEATMAP LAYER
    ============================================================ */
    function redrawHeat(points) {
        if (heatLayer) heatLayer.remove();

        heatLayer = L.heatLayer(points, {
            radius: 45,
            blur: 35,
            maxZoom: 17,
            gradient: {
                0.0: "green",
                0.4: "yellow",
                0.7: "orange",
                1.0: "red"
            }
        }).addTo(map);
    }

    /* ============================================================
       UI BUTTON HANDLING
    ============================================================ */
    function setActiveButton(btnId) {
        ["btnWaste", "btnHygiene", "btnHybrid"].forEach(id =>
            document.getElementById(id).classList.remove("map-btn-active")
        );

        document.getElementById(btnId).classList.add("map-btn-active");
    }

    /* Load default mode */
    loadWasteHeatmap();


    /* ============================================================
       AUTO REFRESH every 10 sec
    ============================================================ */
    setInterval(() => {
        fetch("admin_prediction_ajax.php?type=prediction")
            .then(res => res.json())
            .then(data => {

                // Update bins with new predictions
                data.predictions.forEach(p => {
                    const bin = bins.find(b => b.bin_id === p.bin_id);
                    if (bin) {
                        bin.capacity_percent = p.current;
                    }
                });

                // Re-render based on active mode
                if (document.getElementById("btnWaste").classList.contains("map-btn-active")) {
                    loadWasteHeatmap();
                } else if (document.getElementById("btnHygiene").classList.contains("map-btn-active")) {
                    loadHygieneHeatmap();
                } else {
                    loadHybridHeatmap();
                }
            });

    }, 10000);

</script>

</body>
</html>
