<?php
session_start();

// Only admin can access
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_selection.php");
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "smart_hygiene_db");
if (!$conn) {
    die("DB Connection Failed: " . mysqli_connect_error());
}

/* -----------------------------------------
   Fetch Summary Values
------------------------------------------*/

// Total points earned by all citizens
$res = mysqli_query($conn,
    "SELECT SUM(points_change) AS total_points FROM point_transactions"
);
$total_points = mysqli_fetch_assoc($res)['total_points'] ?? 0;

// Average points per citizen
$res = mysqli_query($conn,
    "SELECT AVG(points) AS avg_points FROM users WHERE role='citizen'"
);
$avg_points = round(mysqli_fetch_assoc($res)['avg_points'] ?? 0, 2);

// Number of transactions
$res = mysqli_query($conn,
    "SELECT COUNT(*) AS total_txn FROM point_transactions"
);
$total_txn = mysqli_fetch_assoc($res)['total_txn'] ?? 0;

// Highest eco points
$res = mysqli_query($conn,
    "SELECT username, points FROM users ORDER BY points DESC LIMIT 1"
);
$top_user_data = mysqli_fetch_assoc($res);
$top_user = $top_user_data['username'] ?? "-";
$top_user_points = $top_user_data['points'] ?? 0;


/* -----------------------------------------
   Leaderboard (Top 10 Citizens)
------------------------------------------*/
$leaderboard = mysqli_query($conn,
    "SELECT username, points 
     FROM users 
     WHERE role='citizen'
     ORDER BY points DESC LIMIT 10"
);


/* -----------------------------------------
   Points Over Time (Line Chart)
------------------------------------------*/
$points_over_time = mysqli_query($conn,
    "SELECT DATE(transaction_date) AS d, SUM(points_change) AS p
     FROM point_transactions
     GROUP BY DATE(transaction_date)
     ORDER BY d ASC"
);

$dates = [];
$points = [];
while ($row = mysqli_fetch_assoc($points_over_time)) {
    $dates[] = $row['d'];
    $points[] = $row['p'];
}


/* -----------------------------------------
   Issue-type Distribution (Pie chart)
------------------------------------------*/
$issue_counts = mysqli_query($conn,
    "SELECT issue_type, COUNT(*) AS cnt 
     FROM reports GROUP BY issue_type"
);

$issue_labels = [];
$issue_values = [];
while ($i = mysqli_fetch_assoc($issue_counts)) {
    $issue_labels[] = $i['issue_type'];
    $issue_values[] = $i['cnt'];
}


