<?php
/**
 * Google OAuth Callback Handler
 * Handles the redirect from Google, exchanges code for access token, and logs in user
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Google OAuth Credentials
$client_id = GOOGLE_CLIENT_ID;
$client_secret = GOOGLE_CLIENT_SECRET;
$redirect_uri = GOOGLE_REDIRECT_URI;

// Step 1: Capture Authorization Code
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Step 2: Exchange Code for Access Token
    $url = "https://oauth2.googleapis.com/token";
    $data = [
        "client_id" => $client_id,
        "client_secret" => $client_secret,
        "code" => $code,
        "grant_type" => "authorization_code",
        "redirect_uri" => $redirect_uri
    ];
    
    $options = [
        "http" => [
            "header"  => "Content-Type: application/x-www-form-urlencoded",
            "method"  => "POST",
            "content" => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $token_data = json_decode($result, true);
    
    if (isset($token_data['access_token'])) {
        $access_token = $token_data['access_token'];
        
        // Step 3: Fetch User Info from Google
        $userinfo_url = "https://www.googleapis.com/oauth2/v2/userinfo?access_token=" . $access_token;
        
        $userinfo = file_get_contents($userinfo_url);
        $userinfo_data = json_decode($userinfo, true);
        
        // Debug: Log the userinfo data (remove in production)
        error_log("Google UserInfo: " . json_encode($userinfo_data));
        
        if (isset($userinfo_data['email'])) {
            // Check if user already exists by email
            $existing_user = db()->fetchOne("SELECT * FROM users WHERE email = ?", [$userinfo_data['email']]);
            
            if ($existing_user) {
                // User exists, log them in using Auth class
                Auth::login($existing_user['id']);
                
                // Update profile picture if available
                if (isset($userinfo_data['picture'])) {
                    db()->execute("UPDATE users SET profile_picture = ? WHERE id = ?", 
                                 [$userinfo_data['picture'], $existing_user['id']]);
                }
                
                // Redirect to appropriate dashboard
                $redirect_url = $existing_user['role'] === 'admin' ? 'admin-dashboard.php' : 
                               ($existing_user['role'] === 'staff' ? 'staff-dashboard.php' : 'student-dashboard.php');
                header("Location: $redirect_url");
                exit;
            } else {
                // New user - create account directly with Google data
                try {
                    $user_id = generateUUID();
                    $hashed_password = hashPassword(uniqid()); // Random password for Google users
                    $verification_token = bin2hex(random_bytes(32));
                    
                    // Create new user account with default role as 'student'
                    db()->execute(
                        "INSERT INTO users (id, name, email, password_hash, role, batch, verification_token, email_verified, is_active, profile_picture, created_at) 
                         VALUES (?, ?, ?, ?, 'student', 'Google User', ?, 1, 1, ?, NOW())",
                        [
                            $user_id,
                            $userinfo_data['name'] ?? 'Google User',
                            $userinfo_data['email'],
                            $hashed_password,
                            $verification_token,
                            $userinfo_data['picture'] ?? null
                        ]
                    );
                    
                    // Log the user in using Auth class
                    Auth::login($user_id);
                    
                    // Redirect to student dashboard (new users default to student role)
                    header("Location: student-dashboard.php");
                    exit;
                    
                } catch (PDOException $e) {
                    error_log("Google user creation failed: " . $e->getMessage());
                    $_SESSION['error'] = "Account creation failed. Please try again.";
                    header("Location: google_login.php");
                    exit;
                }
            }
        } else {
            $_SESSION['error'] = "Failed to retrieve user information from Google.";
            header("Location: google_login.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Failed to get access token from Google.";
        header("Location: google_login.php");
        exit;
    }
} else {
    $_SESSION['error'] = "No authorization code received from Google.";
    header("Location: google_login.php");
    exit;
}
?>