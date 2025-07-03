<?php
session_start();
// Only allow access if admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Database connection (adjust these credentials)
$host = 'localhost';
$dbname = 'event_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}


try {
    $clubs = $pdo->query("SELECT * FROM Clubs ORDER BY club_name")->fetchAll();
} catch(PDOException $e) {
    $clubs = []; // Initialize as empty array if query fails
    error_log("Error fetching clubs: " . $e->getMessage());
}

// Handle form submissions with POST-REDIRECT-GET pattern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['change_password'])) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                // Store success message in session
                $_SESSION['success_message'] = "Password change functionality needs to be implemented with proper database integration.";
            } else {
                $_SESSION['error_message'] = "New passwords do not match!";
            }
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=profile");
            exit;
        }
  if (isset($_POST['add_club'])) {
    // FIXED: Changed 'description' to 'club_description' to match your database table
    $stmt = $pdo->prepare("INSERT INTO Clubs (club_name, club_description) VALUES (?, ?)");
    $stmt->execute([$_POST['club_name'], $_POST['description']]);
    $_SESSION['success_message'] = "Club added successfully!";
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=clubs");
    exit;
}
        
        if (isset($_POST['add_event'])) {
            $stmt = $pdo->prepare("INSERT INTO Events (club_id, event_name, event_date, location, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['club_id'], $_POST['event_name'], $_POST['event_date'], $_POST['location'], $_POST['description']]);
            $_SESSION['success_message'] = "Event added successfully!";
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=events");
            exit;
        }
        
        if (isset($_POST['add_recruitment'])) {
            $stmt = $pdo->prepare("INSERT INTO RecruitmentNotices (club_id, position, description, application_deadline) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['club_id'], $_POST['position'], $_POST['description'], $_POST['application_deadline']]);
            $_SESSION['success_message'] = "Recruitment notice added successfully!";
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=recruitment");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Display session messages and clear them
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

$recent_events = $pdo->query("
    SELECT e.*, c.club_name 
    FROM Events e 
    JOIN Clubs c ON e.club_id = c.club_id 
    ORDER BY e.event_date DESC 
    LIMIT 5
")->fetchAll();


// Enhanced Analytics Data
// Get student registrations for events
$event_registrations = $pdo->query("
    SELECT 
        er.registration_id,
        er.student_name,
        er.roll_number,
        er.branch,
        er.registration_date,
        e.event_name,
        c.club_name,
        e.event_date,
        'Event' as registration_type
    FROM EventRegistrations er
    JOIN Events e ON er.event_id = e.event_id
    JOIN Clubs c ON e.club_id = c.club_id
    ORDER BY er.registration_date DESC
")->fetchAll();

// Get registration statistics
$registration_stats = [];

// Stats by club
$club_stats = $pdo->query("
    SELECT 
        c.club_name,
        COUNT(er.registration_id) as event_registrations,
        COUNT(DISTINCT er.student_name) as unique_students
    FROM Clubs c
    LEFT JOIN Events e ON c.club_id = e.club_id
    LEFT JOIN EventRegistrations er ON e.event_id = er.event_id
    GROUP BY c.club_id, c.club_name
    ORDER BY event_registrations DESC
")->fetchAll();

// Stats by branch
$branch_stats = $pdo->query("
    SELECT 
        branch,
        COUNT(*) as registration_count,
        COUNT(DISTINCT student_name) as unique_students
    FROM EventRegistrations
    WHERE branch IS NOT NULL AND branch != ''
    GROUP BY branch
    ORDER BY registration_count DESC
")->fetchAll();

// Get the active tab from URL parameter
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Get club-wise event registrations with detailed breakdown
$club_event_registrations = $pdo->query("
    SELECT 
        c.club_id,
        c.club_name,
        e.event_id,
        e.event_name,
        e.event_date,
        e.location,
        COUNT(er.registration_id) as registration_count,
        GROUP_CONCAT(
            CONCAT(er.student_name, ' (', er.roll_number, ' - ', er.branch, ')')
            ORDER BY er.registration_date DESC
            SEPARATOR '|'
        ) as registered_students
    FROM Clubs c
    LEFT JOIN Events e ON c.club_id = e.club_id
    LEFT JOIN EventRegistrations er ON e.event_id = er.event_id
    GROUP BY c.club_id, c.club_name, e.event_id, e.event_name, e.event_date, e.location
    HAVING registration_count > 0
    ORDER BY c.club_name, e.event_date DESC
")->fetchAll();

// Group by club for easier display
$clubs_with_events = [];
foreach ($club_event_registrations as $row) {
    $club_name = $row['club_name'];
    if (!isset($clubs_with_events[$club_name])) {
        $clubs_with_events[$club_name] = [
            'club_id' => $row['club_id'],
            'club_name' => $club_name,
            'total_registrations' => 0,
            'events' => []
        ];
    }
    
    $students = [];
    if ($row['registered_students']) {
        foreach (explode('|', $row['registered_students']) as $student_info) {
            $students[] = $student_info;
        }
    }
    
    $clubs_with_events[$club_name]['events'][] = [
        'event_id' => $row['event_id'],
        'event_name' => $row['event_name'],
        'event_date' => $row['event_date'],
        'location' => $row['location'],
        'registration_count' => $row['registration_count'],
        'students' => $students
    ];
    
    $clubs_with_events[$club_name]['total_registrations'] += $row['registration_count'];
}

// Get overall club statistics
$club_summary_stats = $pdo->query("
    SELECT 
        c.club_name,
        COUNT(DISTINCT e.event_id) as total_events,
        COUNT(er.registration_id) as total_registrations,
        COUNT(DISTINCT er.student_name) as unique_students
    FROM Clubs c
    LEFT JOIN Events e ON c.club_id = e.club_id
    LEFT JOIN EventRegistrations er ON e.event_id = er.event_id
    GROUP BY c.club_id, c.club_name
    ORDER BY total_registrations DESC
")->fetchAll();

?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Club Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin_dashboard.css">
    
</head>
<body>
<div class="sidebar">
    <h2>ONLINE EVENT<br>MANAGEMENT</h2>
<a href="#" class="active" onclick="showTab('dashboard', event)"><i class="fa fa-home"></i> Dashboard</a>
<a href="#" onclick="showTab('clubs', event)"><i class="fa fa-users"></i> Manage Clubs</a>

<a href="#" onclick="showTab('events', event)"><i class="fa fa-calendar"></i> Add Event</a>

<a href="#" onclick="showTab('analytics', event)"><i class="fa fa-chart-bar"></i> Analytics</a>
<a href="#" onclick="showTab('profile', event)"><i class="fa fa-user"></i> Profile</a>


    <hr style="border: 1px solid rgba(255,255,255,0.2); margin: 20px 0;">
    <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
    <?php if (isset($success_message)): ?>
        <div class="success-message">
            <i class="fa fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <i class="fa fa-exclamation-triangle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Dashboard Tab -->
    <div id="dashboard" class="tab-content active">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>!</h1>
            <p>Manage your college club events and activities from this dashboard.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fa fa-users"></i>
                <h3><?php echo count($clubs); ?></h3>
                <p>Total Clubs</p>
            </div>
            
            <div class="stat-card">
                <i class="fa fa-calendar"></i>
                <h3><?php echo count($recent_events); ?></h3>
                <p>Recent Events</p>
            </div>
          
        </div>

        <div class="dashboard-grid">

            <div class="recent-items">
                <h3><i class="fa fa-calendar"></i> Recent Events</h3>
                <?php foreach ($recent_events as $event): ?>
                    <div class="recent-item">
                        <h4><?php echo htmlspecialchars($event['event_name']); ?></h4>
                        <p><strong><?php echo htmlspecialchars($event['club_name']); ?></strong> - <?php echo htmlspecialchars($event['location']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Clubs Tab -->
    <div id="clubs" class="tab-content">
        <div class="header">
            <h1>Manage Clubs</h1>
            <p>Add and manage college clubs</p>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h3><i class="fa fa-plus"></i> Add New Club</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="club_name">Club Name</label>
                        <input type="text" id="club_name" name="club_name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"></textarea>
                    </div>
                    <button type="submit" name="add_club" class="btn">
                        <i class="fa fa-plus"></i> Add Club
                    </button>
                </form>
            </div>

            <div class="card">
                <h3><i class="fa fa-list"></i> Existing Clubs</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($clubs as $club): ?>
                        <div class="recent-item">
                            <h4><?php echo htmlspecialchars($club['club_name']); ?></h4>
                            <p><?php echo htmlspecialchars($club['description'] ?? 'No description'); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Events Tab -->
    <div id="events" class="tab-content">
        <div class="header">
            <h1>Add Events</h1>
            <p>Schedule new events for clubs</p>
        </div>

        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <h3><i class="fa fa-calendar"></i> Schedule New Event</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="event_club_id">Select Club</label>
                    <select id="event_club_id" name="club_id" required>
                        <option value="">Choose a club...</option>
                        <?php foreach ($clubs as $club): ?>
                            <option value="<?php echo $club['club_id']; ?>">
                                <?php echo htmlspecialchars($club['club_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="event_name">Event Name</label>
                    <input type="text" id="event_name" name="event_name" required>
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location">
                </div>
                <div class="form-group">
                    <label for="event_date">Event Date</label>
                    <input type="date" id="event_date" name="event_date" required>
                </div>
                <div class="form-group">
                    <label for="event_description">Description</label>
                    <textarea id="event_description" name="description" rows="4"></textarea>
                </div>
                <button type="submit" name="add_event" class="btn">
                    <i class="fa fa-calendar"></i> Schedule Event
                </button>
            </form>
        </div>
    </div>

    <!-- Profile Tab -->
    <div id="profile" class="tab-content">
        <div class="header">
            <h1>Profile Settings</h1>
            <p>Manage your account information</p>
        </div>

        <div class="card" style="max-width: 600px; margin: 20px auto;">
            <h3><i class="fa fa-user"></i> Account Information</h3>
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" value="<?php 
                    if (isset($_SESSION['username'])) {
                        echo htmlspecialchars($_SESSION['username']); 
                    } else {
                        echo 'Not Set';
                    }
                ?>" readonly style="background-color: #f8f9fa;">
            </div>
            
            <div class="form-group">
                <label>Role</label>
                <input type="text" value="<?php 
                    if (isset($_SESSION['role'])) {
                        echo htmlspecialchars($_SESSION['role']); 
                    } else {
                        echo 'admin';
                    }
                ?>" readonly style="background-color: #f8f9fa;">
            </div>
            
            <div class="form-group">
                <label>User ID</label>
                <input type="text" value="<?php 
                    if (isset($_SESSION['user_id'])) {
                        echo htmlspecialchars($_SESSION['user_id']); 
                    } else {
                        echo 'Not Set';
                    }
                ?>" readonly style="background-color: #f8f9fa;">
            </div>
            
            <div class="form-group">
                <label>Account Status</label>
                <input type="text" value="Active" readonly style="background-color: #d4edda; color: #155724;">
            </div>
        </div>

        <div class="card" style="max-width: 600px; margin: 20px auto;">
            <h3><i class="fa fa-history"></i> Session Information</h3>
            <p><strong>Session started:</strong> <?php echo date('M j, Y - g:i A'); ?></p>
            <p><strong>Status:</strong> Currently logged in as Administrator</p>
            <p><strong>Actions:</strong> 
                <a href="logout.php" style="color: #dc3545; text-decoration: none; font-weight: bold;">
                    <i class="fa fa-sign-out-alt"></i> Logout
                </a>
            </p>
        </div>
    </div>

    <!-- Analytics Tab -->
    <div id="analytics" class="tab-content">
        <div class="header">
            <h1>Student Registration Analytics</h1>
            <p>Club-wise event registrations and participation details</p>
        </div>

        <!-- Enhanced Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fa fa-users"></i>
                <h3><?php echo count($clubs); ?></h3>
                <p>Total Clubs</p>
            </div>
           
            <div class="stat-card">
                <i class="fa fa-calendar"></i>
                <h3><?php 
                    $total_events = $pdo->query("SELECT COUNT(*) FROM Events")->fetchColumn();
                    echo $total_events;
                ?></h3>
                <p>Total Events</p>
            </div>
            <div class="stat-card">
                <i class="fa fa-user-check"></i>
                <h3><?php echo count($event_registrations); ?></h3>
                <p>Total Registrations</p>
            </div>
          
        </div>

        <!-- Detailed Club-wise Event Registrations -->
        <div class="club-events-container">
            <h3><i class="fa fa-users"></i> Club-wise Event Registrations</h3>
            
            <?php foreach ($clubs_with_events as $club): ?>
                <div class="club-section">
                    <div class="club-header">
                        <h4>
                            <i class="fa fa-building"></i>
                            <?php echo htmlspecialchars($club['club_name']); ?>
                            <span class="total-registrations">(<?php echo $club['total_registrations']; ?> total registrations)</span>
                        </h4>
                    </div>
                    
                    <div class="events-grid">
                        <?php foreach ($club['events'] as $event): ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <h5><?php echo htmlspecialchars($event['event_name']); ?></h5>
                                    <div class="event-meta">
                                        <span class="event-date">
                                            <i class="fa fa-calendar"></i>
                                            <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                        </span>
                                        <?php if ($event['location']): ?>
                                            <span class="event-location">
                                                <i class="fa fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($event['location']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="registration-count">
                                        <i class="fa fa-users"></i>
                                        <?php echo $event['registration_count']; ?> registered
                                    </div>
                                </div>
                                
                                <div class="registered-students">
                                    <h6>Registered Students:</h6>
                                    <div class="students-list">
                                        <?php foreach ($event['students'] as $student): ?>
                                            <div class="student-item">
                                                <i class="fa fa-user"></i>
                                                <?php echo htmlspecialchars($student); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($clubs_with_events)): ?>
                <div class="no-data">
                    <i class="fa fa-info-circle"></i>
                    <p>No event registrations found. Students will appear here once they register for events.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Enhanced Analytics Styles */
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.analytics-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid #e0e0e0;
}

.analytics-card h3 {
    color: #2c3e50;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Club Summary Styles */
.club-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.club-summary-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    border-left: 4px solid #5e35b1;
}

.club-summary-header h4 {
    margin: 0 0 10px 0;
    color: #2c3e50;
    font-size: 16px;
}

.club-summary-stats {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.summary-stat {
    text-align: center;
    flex: 1;
}

.summary-stat .stat-number {
    display: block;
    font-size: 20px;
    font-weight: bold;
    color: #5e35b1;
}

.summary-stat .stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

/* Club Events Container */
.club-events-container {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid #e0e0e0;
    margin-bottom: 20px;
}

.club-events-container > h3 {
    color: #2c3e50;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.club-section {
    margin-bottom: 30px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
}

.club-header {
    background: linear-gradient(135deg, #5e35b1, #7e57c2);
    color: white;
    padding: 15px 20px;
}

.club-header h4 {
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.total-registrations {
    font-size: 14px;
    background: rgba(255,255,255,0.2);
    padding: 4px 8px;
    border-radius: 12px;
}

.events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    padding: 20px;
    background: #fafafa;
}

.event-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
}

.event-header {
    border-bottom: 2px solid #f1f3f4;
    padding-bottom: 15px;
    margin-bottom: 15px;
}

.event-header h5 {
    margin: 0 0 10px 0;
    color: #2c3e50;
    font-size: 18px;
}

.event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 10px;
    font-size: 14px;
    color: #666;
}

.event-date, .event-location {
    display: flex;
    align-items: center;
    gap: 5px;
}

.registration-count {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #5e35b1;
    font-weight: bold;
    background: #f3e5f5;
    padding: 5px 10px;
    border-radius: 15px;
    width: fit-content;
}

.registered-students h6 {
    margin: 0 0 10px 0;
    color: #2c3e50;
    font-size: 14px;
    font-weight: 600;
}

.students-list {
    max-height: 200px;
    overflow-y: auto;
}

.student-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid #f1f3f4;
    font-size: 14px;
}

.student-item:last-child {
    border-bottom: none;
}

.student-item i {
    color: #5e35b1;
    width: 16px;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #666;
}

.no-data i {
    font-size: 48px;
    color: #ddd;
    margin-bottom: 15px;
}

/* Existing styles for other elements */
.registration-details {
    max-height: 400px;
    overflow-y: auto;
}

.student-card {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    border-left: 4px solid #5e35b1;
}

.student-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.info-item i {
    color: #5e35b1;
    width: 16px;
}

@media (max-width: 768px) {
    .club-summary-grid {
        grid-template-columns: 1fr;
    }
    
    .events-grid {
        grid-template-columns: 1fr;
        padding: 15px;
    }
    
    .event-meta {
        flex-direction: column;
        gap: 5px;
    }
    
    .club-header h4 {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
   
<script>
function showTab(tabId, event) {
    event && event.preventDefault();
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    // Show selected tab
    document.getElementById(tabId).classList.add('active');

    // Remove 'active' from all sidebar links
    document.querySelectorAll('.sidebar a').forEach(link => link.classList.remove('active'));
    // Add 'active' to clicked link
    if (event) event.currentTarget.classList.add('active');
}

// Set minimum date to today for date inputs
document.addEventListener('DOMContentLoaded', function() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    const today = new Date().toISOString().split('T')[0];
    dateInputs.forEach(input => {
        input.min = today;
    });

    // Password confirmation validation
    const changePasswordForm = document.getElementById('changePasswordForm');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
    }
});
</script>
</body>
</html>