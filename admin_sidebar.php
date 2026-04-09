<!-- ADMIN SIDEBAR (Reusable Component) -->
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
    </div>

    <!-- Menu -->
    <nav class="flex-1 px-2 py-4 space-y-1 text-sm">
        <a href="admin_home.php"
           class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-50">
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
           class="sidebar-item flex items-center px-3 py-2 rounded-lg bg-slate-800 text-violet-300 font-semibold">
            <i data-lucide="line-chart" class="w-4 h-4 mr-2"></i>
            <span class="sidebar-text">Prediction & Insights</span>
        </a>

        <a href="admin_heatmap.php"
           class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-50">
            <i data-lucide="map" class="w-4 h-4 mr-2"></i>
            <span class="sidebar-text">City Heatmap</span>
        </a>

        <a href="admin_logs.php"
           class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-50">
            <i data-lucide="activity" class="w-4 h-4 mr-2"></i>
            <span class="sidebar-text">System Logs</span>
        </a>
    </nav>
</aside>
