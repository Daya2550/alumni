<?php
/**
 * LinkedIn OAuth Callback Handler
 * This script processes the authorization code, exchanges it for an access token,
 * retrieves user info, and either logs in an existing user or redirects a new
 * user to a dedicated registration page using LinkedIn data.
 */

// Include necessary components
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
// NOTE: Assuming a secure configuration file is loaded here, defining constants.
// For security and portability, client secrets MUST NOT be hardcoded.

// Load credentials from securely defined constants
// These constants must be defined in a configuration file or loaded from environment variables.
if (!defined('LINKEDIN_CLIENT_ID') || !defined('LINKEDIN_CLIENT_SECRET') || !defined('LINKEDIN_REDIRECT_URI')) {
    error_log("FATAL: LinkedIn credentials are not securely defined.");
    $_SESSION['error'] = "Authentication service misconfiguration. Please contact support.";
    header("Location: login.php");
    exit;
}

$client_id     = LINKEDIN_CLIENT_ID;
$client_secret = LINKEDIN_CLIENT_SECRET;
$redirect_uri  = LINKEDIN_REDIRECT_URI;
$login_page    = 'login.php'; // Use a generic login page for general errors

// Helper function for redirection with error message
function handleAuthError(string $message, string $redirectPage = 'login.php'): void {
    $_SESSION['error'] = $message;
    header("Location: $redirectPage");
    exit;
}

// Step 1: Capture Authorization Code
if (!isset($_GET['code'])) {
    $error_description = $_GET['error_description'] ?? "No authorization code received from LinkedIn.";
    handleAuthError($error_description);
}

$authorization_code = $_GET['code'];
    
// Step 2: Exchange Code for Access Token
$token_url = "https://www.linkedin.com/oauth/v2/accessToken";
$token_request_data = [
    "grant_type"    => "authorization_code",
    "code"          => $authorization_code,
    "redirect_uri"  => $redirect_uri,
    "client_id"     => $client_id,
    "client_secret" => $client_secret
];

$http_options = [
    "http" => [
        "header"  => "Content-Type: application/x-www-form-urlencoded",
        "method"  => "POST",
        "content" => http_build_query($token_request_data),
        // Always set verify_peer to true in production for security
        "verify_peer" => true 
    ]
];

$context = stream_context_create($http_options);
// Using @ to suppress warnings and handling errors explicitly
$token_response = @file_get_contents($token_url, false, $context);

if ($token_response === false) {
    handleAuthError("Failed to connect to LinkedIn to retrieve access token.");
}

$token_data = json_decode($token_response, true);

if (!isset($token_data['access_token'])) {
    $error_message = $token_data['error_description'] ?? 'Failed to obtain access token from LinkedIn.';
    error_log("LinkedIn Token Error: " . ($token_data['error'] ?? 'Unknown') . " - " . $error_message);
    handleAuthError($error_message);
}

$access_token = $token_data['access_token'];

// Step 3: Fetch User Info from LinkedIn (OpenID Connect endpoint)
$userinfo_url = "https://api.linkedin.com/v2/userinfo";
$userinfo_headers = ["Authorization: Bearer " . $access_token];

$userinfo_context = stream_context_create([
    "http" => [
        "header" => implode("\r\n", $userinfo_headers),
        "verify_peer" => true
    ]
]);

$userinfo_response = @file_get_contents($userinfo_url, false, $userinfo_context);

if ($userinfo_response === false) {
    handleAuthError("Failed to retrieve user information from LinkedIn.");
}

$userinfo_data = json_decode($userinfo_response, true);

if (!isset($userinfo_data['email'])) {
    handleAuthError("LinkedIn profile did not provide a required email address.");
}

$user_email = $userinfo_data['email'];

// Step 4: Check User Existence and Process Login/Registration
$existing_user = db()->fetchOne("SELECT * FROM users WHERE email = ?", [$user_email]);

if ($existing_user) {
    // Existing User: Log in and Redirect
    Auth::login($existing_user['id']);
    
    // Update profile picture if available (best practice)
    if (isset($userinfo_data['picture'])) {
        db()->execute("UPDATE users SET profile_picture = ? WHERE id = ?", 
                     [$userinfo_data['picture'], $existing_user['id']]);
    }
    
    // Determine redirect URL based on user role
    $redirect_url = match ($existing_user['role']) {
        'admin' => 'admin-dashboard.php',
        'staff' => 'staff-dashboard.php',
        default => 'student-dashboard.php',
    };
    
    header("Location: $redirect_url");
    exit;
} else {
    // New User: Store Data and Redirect to Complete Registration
    // This allows the user to fill in other required fields (like batch)
    $_SESSION['linkedin_data'] = [
        'name'    => $userinfo_data['name'] ?? '',
        'email'   => $user_email,
        'picture' => $userinfo_data['picture'] ?? null,
        'sub'     => $userinfo_data['sub'] ?? ''
    ];
    
    // Redirect to the dedicated registration page to complete the profile setup
    header("Location: linkedin_register.php");
    exit;
}