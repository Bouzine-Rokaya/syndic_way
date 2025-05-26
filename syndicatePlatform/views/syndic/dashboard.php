<?php 
$page_title = "Syndic Dashboard";
include 'views/layouts/header.php'; 
include 'views/layouts/sidebar.php'; 
?>

<div class="main-content">
    <div class="dashboard-header">
        <h1>Syndic Dashboard</h1>
    </div>

    <div class="dashboard-stats">
        <div class="stat-card">
            <h3>Total Residents</h3>
            <span class="stat-number" id="total-residents">0</span>
        </div>
        
        <div class="stat-card">
            <h3>Apartments</h3>
            <span class="stat-number" id="total-apartments">0</span>
        </div>
        
        <div class="stat-card">
            <h3>Pending Requests</h3>
            <span class="stat-number" id="pending-requests">0</span>
        </div>
        
        <div class="stat-card">
            <h3>Unpaid Invoices</h3>
            <span class="stat-number" id="unpaid-invoices">0</span>
        </div>
    </div>

    <div class="dashboard-content">
        <div class="content-section">
            <h2>Recent Maintenance Requests</h2>
            <div id="recent-requests"></div>
        </div>
        
        <div class="content-section">
            <h2>Quick Actions</h2>
            <div class="quick-actions">
                <a href="residents.php" class="btn btn-primary">Manage Residents</a>
                <a href="apartments.php" class="btn btn-primary">Manage Apartments</a>
                <a href="maintenance.php" class="btn btn-primary">Maintenance Requests</a>
                <a href="announcements.php" class="btn btn-primary">Create Announcement</a>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>public/js/syndic-dashboard.js"></script>
</body>
</html>