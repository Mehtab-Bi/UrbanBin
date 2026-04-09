<?php
session_start();

// Only allow logged-in citizens
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'citizen') {
    header("Location: citizen_portal.php");
    exit;
}

// Handle session messages from handler
$system_message = $_SESSION['message'] ?? '';
$message_type   = $_SESSION['message_type'] ?? '';

unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Issue - Smart Hygiene</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f9; }
    </style>
</head>

<body class="min-h-screen p-6">

<header class="max-w-3xl mx-auto mb-8">
    <div class="flex justify-between items-center bg-white p-4 rounded-xl shadow">
        <h1 class="text-xl font-bold text-indigo-600">Report Bin Issue</h1>
        <a href="citizen_dashboard.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm">
            ← Back to Dashboard
        </a>
    </div>

    <?php if (!empty($system_message)): ?>
        <div class="mt-4 p-3 rounded-lg border-l-4 
            <?= $message_type === 'success' ? 'bg-green-100 border-green-500 text-green-700'
                                            : 'bg-red-100 border-red-500 text-red-700' ?>">
            <?= htmlspecialchars($system_message); ?>
        </div>
    <?php endif; ?>
</header>

<main class="max-w-3xl mx-auto bg-white p-8 rounded-xl shadow-lg">

    <h2 class="text-2xl font-bold mb-4 flex items-center">
        <i data-lucide="alert-triangle" class="w-6 h-6 text-red-500 mr-2"></i>
        Submit a New Bin Issue
    </h2>

    <p class="text-gray-600 mb-6">
        You will earn <span class="font-semibold text-indigo-600">+10 reward points</span> for each valid report.
    </p>

    <form action="citizen_report_handler.php" method="POST" class="space-y-6">

        <div>
            <label class="block text-sm font-medium mb-1">Bin ID <span class="text-red-500">*</span></label>
            <input 
                type="text" 
                name="bin_id" 
                required
                placeholder="e.g., BIN103"
                class="w-full px-4 py-2 border rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
            >
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Issue Type <span class="text-red-500">*</span></label>
            <select 
                name="report_type" 
                required
                class="w-full px-4 py-2 border rounded-lg bg-white focus:ring-indigo-500 focus:border-indigo-500"
            >
                <option value="">-- Select Issue Type --</option>
                <option value="Overflow">Bin Overflowing</option>
                <option value="Damaged">Damaged / Broken</option>
                <option value="Blockage">Sensor / Lid Blockage</option>
                <option value="Misplaced">Bin Missing / Misplaced</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Additional Details</label>
            <textarea 
                name="description" rows="4"
                placeholder="Describe the issue (optional)"
                class="w-full px-4 py-2 border rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
            ></textarea>
        </div>

        <button 
            type="submit"
            name="submit_report"
            class="w-full py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition shadow"
        >
            <i data-lucide="send" class="w-5 h-5 inline-block mr-2"></i>
            Submit Report
        </button>

    </form>

</main>

<script>lucide.createIcons();</script>
</body>
</html>
