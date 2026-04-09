<?php
session_start();

// Database Connection Details
$servername = "localhost";
$username = "root";
$password = ""; 
$dbname = "smart_hygiene_db";

// Check if user is logged in, otherwise redirect to login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// ----------------------------------------------------
// DB Connection and Data Fetching (for Report)
// ----------------------------------------------------
$report_data = [];
$total_bins = 0;
$alert_count = 0;

$conn = new mysqli($servername, $username, $password, $dbname);

if (!$conn->connect_error) {
    // Fetch all bins data
    $sql = "SELECT bin_id, location, capacity_percent, last_updated, status FROM bins ORDER BY bin_id";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $total_bins = $result->num_rows;
        while($row = $result->fetch_assoc()) {
            $report_data[] = $row;
            if ($row['status'] === 'Alert') {
                $alert_count++;
            }
        }
    }
    $conn->close();
}
session_write_close();

// Set current page for navigation highlighting
$currentPage = 'report';
// ----------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f0f4f8; }
        .nav-link { padding: 0.5rem 1rem; border-radius: 0.5rem; }
        .nav-link.active { background-color: #4f46e5; color: white; }
        /* Print styles for a clean PDF */
        @media print {
            .no-print { display: none; }
            body { background-color: white !important; padding: 0 !important; }
            .report-card { box-shadow: none !important; border: 1px solid #ccc !important; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 8px; }
        }
    </style>
</head>
<body class="p-4 md:p-8">

    <!-- Header & Navigation (No Print) -->
    <header class="no-print mb-10 pb-4 border-b border-gray-200">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
            <h1 class="text-3xl font-extrabold text-gray-900 flex items-center mb-4 sm:mb-0">
                <svg class="w-8 h-8 mr-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17.25v1.007a3 3 0 01-.879 2.122l-.723.723a1.5 1.5 0 01-1.06.44h.75a7.5 7.5 0 007.5 7.5h.75a1.5 1.5 0 01-1.06-.44l-.723-.723A3 3 0 0115 18.257V17.25m3.75-2.5h-.007M10.971 7.27a.75.75 0 00-1.06 0l-3.375 3.375.394 3.945c.046.466.52.791.988.587l2.88-1.284a.75.75 0 01.65.006l2.88 1.284c.468.204.942-.121.988-.587l.394-3.945-3.375-3.375z"></path></svg>
                Smart Hygiene System
            </h1>
            <div class="flex items-center space-x-4">
                <span class="text-gray-600">User: <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($_SESSION['username']); ?></span></span>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-lg font-medium shadow transition duration-150">Logout</a>
            </div>
        </div>

        <!-- Main Navigation Bar -->
        <nav class="flex flex-wrap gap-4 border-t pt-4">
            <a href="index.php" class="nav-link text-gray-600 hover:bg-indigo-50 hover:text-indigo-600">Dashboard</a>
            <a href="sensor_simulator.php" class="nav-link text-gray-600 hover:bg-indigo-50 hover:text-indigo-600">Sensor Simulator</a>
            <a href="awareness.php" class="nav-link text-gray-600 hover:bg-indigo-50 hover:text-indigo-600">Hygiene Awareness</a>
            <a href="customer_report.php" class="nav-link text-indigo-600 hover:bg-indigo-50 active">Customer Report</a>
            <a href="manage_bins.php" class="nav-link text-gray-600 hover:bg-indigo-50 hover:text-indigo-600">Manage Bins</a>
        </nav>
    </header>

    <!-- Main Report Content -->
    <main class="container mx-auto">
        <h2 class="text-2xl font-bold text-gray-700 mb-6">Customer Bin Report - <?php echo date("F j, Y"); ?></h2>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="report-card bg-white p-6 rounded-xl shadow-lg border-l-4 border-indigo-500">
                <p class="text-sm text-gray-500 font-medium">Total Bins Monitored</p>
                <p class="text-3xl font-bold text-gray-800"><?php echo $total_bins; ?></p>
            </div>
            <div class="report-card bg-white p-6 rounded-xl shadow-lg border-l-4 border-red-500">
                <p class="text-sm text-gray-500 font-medium">Bins Currently in Alert</p>
                <p class="text-3xl font-bold text-red-600"><?php echo $alert_count; ?></p>
            </div>
            <div class="report-card bg-white p-6 rounded-xl shadow-lg border-l-4 border-green-500">
                <p class="text-sm text-gray-500 font-medium">Overall System Status</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $alert_count === 0 ? 'Optimal' : 'Needs Attention'; ?></p>
            </div>
        </div>

        <!-- Print Button -->
        <div class="no-print mb-6">
            <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-150 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z"></path></svg>
                Download/Print Report (PDF Ready)
            </button>
        </div>

        <!-- Detailed Table -->
        <div class="bg-white p-6 rounded-xl shadow-lg overflow-x-auto">
            <h3 class="text-xl font-semibold mb-4 border-b pb-2">Detailed Bin Status Log</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bin ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Fill Level (%)</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($report_data) === 0): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No data available for this report.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($report_data as $bin): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($bin['bin_id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($bin['location']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-bold <?php echo $bin['capacity_percent'] >= 90 ? 'text-red-600' : 'text-gray-800'; ?>"><?php echo htmlspecialchars($bin['capacity_percent']); ?>%</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?php echo htmlspecialchars($bin['last_updated']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                        if ($bin['status'] == 'Alert') echo 'bg-red-100 text-red-800'; 
                                        elseif ($bin['status'] == 'Normal') echo 'bg-green-100 text-green-800'; 
                                        else echo 'bg-gray-100 text-gray-800'; 
                                    ?>">
                                    <?php echo htmlspecialchars($bin['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <footer class="mt-10 pt-4 border-t border-gray-200 text-center text-sm text-gray-500">
            This report was generated on <?php echo date("Y-m-d H:i:s"); ?>.
        </footer>
    </main>
</body>
</html>
