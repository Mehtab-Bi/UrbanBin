<?php
session_start();
// Security check: Only allow logged-in admins to view this page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: admin_login.php");
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db";

$view = $_GET['view'] ?? 'dashboard';

$conn = new mysqli($servername, $username, $password, $dbname);

$db_connected = true;
$admin_message = '';
$admin_message_type = '';
$dashboard_stats = [];
$pending_reports = [];

if ($conn->connect_error) {
    $db_connected = false;
    $admin_message = "Database connection failed: " . $conn->connect_error;
    $admin_message_type = 'error';
}

// --- Report Handling Logic (Admin) ---
// Admin can delete any report, for comprehensive control
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_report_id']) && $db_connected) {
    $report_id = (int)$_POST['delete_report_id'];
    
    // Start Transaction for safe deletion
    $conn->begin_transaction();
    try {
        // 1. Get the points awarded to reverse the transaction
        $sql_get_points = "SELECT points_awarded, user_id FROM reports WHERE id = ?";
        $stmt_get_points = $conn->prepare($sql_get_points);
        $stmt_get_points->bind_param("i", $report_id);
        $stmt_get_points->execute();
        $result_points = $stmt_get_points->get_result();
        
        if ($row = $result_points->fetch_assoc()) {
            $points_to_reverse = $row['points_awarded'];
            $reporter_user_id = $row['user_id'];

            // 2. Reverse points in 'users' table (since points were added there)
            $sql_reverse_user_points = "UPDATE users SET points = points - ? WHERE id = ?";
            $stmt_reverse_user_points = $conn->prepare($sql_reverse_user_points);
            $stmt_reverse_user_points->bind_param("ii", $points_to_reverse, $reporter_user_id);
            $stmt_reverse_user_points->execute();

            // 3. Log the reversal in 'point_transactions'
            $reverse_reason = "Point reversal due to Admin deletion of report ID " . $report_id;
            $negative_points = -abs($points_to_reverse);
            $sql_log_reversal = "INSERT INTO point_transactions (user_id, points_change, reason, transaction_date) VALUES (?, ?, ?, NOW())";
            $stmt_log_reversal = $conn->prepare($sql_log_reversal);
            $stmt_log_reversal->bind_param("iis", $reporter_user_id, $negative_points, $reverse_reason);
            $stmt_log_reversal->execute();
            
            // 4. Delete the report itself
            $sql_delete = "DELETE FROM reports WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $report_id);
            $stmt_delete->execute();
            
            $conn->commit();
            $admin_message = "Report ID $report_id deleted and points successfully reversed.";
            $admin_message_type = 'success';

        } else {
            // Report not found case
            $conn->rollback();
            $admin_message = "Error: Report ID $report_id not found.";
            $admin_message_type = 'error';
        }
        $stmt_get_points->close();
        if(isset($stmt_reverse_user_points)) $stmt_reverse_user_points->close();
        if(isset($stmt_log_reversal)) $stmt_log_reversal->close();
        if(isset($stmt_delete)) $stmt_delete->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        $admin_message = "Transaction Failed: " . $e->getMessage();
        $admin_message_type = 'error';
    }
}


// --- Data Fetching ---
if ($db_connected) {
    // 1. Dashboard Stats (Admin only)
    if ($view === 'dashboard') {
        $sql_stats = "
            SELECT 
                (SELECT COUNT(*) FROM users WHERE role = 'Citizen') as total_citizens,
                (SELECT COUNT(*) FROM reports WHERE status = 'Pending') as pending_reports_count,
                (SELECT COUNT(*) FROM reports) as total_reports
        ";
        $result_stats = $conn->query($sql_stats);
        if ($result_stats) {
            $dashboard_stats = $result_stats->fetch_assoc();
        }
    }

    // 2. Pending Reports (For 'reports' view)
    if ($view === 'reports' || $admin_message_type != '') { // Refetch after action
        $sql_reports = "
            SELECT 
                r.id, r.bin_id, r.issue_type, r.details, r.submission_date, r.status, u.username as reporter
            FROM reports r
            JOIN users u ON r.user_id = u.id
            WHERE r.status = 'Pending'
            ORDER BY r.submission_date ASC
        ";
        $result_reports = $conn->query($sql_reports);
        if ($result_reports) {
            while ($row = $result_reports->fetch_assoc()) {
                $pending_reports[] = $row;
            }
        }
    }
    
    // Close connection after all fetching/processing
    $conn->close();
}


