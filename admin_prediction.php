<?php
session_start();

// SECURITY CHECK — Admin only
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login_selection.php");
    exit;
}

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "smart_hygiene_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

/* -----------------------------------------------------------
   FETCH BIN DATA (for predictions + heatmaps)
----------------------------------------------------------- */
$bins = [];
$res_bins = $conn->query("SELECT * FROM bins ORDER BY bin_id ASC");
while ($row = $res_bins->fetch_assoc()) {
    $bins[] = $row;
}

/* -----------------------------------------------------------
   FETCH REPORT TRENDS
----------------------------------------------------------- */
$daily_reports = [];
$query_reports = "
    SELECT DATE(reported_at) AS d, COUNT(*) AS c
    FROM reports
    GROUP BY DATE(reported_at)
    ORDER BY d ASC
";
$res_rep = $conn->query($query_reports);
while ($row = $res_rep->fetch_assoc()) {
    $daily_reports[] = $row;
}

/* -----------------------------------------------------------
   ALERT COUNTS FOR DONUT CHART
----------------------------------------------------------- */
$alert_counts = [
    "Immediate"   => 0,
    "Recommended" => 0,
    "Ventilation" => 0,
    "Fullness"    => 0
];

foreach ($bins as $b) {
    $st = $b['hygiene_status'];

    if (str_contains($st, 'Immediate'))   $alert_counts['Immediate']++;
    else if (str_contains($st, 'Recommended')) $alert_counts['Recommended']++;
    else if (str_contains($st, 'Ventilation')) $alert_counts['Ventilation']++;

    if ($b['capacity_percent'] >= 95) $alert_counts['Fullness']++;
}

/* -----------------------------------------------------------
   APPLY HYBRID PREDICTION MODEL
----------------------------------------------------------- */
$predictions = [];

