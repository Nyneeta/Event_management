<?php
session_start();
include 'db.php'; // DB connection

// Get event_id from URL parameter (changed from seminar_id)
$event_id = $_GET['event_id'] ?? null;
if (!$event_id || !is_numeric($event_id)) {
    header("Location: student_dashboard.php?error=invalid_event");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: student_dashboard.php");
    exit;
}

// Fetch event details for display
$event_query = "SELECT e.*, c.club_name FROM Events e JOIN Clubs c ON e.club_id = c.club_id WHERE e.event_id = ?";
$stmt_event = $conn->prepare($event_query);
$stmt_event->bind_param("i", $event_id);
$stmt_event->execute();
$event_result = $stmt_event->get_result();
$event = $event_result->fetch_assoc();
$stmt_event->close();

if (!$event) {
    header("Location: student_dashboard.php?error=event_not_found");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['student_name'];
    $roll = $_POST['roll_number'];
    $branch = $_POST['branch'];
    $event_id = $_POST['event_id'];
    
    // Check if user already registered for this event (using EventRegistrations table)
    $check_query = "SELECT COUNT(*) FROM EventRegistrations WHERE event_id = ? AND user_id = ?";
    $stmt_check = $conn->prepare($check_query);
    $stmt_check->bind_param("ii", $event_id, $user_id);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();
    
    if ($count > 0) {
        // User already registered - redirect with error message
        header("Location: student_dashboard.php?error=already_registered&tab=booking");
        exit;
    }
    
    // Insert new registration into EventRegistrations table
    $query = "INSERT INTO EventRegistrations 
              (event_id, user_id, student_name, roll_number, branch, registration_date) 
              VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisss", $event_id, $user_id, $name, $roll, $branch);
    
    if ($stmt->execute()) {
        // Successful registration - redirect to My Registrations tab
        header("Location: student_dashboard.php?registered=1&name=" . urlencode($name) . "&roll=" . urlencode($roll) . "&branch=" . urlencode($branch) . "&tab=booking");
        exit;
    } else {
        $error_message = "Registration failed. Please try again.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register for Event</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/student_seminar.css">
      
</head>
<body>
    <div class="container">
     
            
            <form method="POST">
                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_id); ?>">
                
                <div class="form-group">
                    <label for="student_name"><i class="fa fa-user"></i> Full Name:</label>
                    <input type="text" id="student_name" name="student_name" required placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label for="roll_number"><i class="fa fa-id-card"></i> Roll Number:</label>
                    <input type="text" id="roll_number" name="roll_number" required placeholder="Enter your roll number">
                </div>
                
                <div class="form-group">
                    <label for="branch"><i class="fa fa-graduation-cap"></i> Branch:</label>
                    <input type="text" id="branch" name="branch" required placeholder="Enter your branch (e.g., CSE, ECE, ME)">
                </div>
                
                <div class="button-group">
                    <button type="button" class="cancel-btn" onclick="window.history.back();">
                        <i class="fa fa-arrow-left"></i> Cancel
                    </button>
                    <button type="submit" class="submit-btn">
                        <i class="fa fa-check"></i> Confirm Registration
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>