// Helper function to set tab style
function setTabStyle($tabName, $currentView) {
    if ($tabName === $currentView) {
        return 'bg-indigo-700 text-white shadow-md';
    } else {
        return 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart Hygiene</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .tab-button { transition: background-color 0.15s; }
        .alert-error { background-color: #fee2e2; border-left-color: #ef4444; color: #b91c1c; }
        .alert-success { background-color: #d1fae5; border-left-color: #10b981; color: #065f46; }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8">

    <!-- Header and Navigation -->
    <header class="max-w-6xl mx-auto mb-8">
        <nav class="flex justify-between items-center bg-white p-4 rounded-xl shadow-lg">
            <h1 class="text-2xl font-bold text-indigo-800">Admin Control Panel</h1>
            <div class="flex space-x-3">
                <a href="admin_dashboard.php?view=dashboard" class="py-2 px-4 rounded-lg text-sm font-medium tab-button <?php echo setTabStyle('dashboard', $view); ?>">
                    <i data-lucide="layout-dashboard" class="w-4 h-4 inline mr-1"></i> Dashboard
                </a>
                
                <!-- NEW REPORTS TAB -->
                <a href="admin_dashboard.php?view=reports" class="py-2 px-4 rounded-lg text-sm font-medium tab-button <?php echo setTabStyle('reports', $view); ?>">
                    <i data-lucide="bell" class="w-4 h-4 inline mr-1"></i> Reports 
                    <?php if (!empty($dashboard_stats['pending_reports_count'])): ?>
                         <span class="ml-1 px-2 py-0.5 text-xs font-semibold text-red-700 bg-red-100 rounded-full"><?php echo $dashboard_stats['pending_reports_count']; ?></span>
                    <?php endif; ?>
                </a>

                <a href="logout.php" class="py-2 px-4 rounded-lg text-sm font-medium bg-red-600 text-white hover:bg-red-700">
                    <i data-lucide="log-out" class="w-4 h-4 inline mr-1"></i> Log Out
                </a>
            </div>
        </nav>
        
        <!-- MESSAGE DISPLAY -->
        <?php if (!empty($admin_message)): ?>
            <?php 
            $alert_class = $admin_message_type === 'success' ? 'alert-success border-green-500' : 'alert-error border-red-500';
            ?>
            <div class="mt-4 p-3 border-l-4 rounded-lg shadow-sm <?php echo $alert_class; ?>">
                <p class="font-medium"><?php echo htmlspecialchars($admin_message); ?></p>
            </div>
        <?php endif; ?>
    </header>

    <!-- Main Content Area -->
    <main class="max-w-6xl mx-auto bg-white p-6 md:p-10 rounded-xl shadow-xl">
        
        <?php if ($view === 'dashboard'): ?>
            <!-- Dashboard View -->
            <h2 class="text-3xl font-extrabold text-gray-900 mb-6 border-b pb-2">System Overview</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Total Citizens Card -->
                <div class="bg-indigo-500 text-white p-6 rounded-xl shadow-lg flex justify-between items-center">
                    <div>
                        <p class="text-sm font-medium opacity-80">Total Registered Citizens</p>
                        <p class="text-4xl font-bold mt-1"><?php echo number_format($dashboard_stats['total_citizens'] ?? 0); ?></p>
                    </div>
                    <i data-lucide="users" class="w-10 h-10 opacity-70"></i>
                </div>

                <!-- Pending Reports Card -->
                <div class="bg-yellow-500 text-white p-6 rounded-xl shadow-lg flex justify-between items-center">
                    <div>
                        <p class="text-sm font-medium opacity-80">Pending Bin Reports</p>
                        <p class="text-4xl font-bold mt-1"><?php echo number_format($dashboard_stats['pending_reports_count'] ?? 0); ?></p>
                    </div>
                    <i data-lucide="alert-triangle" class="w-10 h-10 opacity-70"></i>
                </div>

                <!-- Total Reports Card -->
                <div class="bg-green-500 text-white p-6 rounded-xl shadow-lg flex justify-between items-center">
                    <div>
                        <p class="text-sm font-medium opacity-80">Total Reports Submitted</p>
                        <p class="text-4xl font-bold mt-1"><?php echo number_format($dashboard_stats['total_reports'] ?? 0); ?></p>
                    </div>
                    <i data-lucide="list-checks" class="w-10 h-10 opacity-70"></i>
                </div>
            </div>
            
            <p class="mt-8 text-lg text-gray-600">Use the navigation bar above to view, resolve, or manage pending reports and other system settings.</p>

        <?php elseif ($view === 'reports'): ?>
            <!-- Reports View -->
            <h2 class="text-3xl font-extrabold text-gray-900 mb-6 border-b pb-2 flex items-center">
                <i data-lucide="clipboard-list" class="w-7 h-7 mr-2 text-indigo-600"></i>
                Pending Citizen Reports
            </h2>
            
            <?php if (empty($pending_reports)): ?>
                <div class="text-center p-10 bg-gray-50 rounded-lg border border-gray-200">
                    <i data-lucide="check-circle" class="w-12 h-12 text-green-500 mx-auto"></i>
                    <p class="mt-4 text-xl font-semibold text-gray-700">No Pending Reports!</p>
                    <p class="text-gray-500">The queue is clear. All reported issues have been addressed.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto shadow-md rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Bin ID/Loc</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Issue Type</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Reported By</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Submission Date</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Details</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php foreach ($pending_reports as $report): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo htmlspecialchars($report['id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-indigo-600 font-semibold"><?php echo htmlspecialchars($report['bin_id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-500 font-medium"><?php echo htmlspecialchars($report['issue_type']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($report['reporter']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo (new DateTime($report['submission_date']))->format('Y-m-d H:i'); ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <!-- Details Button/Modal Placeholder -->
                                        <button onclick="alert('Details for Report #<?php echo $report['id']; ?>: \n<?php echo str_replace(["\r\n", "\n"], ' ', htmlspecialchars($report['details'])); ?>')" class="text-indigo-500 hover:text-indigo-700 text-sm font-medium">View</button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <!-- Admin Action: Delete (including point reversal) -->
                                        <form method="POST" action="admin_dashboard.php?view=reports" onsubmit="return confirm('Are you sure you want to DELETE report #<?php echo $report['id']; ?>? This will REVERSE the points awarded to the citizen.');" class="inline-block">
                                            <input type="hidden" name="delete_report_id" value="<?php echo $report['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 ml-2 py-1 px-3 rounded-md border border-red-200 hover:bg-red-50 transition duration-150">
                                                Delete
                                            </button>
                                        </form>
                                        
                                        <!-- Note: Admin can delete. Operators will have a "Resolve" button. -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>