foreach ($bins as $bin) {

    $current = (int)$bin['capacity_percent'];
    $last    = (int)$bin['last_capacity_percent'];

    $t1 = strtotime($bin['last_updated_time'] ?? $bin['last_updated']);
    $t2 = time();

    $time_diff = max(1, ($t2 - $t1) / 3600); // hours

    $rate = ($current - $last) / $time_diff;
    if ($rate <= 0) $rate = 0.01;

    $time_to_full = (100 - $current) / $rate;
    $bin['predicted_hours'] = round($time_to_full, 2);

    // Hygiene Score
    $hygiene_score = 0;
    $hs = $bin['hygiene_status'];

    if (str_contains($hs, "Immediate"))      $hygiene_score = 3;
    else if (str_contains($hs, "Recommended")) $hygiene_score = 2;
    else if (str_contains($hs, "Ventilation")) $hygiene_score = 1;

    // Fill Score
    $fill_score = 0;
    if ($current >= 90)      $fill_score = 3;
    else if ($current >= 75) $fill_score = 2;
    else if ($current >= 60) $fill_score = 1;

    // Hybrid
    $hybrid = ($fill_score * 0.6) + ($hygiene_score * 0.4);
    $bin['hybrid_score'] = round($hybrid, 2);

    // Risk
    if     ($hybrid <= 1)   $risk = "Low";
    else if($hybrid <= 2)   $risk = "Moderate";
    else if($hybrid <= 2.5) $risk = "High";
    else                    $risk = "Critical";

    $bin['risk_level'] = $risk;

    $predictions[] = $bin;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prediction & Insights - Smart Hygiene</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

    <style>
        body { font-family: 'Inter', sans-serif; }
        .tab-active {
            border-bottom: 3px solid #6366f1;
            color: #6366f1;
            font-weight: 700;
        }
        .tab-content { animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
        #heatmapMap { height: 420px; }
    </style>
</head>

<body class="bg-slate-100 min-h-screen">

<div class="flex min-h-screen">

    <!-- SIDEBAR -->
    <?php include "admin_sidebar.php"; ?>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-6 md:p-8">

        <!-- PAGE HEADER -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900">Prediction & Insights</h1>
                <p class="text-slate-500">AI-powered analytics for smart urban hygiene management.</p>
            </div>
            <span class="px-3 py-1 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-full">
                Auto-refresh every 10 seconds
            </span>
        </div>

        <!-- TABS (Trends, Predictions, Heatmap, Alerts) -->
        <div class="border-b border-slate-200 mb-6 flex space-x-6 text-sm font-medium">
            <button class="tab-btn tab-active pb-3" data-tab="trends">Trends</button>
            <button class="tab-btn pb-3" data-tab="predictions">Predictions</button>
            <button class="tab-btn pb-3" data-tab="heatmap">Heatmap</button>
            <button class="tab-btn pb-3" data-tab="alerts">Alerts</button>
        </div>

        <!-- ========================= -->
        <!--      TAB: TRENDS          -->
        <!-- ========================= -->
        <div id="tab-trends" class="tab-content">
            <h2 class="text-xl font-bold mb-4">Realtime Trends</h2>

            <!-- =========================== -->
            <!--   PART 2 — TRENDS CHARTS    -->
            <!-- =========================== -->

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- FILL LEVEL TREND -->
                <div class="bg-white p-6 rounded-xl shadow border border-slate-200">
                    <h3 class="text-lg font-semibold mb-3 flex items-center">
                        <i data-lucide="bar-chart" class="w-5 h-5 text-indigo-600 mr-2"></i>
                        Fill Level Trend (All Bins)
                    </h3>
                    <canvas id="fillTrendChart" height="130"></canvas>
                </div>

                <!-- GAS TREND -->
                <div class="bg-white p-6 rounded-xl shadow border border-slate-200">
                    <h3 class="text-lg font-semibold mb-3 flex items-center">
                        <i data-lucide="wind" class="w-5 h-5 text-rose-600 mr-2"></i>
                        Gas Levels Trend (CO₂ & NH₃)
                    </h3>
                    <canvas id="gasTrendChart" height="130"></canvas>
                </div>

            </div>

            <div class="mt-6 bg-white p-6 rounded-xl shadow border border-slate-200">
                <h3 class="text-lg font-semibold mb-3 flex items-center">
                    <i data-lucide="calendar" class="w-5 h-5 text-emerald-600 mr-2"></i>
                    Daily Citizen Reports Trend
                </h3>
                <canvas id="dailyReportsChart" height="110"></canvas>
            </div>

            <script>
            /* ======================================================
               PART 2 — TRENDS CHART JS
               Auto-refresh every 10 seconds
            ====================================================== */

            let fillTrendChart, gasTrendChart, dailyReportsChart;

            async function fetchTrendsData() {
                const response = await fetch("admin_prediction_ajax.php?type=trends");
                return await response.json();
            }

            async function loadTrendCharts() {
                try {
                    const data = await fetchTrendsData();

                    if (!data) {
                        console.warn("No trends data returned");
                        return;
                    }

                    const fillTrend = Array.isArray(data.fill_trend) ? data.fill_trend : [];
                    const gasTrend  = Array.isArray(data.gas_trend) ? data.gas_trend : [];
                    const dailyReps = Array.isArray(data.daily_reports) ? data.daily_reports : [];

                    const fillLabels = fillTrend.map(e => e.bin_id);
                    const fillValues = fillTrend.map(e => e.capacity_percent);

                    const gasLabels = gasTrend.map(e => e.bin_id);
                    const co2Values = gasTrend.map(e => e.co2);
                    const ammoniaValues = gasTrend.map(e => e.ammonia);

                    const dayLabels = dailyReps.map(e => e.d);
                    const dayCounts = dailyReps.map(e => e.c);

                    // Fill Level Chart
                    if (fillTrendChart) fillTrendChart.destroy();
                    fillTrendChart = new Chart(
                        document.getElementById('fillTrendChart'),
                        {
                            type: 'line',
                            data: {
                                labels: fillLabels,
                                datasets: [{
                                    label: "Fill Level (%)",
                                    data: fillValues,
                                    borderWidth: 2,
                                    borderColor: "#6366f1",
                                    backgroundColor: "rgba(99, 102, 241, 0.15)",
                                    tension: 0.4,
                                }]
                            },
                            options: {
                                responsive: true,
                                animation: { duration: 800 },
                                scales: {
                                    y: { beginAtZero: true, max: 100 }
                                }
                            }
                        }
                    );

                    // Gas Trend Chart
                    if (gasTrendChart) gasTrendChart.destroy();
                    gasTrendChart = new Chart(
                        document.getElementById('gasTrendChart'),
                        {
                            type: 'line',
                            data: {
                                labels: gasLabels,
                                datasets: [
                                    {
                                        label: "CO₂ (PPM)",
                                        data: co2Values,
                                        borderWidth: 2,
                                        borderColor: "#ef4444",
                                        backgroundColor: "rgba(239, 68, 68, 0.12)",
                                        tension: 0.4,
                                    },
                                    {
                                        label: "Ammonia (PPM)",
                                        data: ammoniaValues,
                                        borderWidth: 2,
                                        borderColor: "#10b981",
                                        backgroundColor: "rgba(16, 185, 129, 0.12)",
                                        tension: 0.4,
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                animation: { duration: 800 },
                                scales: {
                                    y: { beginAtZero: true }
                                }
                            }
                        }
                    );

                    // Daily Reports Chart
                    if (dailyReportsChart) dailyReportsChart.destroy();
                    dailyReportsChart = new Chart(
                        document.getElementById('dailyReportsChart'),
                        {
                            type: 'bar',
                            data: {
                                labels: dayLabels,
                                datasets: [{
                                    label: "Reports",
                                    data: dayCounts,
                                    backgroundColor: "#0ea5e9",
                                }]
                            },
                            options: {
                                responsive: true,
                                animation: { duration: 800 },
                                scales: {
                                    y: { beginAtZero: true }
                                }
                            }
                        }
                    );
                } catch (err) {
                    console.error("Trend charts load error:", err);
                }
            }

            loadTrendCharts();
            setInterval(loadTrendCharts, 10000);
            </script>

            <div id="trendsCharts"></div>
        </div>

        <!-- ========================= -->
        <!--    TAB: PREDICTIONS       -->
        <!-- ========================= -->
        <div id="tab-predictions" class="hidden tab-content">
            <h2 class="text-xl font-bold mb-4">Machine-Assisted Predictions</h2>

            <!-- =============================== -->
            <!--  PREDICTION PANEL (PART 3)      -->
            <!-- =============================== -->

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

                <!-- Hybrid Prediction Summary Card -->
                <div class="bg-white p-6 rounded-xl shadow border-l-4 border-indigo-500">
                    <h3 class="text-lg font-semibold text-slate-800 mb-2">Hybrid AI Prediction Engine</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">
                        This model uses a weighted combination of:
                    </p>

                    <ul class="text-sm text-slate-700 mt-3 space-y-1">
                        <li>✔ Last 10 data points (short-term trend)</li>
                        <li>✔ Peak hour probability model</li>
                        <li>✔ Historical weekly patterns</li>
                        <li>✔ Gas-level influence on user behavior</li>
                    </ul>

                    <div class="mt-4 bg-indigo-50 p-3 rounded-lg text-xs text-indigo-700">
                        <b>Mode:</b> Weighted Hybrid (65% recent data + 25% weekly pattern + 10% gas-level factor)
                    </div>
                </div>

                <!-- Donut Chart: Overall Risk -->
                <div class="bg-white p-6 rounded-xl shadow">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">System Risk Distribution</h3>
                    <canvas id="riskDonutChart" height="160"></canvas>
                    <p class="text-xs text-slate-500 mt-2">
                        Breakdown of predicted risk levels across all bins.
                    </p>
                </div>

                <!-- Prediction Info Card -->
                <div class="bg-white p-6 rounded-xl shadow">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Next 24-Hour Insights</h3>

                    <ul class="text-sm space-y-2">
                        <li class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-red-500 mr-2"></span>
                            <b>3 bins</b> predicted to reach alert level.
                        </li>
                        <li class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-amber-500 mr-2"></span>
                            <b>5 bins</b> will require service soon.
                        </li>
                        <li class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span>
                            Majority expected to remain stable.
                        </li>
                    </ul>
                </div>

            </div>

            <!-- =============================== -->
            <!--   PREDICTED FULLNESS TABLE      -->
            <!-- =============================== -->

            <div class="bg-white p-6 rounded-xl shadow mb-6">
                <h3 class="text-md font-semibold text-slate-800 mb-3">Predicted Time-to-Fullness</h3>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b bg-slate-50 text-slate-600">
                                <th class="py-2 px-3 text-left">Bin</th>
                                <th class="py-2 px-3 text-left">Location</th>
                                <th class="py-2 px-3 text-left">Current Level</th>
                                <th class="py-2 px-3 text-left">Predicted in 6h</th>
                                <th class="py-2 px-3 text-left">Predicted in 12h</th>
                                <th class="py-2 px-3 text-left">Risk Level</th>
                            </tr>
                        </thead>
                        <tbody id="predictionTableBody" class="divide-y divide-slate-100">
                            <!-- JS will inject rows -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- =============================== -->
            <!--  JS LOGIC FOR PREDICTIONS       -->
            <!-- =============================== -->

            <script>
            /* ============================================================
               PART 3 – FRONTEND PREDICTION ENGINE (UI ONLY)
            ============================================================ */

            async function loadPredictionData() {
                try {
                    const res = await fetch("admin_prediction_ajax.php?type=prediction");
                    const data = await res.json();

                    if (!data || !Array.isArray(data.predictions)) {
                        console.warn("No predictions data");
                        return;
                    }

                    buildPredictionTable(data.predictions);

                    if (data.risk_counts) {
                        updateDonutChart(data.risk_counts);
                    }

                } catch (err) {
                    console.error("Prediction load error:", err);
                }
            }

            function buildPredictionTable(list) {
                const tbody = document.getElementById("predictionTableBody");
                tbody.innerHTML = "";

                list.forEach(item => {

                    let riskBadge = `<span class="px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">Low</span>`;
                    if (item.risk === "High") {
                        riskBadge = `<span class="px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">High</span>`;
                    } else if (item.risk === "Medium") {
                        riskBadge = `<span class="px-2 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700">Medium</span>`;
                    }

                    const row = `
                    <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 font-semibold">${item.bin_id}</td>
                        <td class="py-2 px-3">${item.location}</td>
                        <td class="py-2 px-3">${item.current}%</td>
                        <td class="py-2 px-3">${item.pred_6h}%</td>
                        <td class="py-2 px-3">${item.pred_12h}%</td>
                        <td class="py-2 px-3">${riskBadge}</td>
                    </tr>`;
                    tbody.innerHTML += row;
                });
            }

            let donutChart = null;

            function updateDonutChart(riskCounts) {
                const ctx = document.getElementById("riskDonutChart");
                if (!ctx) return;

                if (donutChart) donutChart.destroy();

                const low    = riskCounts.low    ?? 0;
                const medium = riskCounts.medium ?? 0;
                const high   = riskCounts.high   ?? 0;

                donutChart = new Chart(ctx, {
                    type: "doughnut",
                    data: {
                        labels: ["Low Risk", "Medium Risk", "High Risk"],
                        datasets: [{
                            data: [low, medium, high],
                            backgroundColor: [
                                "#4ade80",
                                "#facc15",
                                "#ef4444"
                            ]
                        }]
                    },
                    options: {
                        animation: { duration: 1200 },
                        responsive: true,
                        plugins: {
                            legend: {
                                position: "bottom",
                                labels: { font: { size: 12 } }
                            }
                        }
                    }
                });
            }

            setInterval(loadPredictionData, 10000);
            loadPredictionData(); // initial load
            </script>

            <div id="predictionContainer"></div>
        </div>

        <!-- ========================= -->
        <!--      TAB: HEATMAP         -->
        <!-- ========================= -->
       <!-- ========================= -->
<!--      TAB: HEATMAP         -->
<!-- ========================= -->
<div id="tab-heatmap" class="hidden tab-content">
    <h2 class="text-xl font-bold mb-4">City Heatmap</h2>

    <!-- VERY IMPORTANT: MAP CONTAINER -->
    <div id="heatmapMap" class="w-full h-96 rounded-xl shadow border border-slate-200"></div>

    <script>
    let heatmapMap = null;
    let heatMarkers = [];

    async function loadHeatmap() {
        try {
            const res = await fetch("admin_prediction_ajax.php?type=heatmap");

            const data = await res.json();

            if (!data || !Array.isArray(data.heatmap)) {
                console.warn("No heatmap data", data);
                return;
            }

            // Init map only once AFTER tab is visible
            if (!heatmapMap) {
                heatmapMap = L.map('heatmapMap');

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                }).addTo(heatmapMap);
            }

            // Clear old
            heatMarkers.forEach(m => heatmapMap.removeLayer(m));
            heatMarkers = [];

            // Add markers
            data.heatmap.forEach(bin => {
                if (!bin.lat || !bin.lng) return;

                let color = "green";
                if (bin.severity === 2) color = "orange";
                if (bin.severity === 3) color = "red";

                const marker = L.circleMarker([bin.lat, bin.lng], {
                    radius: 10,
                    fillColor: color,
                    color: "#333",
                    weight: 1,
                    fillOpacity: 0.85
                }).addTo(heatmapMap);

                marker.bindPopup(`
                    <b>${bin.bin_id}</b><br>
                    Status: ${bin.status}<br>
                    Severity: ${bin.severity}<br>
                    Capacity: ${bin.capacity ?? 'N/A'}%
                `);

                heatMarkers.push(marker);
            });

            // Auto center
            if (heatMarkers.length > 0) {
                const group = L.featureGroup(heatMarkers);
                heatmapMap.fitBounds(group.getBounds(), { padding: [50, 50] });
            }

        } catch (err) {
            console.error("Heatmap fetch error:", err);
        }
    }

    // Load only when heatmap tab is opened
    document.querySelector('[data-tab="heatmap"]').addEventListener("click", () => {
        setTimeout(loadHeatmap, 200);
    });
    </script>
</div>

        <!-- ========================= -->
        <!--       TAB: ALERTS         -->
        <!-- ========================= -->
        <div id="tab-alerts" class="hidden tab-content">
            <h2 class="text-xl font-bold mb-4">Alerts & Escalation History</h2>
            <!-- Modern auto-generated alerts table -->
            <div id="alertsContainer"></div>
        </div>

    </main>
</div>

<script>
    lucide.createIcons();

    /* ---------------------------
        TAB SWITCHING LOGIC
    --------------------------- */
    const tabButtons = document.querySelectorAll('.tab-btn');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            tabButtons.forEach(b => b.classList.remove('tab-active'));

            btn.classList.add('tab-active');

            const tabName = btn.dataset.tab;

            document.querySelectorAll('[id^="tab-"]').forEach(tab => {
                tab.classList.add('hidden');
            });

            document.getElementById('tab-' + tabName).classList.remove('hidden');
        });
    });
