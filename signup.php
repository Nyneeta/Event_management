<?php
require 'db.php';
session_start();

$errors = [];
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = isset($_POST['role']) ? $_POST['role'] : 'student'; // Default to 'student'

    // Validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } else {
        // Password validation: at least 1 uppercase, 1 special char, 1 number, minimum 8 characters
        $pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
        if (!preg_match($pattern, $password)) {
            $errors[] = "Password must contain at least 1 uppercase letter, 1 number, 1 special character, and be at least 8 characters long.";
        }
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Password and Confirm Password do not match.";
    }
    
    if (!in_array($role, ['admin', 'student'])) {
        $errors[] = "Invalid role selected.";
    }

    if (empty($errors)) {
        // Check if email or username already exists
        $stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errors[] = "Email or username already registered. Please try a different one or login.";
        } else {
            // Insert new user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO Users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $password_hash, $role);

            if ($stmt->execute()) {
                $success = "Signup successful! Redirecting to login...";
                $_SESSION['redirect_role'] = $role; // Store role in session for JS to use
            } else {
                $errors[] = "Error during registration. Please try again.";
            }
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
<title>Sign Up - User Registration</title>
<link rel="stylesheet" href="css/signup.css">

</head>
<body>
<div class="container">
  <h2>Create Account</h2>
  
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
      <label for="username">Username:</label>
      <input type="text" id="username" name="username" 
             value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
             required>
    </div>

    <div class="form-group">
      <label for="email">Email Address:</label>
      <input type="email" id="email" name="email" 
             value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
             required>
    </div>

    <div class="form-group">
      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required>
      <div class="password-requirements">
        Password must contain at least 1 uppercase letter, 1 number, 1 special character, and be at least 8 characters long.
      </div>
    </div>

    <div class="form-group">
      <label for="confirm_password">Confirm Password:</label>
      <input type="password" id="confirm_password" name="confirm_password" required>
    </div>

    <div class="form-group">
      <label>Account Type:</label>
      <div class="role-group">
        <div class="role-option">
          <input type="radio" id="role_student" name="role" value="student" 
                 <?php echo (!isset($_POST['role']) || $_POST['role'] === 'student') ? 'checked' : ''; ?>>
          <label for="role_student">Student</label>
        </div>
        <div class="role-option">
          <input type="radio" id="role_admin" name="role" value="admin" 
                 <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'checked' : ''; ?>>
          <label for="role_admin">Admin</label>
        </div>
      </div>
    </div>

    <button type="submit">Create Account</button>
  </form>

  <div class="login-link">
    <p>Already have an account? <a href="student_login.php">Login here</a></p>
  </div>
</div>

<?php if ($success && isset($_SESSION['redirect_role'])): ?>
<script>
    setTimeout(function() {
        var role = '<?php echo $_SESSION['redirect_role']; ?>';
        if (role === 'admin') {
            window.location.href = "admin_login.php";
        } else if (role === 'student') {
            window.location.href = "student_login.php";
        }
    }, 2000);
</script>
<?php 
    unset($_SESSION['redirect_role']); // Clear role after setting up redirection
endif; 
?>

</body>
</html>
