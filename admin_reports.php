<?php
session_start();


// SECURITY CHECK — only admin allowed
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login_selection.php");
    exit;
}

// DIRECT DB CONNECTION (your current structure)
$conn = mysqli_connect("localhost", "root", "", "smart_hygiene_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// STATUS FILTER
$status_filter = isset($_GET['status']) ? $_GET['status'] : "All";

// QUERY
$query = "SELECT r.*, 
                 u.username AS citizen_name, 
                 b.location AS bin_location,
                 b.bin_id AS bin_code
          FROM reports r
          JOIN users u ON r.user_id = u.id
          JOIN bins b ON r.bin_id = b.bin_id";


if ($status_filter !== "All") {
    $query .= " WHERE r.report_status = '$status_filter'";
}

$query .= " ORDER BY r.id DESC";

$reports = mysqli_query($conn, $query);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports & Escalations - Admin Panel</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-collapsed { width: 4.5rem !important; }
        .sidebar-collapsed .sidebar-text { display: none; }
        .sidebar-collapsed .sidebar-logo-text { display: none; }
        .sidebar-collapsed .sidebar-item { justify-content: center; }
        .sidebar-collapsed .sidebar-item i { margin-right: 0; }
    </style>
</head>

<body class="bg-slate-100 min-h-screen">

<div class="flex min-h-screen">

    <!-- ====================== SIDEBAR ====================== -->
    <aside id="sidebar"
           class="bg-slate-900 text-slate-100 w-64 transition-all duration-300 flex flex-col">

        <!-- LOGO -->
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

        <!-- MENU ITEMS -->
        <nav class="flex-1 px-2 py-4 space-y-1 text-sm">
            <a href="admin_home.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Dashboard Overview</span>
            </a>

            <a href="manage_bins.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Manage Bins</span>
            </a>

            <a href="admin_reports.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg bg-slate-800 text-violet-300 font-semibold">
                <i data-lucide="clipboard-list" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Reports & Escalations</span>
            </a>

            <a href="admin_users.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="users" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Users & Operators</span>
            </a>

            <a href="admin_rewards.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="award" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Reward Analytics</span>
            </a>

            <a href="admin_prediction.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="line-chart" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Prediction & Insights</span>
            </a>

            <a href="admin_map.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="map" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">City Bin Map</span>
            </a>

            <a href="admin_logs.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="activity" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">System Logs</span>
            </a>
        </nav>

        <!-- PROFILE + LOGOUT -->
        <div class="px-3 py-3 border-t border-slate-800 flex items-center justify-between text-xs">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center mr-2">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                </div>
                <div class="sidebar-text">
                    <p class="font-semibold text-slate-100 text-xs">
                        <?php echo htmlspecialchars($_SESSION['username'] ?? "Admin"); ?>
                    </p>
                    <p class="text-slate-400 text-[11px] uppercase tracking-wide">Administrator</p>
                </div>
            </div>
            <a href="logout.php"
               class="p-2 rounded-lg hover:bg-slate-800 text-slate-300">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </aside>


    <!-- ====================== MAIN CONTENT ====================== -->
    <main class="flex-1 p-6">

        <h1 class="text-2xl font-bold text-slate-800 mb-6">Reports & Escalations</h1>

        <!-- FILTERS -->
        <div class="flex flex-wrap gap-3 mb-6">
            <?php
            $filters = ["All", "Pending", "In Progress", "Resolved", "Rejected"];
            $colors = [
                "All" => "bg-gray-200 text-gray-800",
                "Pending" => "bg-yellow-100 text-yellow-700",
                "In Progress" => "bg-blue-100 text-blue-700",
                "Resolved" => "bg-green-100 text-green-700",
                "Rejected" => "bg-red-100 text-red-700"
            ];

            foreach ($filters as $f) {
                $active = ($status_filter === $f);
                $class = $active 
                    ? "border-2 border-violet-600 bg-violet-50 text-violet-700" 
                    : $colors[$f];

                echo "<a href='?status=$f'
                      class='px-4 py-2 rounded-lg text-sm font-medium $class'>$f</a>";
            }
            ?>
        </div>

        <!-- TABLE -->
        <div class="bg-white shadow-md rounded-xl overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="p-3">ID</th>
                        <th class="p-3">Citizen</th>
                        <th class="p-3">Bin</th>
                        <th class="p-3">Issue</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Reported At</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($reports)) { ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3">#<?php echo $row['id']; ?></td>
                        <td class="p-3"><?php echo $row['citizen_name']; ?></td>
                        <td class="p-3"><?php echo $row['bin_code']; ?></td>
                        <td class="p-3"><?php echo $row['issue_type']; ?></td>

                        <td class="p-3">
                            <?php
                            $status = $row['report_status'];
                            $statusColors = [
                                "Pending" => "bg-yellow-100 text-yellow-700",
                                "In Progress" => "bg-blue-100 text-blue-700",
                                "Resolved" => "bg-green-100 text-green-700",
                                "Rejected" => "bg-red-100 text-red-700"
                            ];
                            ?>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusColors[$status]; ?>">
                                <?php echo $status; ?>
                            </span>
                        </td>

                        <td class="p-3"><?php echo $row['reported_at']; ?></td>

                        <td class="p-3 text-right">
                            <!-- VIEW -->
                            <button 
                                onclick="openDetailsModal(
                                    '<?php echo $row['id']; ?>',
                                    '<?php echo $row['citizen_name']; ?>',
                                    '<?php echo $row['bin_code']; ?>',
                                    '<?php echo $row['issue_type']; ?>',
                                    `<?php echo addslashes($row['details']); ?>`,
                                    '<?php echo $row['report_status']; ?>',
                                    '<?php echo $row['reported_at']; ?>'
                                )"
                                class="text-blue-600 hover:underline text-sm mr-4">
                                View
                            </button>

                            <!-- DELETE -->
                            <a href="delete_report.php?id=<?php echo $row['id']; ?>"
                               onclick="return confirm('Delete this report? Points will be reversed automatically.')"
                               class="text-red-600 hover:underline text-sm">
                                Delete
                            </a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>

            </table>
        </div>
    </main>
</div>


<!-- DETAILS MODAL -->
<div id="detailsModal"
     class="hidden fixed inset-0 bg-black bg-opacity-40 flex justify-center items-center">
    
    <div class="bg-white p-8 rounded-xl w-full max-w-lg shadow-xl">

        <h3 class="text-xl font-bold mb-4">Report Details</h3>

        <div id="modalContent" class="text-sm space-y-2"></div>

        <button onclick="closeDetailsModal()" 
                class="mt-4 px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800">
            Close
        </button>
    </div>
</div>

<script>
lucide.createIcons();

// MODAL FUNCTIONS
function openDetailsModal(id, citizen, bin, issue, details, status, date) {
    document.getElementById("detailsModal").classList.remove("hidden");

    document.getElementById("modalContent").innerHTML = `
        <p><strong>Report ID:</strong> #${id}</p>
        <p><strong>Citizen:</strong> ${citizen}</p>
        <p><strong>Bin:</strong> ${bin}</p>
        <p><strong>Issue:</strong> ${issue}</p>
        <p><strong>Details:</strong> ${details}</p>
        <p><strong>Status:</strong> ${status}</p>
        <p><strong>Reported At:</strong> ${date}</p>
    `;
}

function closeDetailsModal() {
    document.getElementById("detailsModal").classList.add("hidden");
}
</script>

</body>
</html>