/* -----------------------------------------
   Recent Reward Activity Feed
------------------------------------------*/
$recent_txn = mysqli_query($conn,
    "SELECT pt.*, u.username 
     FROM point_transactions pt
     JOIN users u ON pt.user_id = u.id
     ORDER BY transaction_date DESC
     LIMIT 10"
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reward Analytics - Smart Hygiene</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-collapsed { width: 4.5rem !important; }
        .sidebar-collapsed .sidebar-text { display: none; }
        .sidebar-collapsed .sidebar-logo-text { display: none; }
    </style>
</head>

<body class="bg-slate-100 min-h-screen">

<div class="flex min-h-screen">

    <!-- SIDEBAR (Copied from admin_home) -->
    <aside id="sidebar" class="bg-slate-900 text-slate-100 w-64 transition-all duration-300 flex flex-col">

        <!-- Logo -->
        <div class="flex items-center justify-between px-4 py-4 border-b border-slate-800">
            <div class="flex items-center">
                <div class="bg-violet-600 rounded-lg p-2 mr-2">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                </div>
                <div class="sidebar-logo-text">
                    <p class="text-xs text-slate-400 uppercase">Smart Hygiene</p>
                    <p class="text-sm font-semibold">Admin Console</p>
                </div>
            </div>
            <button id="sidebarToggle" class="p-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="panel-left-close" class="w-5 h-5"></i>
            </button>
        </div>

        <nav class="flex-1 px-2 py-4 space-y-1 text-sm">
            <a href="admin_home.php" class="sidebar-item flex px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Dashboard Overview</span>
            </a>

            <a href="manage_bins.php" class="sidebar-item flex px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Manage Bins</span>
            </a>

            <a href="admin_reports.php" class="sidebar-item flex px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="clipboard-list" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Reports & Escalations</span>
            </a>

            <a href="admin_users.php" class="sidebar-item flex px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="users" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Users & Operators</span>
            </a>

            <a href="admin_rewards.php"
               class="sidebar-item flex px-3 py-2 rounded-lg bg-slate-800 text-violet-300 font-semibold">
                <i data-lucide="award" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Reward Analytics</span>
            </a>

            <a href="admin_prediction.php" class="sidebar-item flex px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="line-chart" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Prediction & Insights</span>
            </a>

            <a href="admin_map.php" class="sidebar-item flex px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="map" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">City Bin Map</span>
            </a>

            <a href="admin_logs.php" class="sidebar-item flex px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="activity" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">System Logs</span>
            </a>
        </nav>

    </aside>


    <!-- MAIN CONTENT -->
    <main class="flex-1 p-8">

        <h1 class="text-3xl font-extrabold text-slate-900 mb-6">Reward Analytics</h1>

        <!-- SUMMARY CARDS -->
        <div class="grid md:grid-cols-4 gap-4 mb-8">

            <div class="bg-white p-4 rounded-xl shadow">
                <p class="text-xs uppercase text-slate-500">Total Points</p>
                <p class="text-3xl font-bold"><?php echo $total_points; ?></p>
            </div>

            <div class="bg-white p-4 rounded-xl shadow">
                <p class="text-xs uppercase text-slate-500">Avg Points per Citizen</p>
                <p class="text-3xl font-bold"><?php echo $avg_points; ?></p>
            </div>

            <div class="bg-white p-4 rounded-xl shadow">
                <p class="text-xs uppercase text-slate-500">Total Transactions</p>
                <p class="text-3xl font-bold"><?php echo $total_txn; ?></p>
            </div>

            <div class="bg-white p-4 rounded-xl shadow">
                <p class="text-xs uppercase text-slate-500">Top Citizen</p>
                <p class="text-xl font-semibold"><?php echo $top_user; ?></p>
                <p class="text-sm text-slate-700"><?php echo $top_user_points; ?> points</p>
            </div>

        </div>


        <!-- CHARTS + LEADERBOARD -->
        <div class="grid lg:grid-cols-3 gap-6">

            <!-- LINE CHART -->
            <div class="bg-white rounded-xl shadow p-5 lg:col-span-2">
                <h2 class="text-lg font-semibold mb-3">Points Earned Over Time</h2>
                <canvas id="pointsChart"></canvas>
            </div>

            <!-- LEADERBOARD -->
            <div class="bg-white rounded-xl shadow p-5">
                <h2 class="text-lg font-semibold mb-3">Top Citizens</h2>

                <ol class="space-y-2">
                    <?php while ($l = mysqli_fetch_assoc($leaderboard)) { ?>
                        <li class="flex justify-between border-b pb-1">
                            <span><?php echo htmlspecialchars($l['username']); ?></span>
                            <span class="font-semibold"><?php echo $l['points']; ?> pts</span>
                        </li>
                    <?php } ?>
                </ol>
            </div>

        </div>


        <!-- ISSUE-TYPE PIE CHART -->
        <div class="bg-white rounded-xl shadow p-5 mt-8 max-w-2xl">
            <h2 class="text-lg font-semibold mb-3">Issue Type Distribution</h2>
            <canvas id="issueChart"></canvas>
        </div>


        <!-- RECENT TRANSACTIONS -->
        <div class="bg-white rounded-xl shadow p-5 mt-8">
            <h2 class="text-lg font-semibold mb-3">Recent Reward Activity</h2>

            <ul class="divide-y">
                <?php while ($t = mysqli_fetch_assoc($recent_txn)) { ?>
                    <li class="py-3 flex justify-between text-sm">
                        <span>
                            <strong><?php echo $t['username']; ?></strong>
                            <span class="text-slate-500">— <?php echo $t['reason']; ?></span>
                        </span>

                        <span class="font-semibold <?php echo ($t['points_change'] > 0) ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo ($t['points_change'] > 0 ? "+" : "") . $t['points_change']; ?>
                        </span>
                    </li>
                <?php } ?>
            </ul>
        </div>

    </main>
</div>


<script>
lucide.createIcons();

// Sidebar toggle
document.getElementById("sidebarToggle").onclick = () => {
    document.getElementById("sidebar").classList.toggle("sidebar-collapsed");
};

// CHART 1 — Points Over Time
new Chart(document.getElementById('pointsChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [{
            label: "Points Earned",
            data: <?php echo json_encode($points); ?>,
            borderWidth: 2
        }]
    },
    options: { responsive: true }
});

// CHART 2 — Issue Types
new Chart(document.getElementById('issueChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($issue_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($issue_values); ?>
        }]
    }
});
</script>

</body>
</html>
