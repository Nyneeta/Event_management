<?php
require 'db.php';
session_start();

$errors = [];
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $club_name = trim($_POST['club_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validation
    if (empty($club_name)) {
        $errors[] = "Club name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        // Check if user exists with admin role
        $stmt = $conn->prepare("SELECT user_id, username, password_hash FROM Users WHERE email = ? AND role = 'admin'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Check if club exists and get club_id
                $club_stmt = $conn->prepare("SELECT club_id FROM Clubs WHERE club_name = ?");
                $club_stmt->bind_param("s", $club_name);
                $club_stmt->execute();
                $club_result = $club_stmt->get_result();
                
                if ($club_result->num_rows > 0) {
                    $club = $club_result->fetch_assoc();
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = 'admin';
                    $_SESSION['club_id'] = $club['club_id'];
                    $_SESSION['club_name'] = $club_name;
                    
                    // Redirect to admin dashboard
                    header("Location: dashboard_admin.php");
                    exit;
                } else {
                    $errors[] = "Club not found. Please contact system administrator.";
                }
                $club_stmt->close();
            } else {
                $errors[] = "Invalid credentials. Please try again.";
            }
        } else {
            $errors[] = "Admin account not found with this email.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login - Club Dashboard</title>
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }
  
  body {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
  }
  
  .container {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    width: 100%;
    max-width: 420px;
  }
  
  h2 {
    text-align: center;
    margin-bottom: 10px;
    color: #333;
    font-size: 28px;
    font-weight: 600;
  }
  
  .subtitle {
    text-align: center;
    margin-bottom: 30px;
    color: #666;
    font-size: 14px;
  }
  
  .form-group {
    margin-bottom: 20px;
  }
  
  label {
    display: block;
    font-weight: 600;
    color: #555;
    margin-bottom: 8px;
    font-size: 14px;
  }
  
  input[type="text"], 
  input[type="email"], 
  input[type="password"] {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s ease;
    background-color: #f8f9fa;
  }
  
  input[type="text"]:focus, 
  input[type="email"]:focus, 
  input[type="password"]:focus {
    outline: none;
    border-color: #667eea;
    background-color: white;
  }
  
  button {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    font-size: 16px;
    transition: transform 0.2s ease;
    margin-top: 10px;
  }
  
  button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
  }
  
  .back-link {
    text-align: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e1e5e9;
  }
  
  .back-link a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
  }
  
  .back-link a:hover {
    text-decoration: underline;
  }
  
  .error-messages {
    background-color: #fee;
    border: 1px solid #fcc;
    color: #c33;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
    font-size: 14px;
  }
  
  .success-message {
    background-color: #efe;
    border: 1px solid #cfc;
    color: #363;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
    font-size: 14px;
  }

  .admin-icon {
    text-align: center;
    margin-bottom: 20px;
    font-size: 48px;
    color: #667eea;
  }

  .help-text {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
    font-style: italic;
  }
</style>
</head>
<body>
<div class="container">
  <div class="admin-icon">🔐</div>
  <h2>Admin Login</h2>
  <p class="subtitle">Access your club management dashboard</p>
  
  <?php if (!empty($errors)): ?>
    <div class="error-messages">
      <?php foreach ($errors as $error): ?>
        <div><?php echo htmlspecialchars($error); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  
  <?php if ($success): ?>
    <div class="success-message">
      <?php echo htmlspecialchars($success); ?>
    </div>
  <?php endif; ?>
  
  <form method="POST" novalidate>
    <div class="form-group">
      <label for="club_name">Club Name:</label>
      <input type="text" id="club_name" name="club_name" 
             value="<?php echo isset($_POST['club_name']) ? htmlspecialchars($_POST['club_name']) : ''; ?>" 
             required>
      <div class="help-text">Enter the exact name of your club</div>
    </div>

    <div class="form-group">
      <label for="email">Admin Email:</label>
      <input type="email" id="email" name="email" 
             value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
             required>
      <div class="help-text">Use the same email from registration</div>
    </div>

    <div class="form-group">
      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required>
    </div>

    <button type="submit">Login to Dashboard</button>
  </form>

  <div class="back-link">
    <p><a href="signup.php">← Back to Registration</a></p>
    <p style="margin-top: 10px;"><a href="student_login.php">Student Login</a></p>
  </div>
</div>

</body>
</html>