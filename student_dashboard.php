<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: student_login.php");
    exit;
}

// Database connection (adjust these credentials to match your setup)
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

// Handle event registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event'])) {
    $event_id = $_POST['event_id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if user is already registered for this event
    $check_stmt = $pdo->prepare("SELECT * FROM EventRegistrations WHERE event_id = ? AND user_id = ?");
    $check_stmt->execute([$event_id, $user_id]);
    
    if ($check_stmt->rowCount() == 0) {
        // Register user for the event
        $register_stmt = $pdo->prepare("INSERT INTO EventRegistrations (event_id, user_id) VALUES (?, ?)");
        $register_stmt->execute([$event_id, $user_id]);
        $success_message = "Successfully registered for the event!";
    } else {
        $error_message = "You are already registered for this event!";
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $branch = $_POST['branch'];
    $year = $_POST['year'];
    $bio = $_POST['bio'];
    $user_id = $_SESSION['user_id'];
    
    $update_stmt = $pdo->prepare("UPDATE Users SET email = ?, phone = ?, branch = ?, year = ?, bio = ? WHERE user_id = ?");
    if ($update_stmt->execute([$email, $phone, $branch, $year, $bio, $user_id])) {
        $profile_success_message = "Profile updated successfully!";
    } else {
        $profile_error_message = "Failed to update profile. Please try again.";
    }
}

// Fetch user profile information
$user_stmt = $pdo->prepare("SELECT * FROM Users WHERE user_id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user_profile = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch upcoming events (next 30 days)
$upcoming_events = $pdo->query("
    SELECT e.*, c.club_name 
    FROM Events e 
    JOIN Clubs c ON e.club_id = c.club_id 
    WHERE e.event_date >= CURDATE() AND e.event_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY e.event_date ASC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's registered events (event history)
$user_events = $pdo->prepare("
    SELECT e.*, c.club_name, er.registration_date
    FROM EventRegistrations er
    JOIN Events e ON er.event_id = e.event_id
    JOIN Clubs c ON e.club_id = c.club_id
    WHERE er.user_id = ?
    ORDER BY er.registration_date DESC
    LIMIT 10
");
$user_events->execute([$_SESSION['user_id']]);
$event_history = $user_events->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's registered events (booking history) - CORRECTED QUERY
$user_registrations = $pdo->prepare("
    SELECT e.*, c.club_name, er.registration_date, er.student_name, er.roll_number, er.branch
    FROM EventRegistrations er
    JOIN Events e ON er.event_id = e.event_id
    JOIN Clubs c ON e.club_id = c.club_id
    WHERE er.user_id = ?
    ORDER BY er.registration_date DESC
    LIMIT 20
");
$user_registrations->execute([$_SESSION['user_id']]);
$booking_history = $user_registrations->fetchAll(PDO::FETCH_ASSOC);

// Debug: Check if we have any registrations
error_log("User ID: " . $_SESSION['user_id']);
error_log("Booking history count: " . count($booking_history));

// If the user just registered and we have URL parameters, let's also check for recent registrations
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    // Force refresh the booking history to include the latest registration
    $recent_registration = $pdo->prepare("
        SELECT e.*, c.club_name, er.registration_date, er.student_name, er.roll_number, er.branch
        FROM EventRegistrations er
        JOIN Events e ON er.event_id = e.event_id
        JOIN Clubs c ON e.club_id = c.club_id
        WHERE er.user_id = ?
        ORDER BY er.registration_date DESC
        LIMIT 1
    ");
    $recent_registration->execute([$_SESSION['user_id']]);
    $latest_registration = $recent_registration->fetch(PDO::FETCH_ASSOC);
    
    if ($latest_registration) {
        // If we found a recent registration but it's not in our booking_history, add it
        $found_in_history = false;
        foreach ($booking_history as $booking) {
            if ($booking['event_id'] == $latest_registration['event_id']) {
                $found_in_history = true;
                break;
            }
        }
        
        if (!$found_in_history) {
            array_unshift($booking_history, $latest_registration);
        }
    }
}

// Get stats for dashboard
$total_events = $pdo->query("SELECT COUNT(*) FROM Events WHERE event_date >= CURDATE()")->fetchColumn();
$user_registered_events = $pdo->prepare("SELECT COUNT(*) FROM EventRegistrations WHERE user_id = ?");
$user_registered_events->execute([$_SESSION['user_id']]);
$registered_count = $user_registered_events->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/student_dashboard.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>STUDENT PORTAL</h2>
        <a href="#" class="active" onclick="showSection('dashboard')"><i class="fa fa-home"></i> Dashboard</a>
        <a href="#" onclick="showSection('profile')"><i class="fa fa-user"></i> Profile</a>
        <a href="#" onclick="showSection('events')"><i class="fa fa-calendar-alt"></i> Events</a>
        <a href="#" onclick="showSection('booking')"><i class="fa fa-history"></i> My Registrations</a>

        <div class="logout-link">
            <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($success_message)): ?>
            <div class="success-message">
                <i class="fa fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fa fa-exclamation-triangle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <div id="dashboard" class="tab-content active">
            <div class="header">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h1>
                <p>Stay updated with the latest events from various clubs.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fa fa-calendar-alt"></i>
                    <h3><?php echo $total_events; ?></h3>
                    <p>Upcoming Events</p>
                </div>
                <div class="stat-card">
                    <i class="fa fa-check-circle"></i>
                    <h3><?php echo $registered_count; ?></h3>
                    <p>Registered Events</p>
                </div>
            </div>

            <!-- Quick Overview -->
            <div class="section">
                <h3><i class="fa fa-star"></i> Latest Updates</h3>
                
                <?php if (count($upcoming_events) > 0): ?>
                    <h4 style="color: #5e35b1; margin-bottom: 15px;">Next Event</h4>
                    <?php $next_event = $upcoming_events[0]; ?>
                    <div class="event-card">
                        <h4><?php echo htmlspecialchars($next_event['event_name']); ?></h4>
                        <p><strong>Club:</strong> <span class="club-name"><?php echo htmlspecialchars($next_event['club_name']); ?></span></p>
                        <p><strong>Date:</strong> <span class="date"><?php echo date('M j, Y', strtotime($next_event['event_date'])); ?></span></p>
                        <?php if ($next_event['location']): ?>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($next_event['location']); ?></p>
                        <?php endif; ?>
                        <?php if ($next_event['description']): ?>
                            <div class="description"><?php echo htmlspecialchars($next_event['description']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fa fa-calendar" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                        <p>No upcoming events at the moment. Check back later!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PROFILE SECTION -->
        <div id="profile" class="tab-content">
            <div class="header">
                <h1>My Profile</h1>
            </div>

            <?php if (isset($profile_success_message)): ?>
                <div class="success-message-profile">
                    <i class="fa fa-check-circle"></i> <?php echo $profile_success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($profile_error_message)): ?>
                <div class="error-message-profile">
                    <i class="fa fa-exclamation-triangle"></i> <?php echo $profile_error_message; ?>
                </div>
            <?php endif; ?>

            <div class="profile-section">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user_profile['username'] ?? 'UN', 0, 2)); ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user_profile['username'] ?? 'Unknown User'); ?></h2>
                        <p><?php echo htmlspecialchars($user_profile['email'] ?? 'No email provided'); ?></p>
                        <p>
                            <i class="fa fa-graduation-cap"></i> 
                           
                            <?php if (!empty($user_profile['year'])): ?>
                                - Year <?php echo htmlspecialchars($user_profile['year']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="profile-stats">
                    <div class="profile-stat">
                        <h3><?php echo $registered_count; ?></h3>
                        <p>Events Registered</p>
                    </div>
                    <div class="profile-stat">
                        <h3><?php echo date('M Y', strtotime($user_profile['created_at'] ?? 'now')); ?></h3>
                        <p>Member Since</p>
                    </div>
                    <div class="profile-stat">
                        <h3><?php echo ucfirst($user_profile['role'] ?? 'Student'); ?></h3>
                        <p>Account Type</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Events Section -->
        <div id="events" class="tab-content">
            <div class="header">
                <h1>Upcoming Events</h1>
                <p>Attend exciting events and enhance your knowledge</p>
            </div>

            <div class="section">
                <h3><i class="fa fa-calendar-alt"></i> Available Events</h3>
                
                <?php if (count($upcoming_events) > 0): ?>
                    <?php foreach ($upcoming_events as $event): ?>
                        <div class="event-card">
                            <h4><?php echo htmlspecialchars($event['event_name']); ?></h4>
                            <p><strong>Club:</strong> <span class="club-name"><?php echo htmlspecialchars($event['club_name']); ?></span></p>
                            <p><strong>Date:</strong> <span class="date"><?php echo date('M j, Y', strtotime($event['event_date'])); ?></span></p>
                            <?php if ($event['location']): ?>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
                            <?php endif; ?>
                            <?php if ($event['description']): ?>
                                <div class="description"><?php echo htmlspecialchars($event['description']); ?></div>
                            <?php endif; ?>

                            <a href="event_registration.php?event_id=<?php echo urlencode($event['event_id']); ?>" class="attend-button">Register</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fa fa-calendar" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                        <p>No upcoming events at the moment. Check back later!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Updated HTML for My Registrations/Booking Section -->
        <div id="booking" class="tab-content">
            <div class="header">
                <h1>My Registrations</h1>
                <p>View your event registrations</p>
            </div>

            <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                <div class="success-message">
                    <i class="fa fa-check-circle"></i> 
                    Successfully registered for the event! Welcome 
                    <?php echo htmlspecialchars($_GET['name'] ?? ''); ?> 
                    (<?php echo htmlspecialchars($_GET['roll'] ?? ''); ?>) from <?php echo htmlspecialchars($_GET['branch'] ?? ''); ?>
                </div>
            <?php endif; ?>

            <!-- Event Registration History -->
            <div class="section">
                <h3><i class="fa fa-calendar-alt"></i> Event Registration History</h3>
                <p class="section-subtitle">Events you have registered to attend</p>
                
                <?php if (!empty($event_history)): ?>
                    <div class="registration-grid">
                        <?php foreach ($event_history as $event): ?>
                            <div class="registration-card event-card">
                                <div class="card-header">
                                    <h4><i class="fa fa-calendar-alt"></i> <?php echo htmlspecialchars($event['event_name']); ?></h4>
                                    <div class="status-badge">
                                        <?php if (strtotime($event['event_date']) > time()): ?>
                                            <span class="status upcoming">Upcoming</span>
                                        <?php else: ?>
                                            <span class="status completed">Completed</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card-details">
                                    <div class="detail-row">
                                        <span class="detail-label"><i class="fa fa-building"></i> Club:</span>
                                        <span class="detail-value club-name"><?php echo htmlspecialchars($event['club_name']); ?></span>
                                    </div>
                                    
                                    <div class="detail-row">
                                        <span class="detail-label"><i class="fa fa-calendar-alt"></i> Event Date:</span>
                                        <span class="detail-value date"><?php echo date("F j, Y (l)", strtotime($event['event_date'])); ?></span>
                                    </div>
                                    
                                    <?php if (!empty($event['location'])): ?>
                                        <div class="detail-row">
                                            <span class="detail-label"><i class="fa fa-map-marker-alt"></i> Location:</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($event['location']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail-row">
                                        <span class="detail-label"><i class="fa fa-clock"></i> Registered On:</span>
                                        <span class="detail-value"><?php echo date('F j, Y \a\t g:i A', strtotime($event['registration_date'])); ?></span>
                                    </div>
                                    
                                    <?php if (!empty($event['description'])): ?>
                                        <div class="detail-row full-width">
                                            <span class="detail-label"><i class="fa fa-info-circle"></i> Description:</span>
                                            <div class="description"><?php echo htmlspecialchars($event['description']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fa fa-calendar" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                        <p>No event registrations found.</p>
                        <a href="#" onclick="showSection('events')" class="action-link">Browse Events</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        /* Event Registration Styles */
        .registration-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 20px;
        }

        .registration-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #e0e0e0;
        }

        .card-header h4 {
            color: #2c3e50;
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status.upcoming {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #1976d2;
            border: 1px solid #90caf9;
        }

        .status.completed {
            background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
            color: #388e3c;
            border: 1px solid #a5d6a7;
        }

        .card-details {
            padding: 20px;
        }

        .detail-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
        }

        .detail-label {
            font-weight: 600;
            color: #5e35b1;
            min-width: 140px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-label i {
            width: 16px;
            text-align: center;
            color: #5e35b1;
        }

        .detail-value {
            color: #34495e;
            font-size: 14px;
            flex: 1;
            font-weight: 500;
        }

        .detail-value.club-name {
            font-weight: 600;
            color: #5e35b1;
        }

        .detail-value.date {
            font-weight: 600;
            color: #2c3e50;
        }

        .description {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1.5;
            color: #495057;
            margin-top: 8px;
        }

        .success-message {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid #c3e6cb;
            box-shadow: 0 2px 8px rgba(21, 87, 36, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .success-message i {
            font-size: 20px;
            color: #28a745;
        }

        .action-link {
            color: #5e35b1;
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
            display: inline-block;
        }

        .action-link:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .registration-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .detail-label {
                min-width: auto;
            }
        }
    </style>

    <script>
        function showSection(sectionId) {
            // Hide all sections
            const sections = document.querySelectorAll('.tab-content');
            sections.forEach(section => section.classList.remove('active'));
            
            // Remove active class from all sidebar links
            const sidebarLinks = document.querySelectorAll('.sidebar a');
            sidebarLinks.forEach(link => link.classList.remove('active'));
            
            // Show selected section
            document.getElementById(sectionId).classList.add('active');
            
            // Add active class to clicked link
            event.target.classList.add('active');
        }

        // Handle URL parameters and hash navigation
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab');
            const hash = window.location.hash.substring(1); // Remove the #
            
            // Check if there's a tab parameter in URL
            if (activeTab) {
                // Hide all tab content
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                // Remove active class from all sidebar links
                document.querySelectorAll('.sidebar a').forEach(link => {
                    link.classList.remove('active');
                });
                
                // Show the requested tab
                const targetTab = document.getElementById(activeTab);
                if (targetTab) {
                    targetTab.classList.add('active');
                    
                    // Set the corresponding sidebar link as active
                    const sidebarLink = document.querySelector(`.sidebar a[onclick*="${activeTab}"]`);
                    if (sidebarLink) {
                        sidebarLink.classList.add('active');
                    }
                }
            }
            
            // Handle hash navigation
            if (hash) {
                const targetTab = document.getElementById(hash);
                if (targetTab) {
                    // Hide all tab content
                    document.querySelectorAll('.tab-content').forEach(tab => {
                        tab.classList.remove('active');
                    });
                    
                    // Show the target tab
                    targetTab.classList.add('active');
                    
                    // Update sidebar active state
                    document.querySelectorAll('.sidebar a').forEach(link => {
                        link.classList.remove('active');
                    });
                    
                    const sidebarLink = document.querySelector(`.sidebar a[onclick*="${hash}"]`);
                    if (sidebarLink) {
                        sidebarLink.classList.add('active');
                    }
                }
            }
        });

        // Auto-refresh page every 5 minutes to get latest data
        setInterval(function() {
            window.location.reload();
        }, 300000); // 5 minutes = 300000 milliseconds
    </script>
</body>
</html>