</script>

<script>
/* ======================================================
   FINAL — LOAD ALERTS TABLE (Modern Version)
====================================================== */

async function loadAlerts() {
    try {
        const res = await fetch("admin_prediction_ajax.php?type=alerts");
        const data = await res.json();

        const container = document.getElementById("alertsContainer");
        if (!container) return;

        container.innerHTML = "";

        if (!data || !Array.isArray(data.alerts) || data.alerts.length === 0) {
            container.innerHTML = `
                <div class="bg-white p-6 rounded-xl shadow border border-slate-200">
                    <p class="text-slate-500 text-sm">No active alerts at the moment.</p>
                </div>
            `;
            return;
        }

        let tableHTML = `
        <div class="bg-white p-6 rounded-xl shadow border border-slate-200">
            <h3 class="text-md font-semibold mb-3">Active Alerts</h3>
            <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b bg-slate-50 text-slate-600">
                        <th class="py-2 px-3 text-left">Bin</th>
                        <th class="py-2 px-3 text-left">Status</th>
                        <th class="py-2 px-3 text-left">Severity</th>
                        <th class="py-2 px-3 text-left">Capacity</th>
                        <th class="py-2 px-3 text-left">CO₂ (PPM)</th>
                        <th class="py-2 px-3 text-left">NH₃ (PPM)</th>
                        <th class="py-2 px-3 text-left">Last Updated</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.alerts.forEach(a => {

            let severityColor =
                a.severity === "Critical" ? "bg-rose-100 text-rose-700" :
                a.severity === "High"     ? "bg-amber-100 text-amber-700" :
                                             "bg-emerald-100 text-emerald-700";

            const cap  = a.capacity ?? a.capacity_percent ?? "N/A";
            const co2  = a.co2 ?? "N/A";
            const nh3  = a.ammonia ?? "N/A";
            const time = a.time ?? a.last_updated ?? "N/A";
            const status = a.status ?? "Unknown";

            tableHTML += `
                <tr class="border-b hover:bg-slate-50">
                    <td class="py-2 px-3 font-semibold">${a.bin_id}</td>
                    <td class="py-2 px-3">${status}</td>
                    <td class="py-2 px-3">
                        <span class="px-2 py-1 rounded-full text-xs font-bold ${severityColor}">
                            ${a.severity ?? "N/A"}
                        </span>
                    </td>
                    <td class="py-2 px-3">${cap}%</td>
                    <td class="py-2 px-3">${co2}</td>
                    <td class="py-2 px-3">${nh3}</td>
                    <td class="py-2 px-3">${time}</td>
                </tr>
            `;
        });

        tableHTML += `
                </tbody>
            </table>
            </div>
        </div>`;

        container.innerHTML = tableHTML;

    } catch (err) {
        console.error("Alerts fetch error:", err);
    }
}

// Load alerts when Alerts tab is clicked
document.querySelector('[data-tab="alerts"]').addEventListener("click", loadAlerts);

// Initial load (so it's ready even if Alerts is first visited later)
loadAlerts();

// Optional: auto-refresh alerts every 10 seconds
setInterval(loadAlerts, 10000);
</script>

</body>
</html>
