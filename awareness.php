<?php
session_start();

// Security: Only logged-in citizens allowed
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'citizen') {
    header("Location: citizen_portal.php");
    exit;
}

$view = "awareness";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hygiene Awareness - Smart Hygiene System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f6fa; }
        .nav-btn { padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 600; transition: 0.2s; }
        .nav-active { background-color: #4f46e5; color: white; }
        .nav-normal { background-color: #e5e7eb; color: #374151; }
        .nav-normal:hover { background-color: #d1d5db; }
        .section-card { transition: transform 0.25s ease, box-shadow 0.25s ease; }
        .section-card:hover { transform: translateY(-4px); box-shadow: 0 12px 22px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="p-4 md:p-8">

<!-- NAVBAR -->
<header class="max-w-4xl mx-auto mb-8">
    <nav class="flex justify-between items-center bg-white p-4 rounded-xl shadow-lg">
        <h1 class="text-xl font-bold text-indigo-600 hidden sm:block">Citizen Portal</h1>

        <div class="flex space-x-2 sm:space-x-4">
            <a href="citizen_dashboard.php?view=dashboard"
               class="nav-btn <?php echo ($view=='dashboard')?'nav-active':'nav-normal'; ?>">
                Rewards
            </a>

            <a href="citizen_dashboard.php?view=report"
               class="nav-btn <?php echo ($view=='report')?'nav-active':'nav-normal'; ?>">
                Report Bin
            </a>

            <a href="awareness.php"
               class="nav-btn <?php echo ($view=='awareness')?'nav-active':'nav-normal'; ?>">
                Awareness
            </a>

            <a href="logout.php" class="nav-btn bg-red-500 text-white hover:bg-red-600">
                Logout
            </a>
        </div>
    </nav>
</header>

<!-- CONTENT -->
<div class="max-w-4xl mx-auto bg-white p-8 rounded-xl shadow-xl">

    <!-- HEADER SECTION -->
    <div class="bg-indigo-600 text-white p-8 rounded-xl shadow-lg mb-10 text-center">
        <h2 class="text-3xl md:text-4xl font-extrabold mb-3">Hygiene Awareness & Best Practices</h2>
        <p class="text-indigo-200 max-w-3xl mx-auto text-sm md:text-base">
            Learn how waste management, personal hygiene, and environmental practices help create cleaner,
            safer, and healthier communities. Your awareness strengthens the Smart Hygiene System.
        </p>
    </div>

    <!-- SECTION 1: HEALTH RISKS -->
    <div class="mb-12">
        <h3 class="text-2xl font-bold text-gray-800 flex items-center mb-4">
            <i data-lucide="alert-triangle" class="w-7 h-7 text-red-500 mr-2"></i>
            Health Risks of Poor Waste Management
        </h3>
        <p class="text-gray-600 mb-6">
            Unmanaged waste leads to multiple health threats. Knowing these risks helps you identify
            issues faster and report them responsibly.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="section-card bg-red-50 border-l-4 border-red-500 p-5 rounded-lg">
                <p class="font-bold text-red-700">Disease Carriers</p>
                <p class="text-sm text-gray-700 mt-1">
                    Mosquitoes, flies, and rodents thrive in open garbage, spreading dengue, malaria, and cholera.
                </p>
            </div>
            <div class="section-card bg-red-50 border-l-4 border-red-500 p-5 rounded-lg">
                <p class="font-bold text-red-700">Toxic Air Contaminants</p>
                <p class="text-sm text-gray-700 mt-1">
                    Decomposing waste releases harmful gases and dust, causing breathing problems and infections.
                </p>
            </div>
            <div class="section-card bg-red-50 border-l-4 border-red-500 p-5 rounded-lg">
                <p class="font-bold text-red-700">Water Pollution</p>
                <p class="text-sm text-gray-700 mt-1">
                    Waste leachate contaminates groundwater, affecting drinking and household water safety.
                </p>
            </div>
            <div class="section-card bg-red-50 border-l-4 border-red-500 p-5 rounded-lg">
                <p class="font-bold text-red-700">Spread of Infections</p>
                <p class="text-sm text-gray-700 mt-1">
                    Overflowing bins increase the risk of community-wide bacterial and viral outbreaks.
                </p>
            </div>
        </div>
    </div>

    <!-- SECTION 2: PERSONAL HYGIENE -->
    <div class="mb-12">
        <h3 class="text-2xl font-bold text-gray-800 flex items-center mb-4">
            <i data-lucide="shield-check" class="w-7 h-7 text-blue-500 mr-2"></i>
            5 Pillars of Personal Hygiene
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            <?php
            $pillars = [
                ["Hand Hygiene", "Wash hands regularly with soap for at least 20 seconds.", "bg-blue-100 text-blue-800"],
                ["Respiratory Cleanliness", "Cover your mouth when coughing or sneezing to avoid spread.", "bg-green-100 text-green-800"],
                ["Proper Waste Disposal", "Always use bins and avoid littering to prevent contamination.", "bg-yellow-100 text-yellow-800"],
                ["Clean Living Spaces", "Maintain clean homes and surroundings to reduce infections.", "bg-purple-100 text-purple-800"],
                ["Safe Food Practices", "Store, cook, and handle food hygienically to avoid foodborne diseases.", "bg-pink-100 text-pink-800"],
                ["Stay Hydrated", "Clean drinking water helps maintain immunity and overall health.", "bg-indigo-100 text-indigo-800"]
            ];
            foreach ($pillars as $p):
            ?>
                <div class="section-card p-5 rounded-lg shadow-sm <?php echo $p[2]; ?>">
                    <p class="font-bold text-lg"><?php echo $p[0]; ?></p>
                    <p class="text-sm mt-1"><?php echo $p[1]; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SECTION 3: SMART SYSTEM IMPACT -->
    <div class="mb-10">
        <h3 class="text-2xl font-bold text-gray-800 flex items-center mb-4">
            <i data-lucide="cpu" class="w-7 h-7 text-green-600 mr-2"></i>
            Smart Waste Management & Your Role
        </h3>

        <div class="space-y-4 text-gray-700">
            <p class="text-gray-600">
                The Smart Hygiene System uses sensors and analytics to ensure timely waste collection and prevent overflow.
            </p>

            <ul class="list-disc list-inside space-y-2 text-gray-600">
                <li><strong>Real-Time Alerts:</strong> Identifies overflowing bins instantly.</li>
                <li><strong>Efficient Routes:</strong> Saves fuel and reduces pollution.</li>
                <li><strong>Cleaner Public Spaces:</strong> Ensures garbage is removed before it becomes hazardous.</li>
            </ul>

            <p class="font-semibold mt-4">Your contribution matters:</p>
            <ul class="list-disc list-inside space-y-2 text-gray-600">
                <li>Report damaged, missing, or overflowing bins through this system.</li>
                <li>Segregate waste properly (wet, dry, recyclable).</li>
                <li>Keep surroundings litter-free.</li>
            </ul>
        </div>
    </div>

    <!-- CTA -->
    <div class="bg-indigo-50 p-6 rounded-xl text-center border border-indigo-200">
        <h3 class="text-xl font-bold text-indigo-700 mb-2">Together, we build a healthier community.</h3>
        <a href="citizen_dashboard.php?view=report"
           class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg shadow-lg font-semibold transition">
           Report a Bin Issue
        </a>
    </div>

</div>

<script>lucide.createIcons();</script>

</body>
</html>
