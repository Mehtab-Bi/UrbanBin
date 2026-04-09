<?php
session_start();
include "log_helper.php";
addLog($_SESSION['role'], "Admin opened System Logs page");

// SECURITY: Only admin can access this page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login_selection.php");
    exit;
}

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "smart_hygiene_db";

$stats = [
    'total_citizens'   => 0,
    'total_operators'  => 0,
    'total_reports'    => 0,
    'pending_reports'  => 0,
    'alert_bins'       => 0,
];

$recent_reports = [];
$db_error = '';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $db_error = "Database connection failed: " . $conn->connect_error;
} else {
    // Dashboard stats
    $sql_stats = "
        SELECT
            (SELECT COUNT(*) FROM users WHERE LOWER(role) = 'citizen')   AS total_citizens,
            (SELECT COUNT(*) FROM users WHERE LOWER(role) = 'operator')  AS total_operators,
            (SELECT COUNT(*) FROM reports)                               AS total_reports,
            (SELECT COUNT(*) FROM reports WHERE report_status = 'Pending') AS pending_reports,
            (SELECT COUNT(*) FROM bins WHERE status = 'Alert')           AS alert_bins
    ";
    if ($res = $conn->query($sql_stats)) {
        $row = $res->fetch_assoc();
        if ($row) {
            $stats = array_merge($stats, $row);
        }
        $res->free();
    }

    // Recent 5 reports
    $sql_recent = "
        SELECT r.id, r.bin_id, r.issue_type, r.report_status, r.reported_at, u.username
        FROM reports r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.reported_at DESC
        LIMIT 5
    ";
    if ($res2 = $conn->query($sql_recent)) {
        while ($r = $res2->fetch_assoc()) {
            $recent_reports[] = $r;
        }
        $res2->free();
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Home - Smart Hygiene</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tailwind + Inter + Icons -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Chart.js (for small overview chart later if needed) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }

        /* Collapsible sidebar behavior */
        .sidebar-collapsed {
            width: 4.5rem !important;
        }
        .sidebar-collapsed .sidebar-text {
            display: none;
        }
        .sidebar-collapsed .sidebar-logo-text {
            display: none;
        }
        .sidebar-collapsed .sidebar-item {
            justify-content: center;
        }
        .sidebar-collapsed .sidebar-item i {
            margin-right: 0;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">

<div class="flex min-h-screen">

    <!-- SIDEBAR -->
    <aside id="sidebar"
           class="bg-slate-900 text-slate-100 w-64 transition-all duration-300 flex flex-col">

        <!-- Logo + Toggle -->
        <div class="flex items-center justify-between px-4 py-4 border-b border-slate-800">
            <div class="flex items-center">
                <div class="bg-violet-600 rounded-lg p-2 mr-2 flex items-center justify-center">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                </div>
                <div class="sidebar-logo-text">
                    <p class="text-xs uppercase tracking-widest text-slate-400">Smart Hygiene</p>
                    <p class="text-sm font-semibold">Admin Console</p>
                </div>
            </div>
            <button id="sidebarToggle"
                    class="p-2 rounded-lg hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-violet-500">
                <i data-lucide="panel-left-close" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Menu -->
        <nav class="flex-1 px-2 py-4 space-y-1 text-sm">
            <a href="admin_home.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg bg-slate-800 text-violet-300 font-semibold">
                <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Dashboard Overview</span>
            </a>

            <a href="manage_bins.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-50">
                <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Manage Bins</span>
            </a>

            <a href="admin_reports.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-50">
                <i data-lucide="clipboard-list" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Reports & Escalations</span>
            </a>

            <a href="admin_users.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-50">
                <i data-lucide="users" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Users & Operators</span>
            </a>

            <a href="admin_rewards.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-50">
                <i data-lucide="award" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Reward Analytics</span>
            </a>

            <a href="admin_prediction.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-50">
                <i data-lucide="line-chart" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Prediction & Insights</span>
            </a>

            <a href="admin_map.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-50">
                <i data-lucide="map" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">City Bin Map</span>
            </a>

            <a href="admin_logs.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-50">
                <i data-lucide="activity" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">System Logs</span>
            </a>
        </nav>

        <!-- Bottom: Profile + Logout -->
        <div class="px-3 py-3 border-t border-slate-800 flex items-center justify-between text-xs">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center mr-2">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                </div>
                <div class="sidebar-text">
                    <p class="font-semibold text-slate-100 text-xs">
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'admin'); ?>
                    </p>
                    <p class="text-slate-400 text-[11px] uppercase tracking-wide">Administrator</p>
                </div>
            </div>
            <a href="logout.php"
               class="p-2 rounded-lg hover:bg-slate-800 text-slate-300"
               title="Logout">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-4 md:p-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">
                    Admin Dashboard
                </h1>
                <p class="text-slate-500 text-sm mt-1">
                    Central overview of citizens, operators, bins and reports.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                    <i data-lucide="check-circle-2" class="w-3 h-3 mr-1"></i>
                    Live Monitoring Enabled
                </span>
            </div>
        </div>

        <?php if ($db_error): ?>
            <div class="mb-6 p-3 border-l-4 border-red-500 bg-red-50 text-red-700 text-sm rounded-md">
                <?php echo htmlspecialchars($db_error); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Citizens</p>
                    <i data-lucide="user-round" class="w-4 h-4 text-violet-500"></i>
                </div>
                <p class="mt-2 text-2xl font-bold text-slate-900">
                    <?php echo (int)$stats['total_citizens']; ?>
                </p>
                <p class="text-xs text-slate-400 mt-1">Registered reporters</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Operators</p>
                    <i data-lucide="truck" class="w-4 h-4 text-amber-500"></i>
                </div>
                <p class="mt-2 text-2xl font-bold text-slate-900">
                    <?php echo (int)$stats['total_operators']; ?>
                </p>
                <p class="text-xs text-slate-400 mt-1">Field staff in system</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Reports</p>
                    <i data-lucide="clipboard-list" class="w-4 h-4 text-sky-500"></i>
                </div>
                <p class="mt-2 text-2xl font-bold text-slate-900">
                    <?php echo (int)$stats['total_reports']; ?>
                </p>
                <p class="text-xs text-slate-400 mt-1">All time reported issues</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Pending & Alerts</p>
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-red-500"></i>
                </div>
                <p class="mt-2 text-2xl font-bold text-slate-900">
                    <?php echo (int)$stats['pending_reports']; ?>
                    <span class="text-xs text-slate-500">reports</span>
                </p>
                <p class="text-xs text-slate-400 mt-1">
                    Active bin alerts: <span class="font-semibold text-red-500"><?php echo (int)$stats['alert_bins']; ?></span>
                </p>
            </div>
        </div>

        <!-- Two-column layout -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Recent Reports -->
            <section class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-slate-700 flex items-center">
                        <i data-lucide="clock-3" class="w-4 h-4 mr-1.5 text-violet-500"></i>
                        Recent Citizen Reports
                    </h2>
                    <a href="admin_reports.php" class="text-xs text-violet-600 hover:underline">
                        View all
                    </a>
                </div>

                <?php if (empty($recent_reports)): ?>
                    <p class="text-sm text-slate-400 italic">No reports have been submitted yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-slate-500 border-b border-slate-100">
                                    <th class="py-2 pr-3 text-left">ID</th>
                                    <th class="py-2 px-3 text-left">Citizen</th>
                                    <th class="py-2 px-3 text-left">Bin</th>
                                    <th class="py-2 px-3 text-left">Issue</th>
                                    <th class="py-2 px-3 text-left">Status</th>
                                    <th class="py-2 pl-3 text-right">Reported</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($recent_reports as $rep): ?>
                                    <?php
                                        $status_color = 'bg-slate-100 text-slate-700';
                                        if ($rep['report_status'] === 'Pending')      $status_color = 'bg-amber-100 text-amber-800';
                                        elseif ($rep['report_status'] === 'In Progress') $status_color = 'bg-sky-100 text-sky-800';
                                        elseif ($rep['report_status'] === 'Resolved')    $status_color = 'bg-emerald-100 text-emerald-800';
                                        elseif ($rep['report_status'] === 'Rejected')    $status_color = 'bg-rose-100 text-rose-800';
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-2 pr-3 font-semibold text-slate-800">#<?php echo (int)$rep['id']; ?></td>
                                        <td class="py-2 px-3 text-slate-700">
                                            <?php echo htmlspecialchars($rep['username']); ?>
                                        </td>
                                        <td class="py-2 px-3 text-slate-700">
                                            <?php echo htmlspecialchars($rep['bin_id']); ?>
                                        </td>
                                        <td class="py-2 px-3 text-slate-700">
                                            <?php echo htmlspecialchars($rep['issue_type']); ?>
                                        </td>
                                        <td class="py-2 px-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?php echo $status_color; ?>">
                                                <?php echo htmlspecialchars($rep['report_status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-2 pl-3 text-right text-slate-500">
                                            <?php
                                                $dt = new DateTime($rep['reported_at']);
                                                echo $dt->format('Y-m-d H:i');
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Small Analytics card -->
            <section class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
                <h2 class="text-sm font-semibold text-slate-700 flex items-center mb-3">
                    <i data-lucide="line-chart" class="w-4 h-4 mr-1.5 text-emerald-500"></i>
                    Quick Analytics
                </h2>
                <p class="text-xs text-slate-500 mb-2">
                    This section can show trends like daily reports or fill-level trends over time.
                </p>
                <canvas id="reportsChart" class="w-full h-32"></canvas>
            </section>
        </div>

        <!-- Footer -->
        <p class="mt-8 text-[11px] text-slate-400">
            Smart Urban Hygiene System · Admin Panel · <?php echo date('Y'); ?>
        </p>
    </main>
</div>

<script>
    lucide.createIcons();

    // Sidebar toggle
    const sidebar     = document.getElementById('sidebar');
    const sidebarBtn  = document.getElementById('sidebarToggle');

    sidebarBtn.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-collapsed');
    });

    // Simple tiny chart (just to make page feel alive)
    const ctx = document.getElementById('reportsChart');
    if (ctx && typeof Chart !== 'undefined') {
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Total', 'Pending', 'Alerts'],
                datasets: [{
                    label: 'Counts',
                    data: [
                        <?php echo (int)$stats['total_reports']; ?>,
                        <?php echo (int)$stats['pending_reports']; ?>,
                        <?php echo (int)$stats['alert_bins']; ?>
                    ]
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
</script>
</body>
</html>
