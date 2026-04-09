<?php
session_start();

// Prevent direct access
if (!isset($_SESSION['last_report_success'])) {
    header("Location: citizen_dashboard.php?view=rewards");
    exit;
}
unset($_SESSION['last_report_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Report Submitted</title>
<script src="https://cdn.tailwindcss.com"></script>

<!-- Auto Redirect After 3 Seconds -->
<meta http-equiv="refresh" content="3;url=citizen_dashboard.php?view=rewards">

<style>
    body {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        font-family: 'Inter', sans-serif;
    }
    /* Green Animated Checkmark */
    .checkmark {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: pop 0.5s ease-out forwards;
        background-color: #10b981;
        color: white;
        font-size: 48px;
    }
    @keyframes pop {
        0% { transform: scale(0.2); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }

    /* Fade-in animation for card */
    .card-anim {
        animation: fadeSlide 0.7s ease-out forwards;
        opacity: 0;
        transform: translateY(20px);
    }
    @keyframes fadeSlide {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
</head>

<body class="flex items-center justify-center h-screen">

<!-- Glassmorphism Success Card -->
<div class="card-anim bg-white/70 backdrop-blur-xl p-10 rounded-3xl shadow-2xl text-center max-w-md border border-white">

    <div class="flex justify-center mb-6">
        <div class="checkmark">✔</div>
    </div>

    <h1 class="text-3xl font-extrabold text-green-700 mb-3">
        Report Submitted!
    </h1>

    <p class="text-gray-700 text-lg mb-6">
        Thank you for helping keep the community clean.<br>
        You have earned <span class="font-bold text-green-700">+10 reward points</span>.
    </p>

    <p class="text-sm text-gray-500">
        Redirecting you to your Dashboard...
    </p>
</div>

</body>
</html>
