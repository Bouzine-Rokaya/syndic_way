// Syndic Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    setupEventListeners();
});

function loadDashboardData() {
    // Load dashboard statistics
    fetch('api/dashboard-stats.php')
        .then(response => response.json())
        .then(data => {
            updateStatistics(data);
        })
        .catch(error => console.error('Error loading dashboard data:', error));

    // Load recent maintenance requests
    fetch('api/recent-requests.php')
        .then(response => response.json())
        .then(data => {
            displayRecentRequests(data);
        })
        .catch(error => console.error('Error loading recent requests:', error));
}

function updateStatistics(data) {
    document.getElementById('total-residents').textContent = data.total_residents || 0;
    document.getElementById('total-apartments').textContent = data.total_apartments || 0;
    document.getElementById('pending-requests').textContent = data.pending_requests || 0;
    document.getElementById('unpaid-invoices').textContent = data.unpaid_invoices || 0;
}

function displayRecentRequests(requests) {
    const container = document.getElementById('recent-requests');
    
    if (requests.length === 0) {
        container.innerHTML = '<p>No recent maintenance requests.</p>';
        return;
    }

    let html = '<div class="requests-list">';
    requests.forEach(request => {
        html += `
            <div class="request-item">
                <div class="request-info">
                    <h4>${request.description}</h4>
                    <p><strong>Apartment:</strong> ${request.numero_appartement}</p>
                    <p><strong>Type:</strong> ${request.type_probleme}</p>
                    <p><strong>Priority:</strong> ${request.priorite}</p>
                    <p><strong>Date:</strong> ${formatDate(request.date_demande)}</p>
                </div>
                <div class="request-actions">
                    <button class="btn btn-primary" onclick="viewRequest(${request.id_demande})">View</button>
                    <button class="btn btn-secondary" onclick="assignRequest(${request.id_demande})">Assign</button>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

function setupEventListeners() {
    // Add any additional event listeners here
}

function viewRequest(requestId) {
    window.location.href = `maintenance-detail.php?id=${requestId}`;
}

function assignRequest(requestId) {
    if (confirm('Are you sure you want to assign this request?')) {
        fetch('api/assign-request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ request_id: requestId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Request assigned successfully');
                loadDashboardData(); // Refresh data
            } else {
                alert('Error assigning request: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error assigning request');
        });
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

// Auto-refresh dashboard every 5 minutes
setInterval(loadDashboardData, 5 * 60 * 1000);