<?php
/**
 * Error and Notification Message Display
 * 
 * This file provides functions to display styled popup messages to users
 * for errors, warnings, and general notifications. Messages can optionally
 * redirect the user after display.
 */

/**
 * Displays a styled popup message with HTML layout and optional redirect
 * 
 * This is the base function for all popup messages. It creates a full HTML page
 * with styling and presents a message to the user with a button for navigation.
 * 
 * @param string $message The primary message text to display to the user
 * @param string $type The type/title of the popup (e.g., "error", "warning", "success") - default is "info"
 * @param string|null $redirect Optional URL to redirect to after user clicks the button; if null, goes back to referrer
 * @param string $fallback The default URL to use if no referrer is available and no redirect is specified
 * @return void - Exits the script after displaying the popup
 */
function popup_base($message, $type = "info", $redirect = null, $fallback = "index.php")
{
    // Get the HTTP referrer (the page the user came from)
    // This allows us to return users to their previous page if no explicit redirect is given
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Determine the final redirect URL using this priority:
    // 1. Explicit redirect parameter (if provided)
    // 2. HTTP referrer from the request header (if it exists)
    // 3. Fallback URL (default: index.php)
    $return_url = $redirect ?: ($referrer !== '' ? $referrer : $fallback);
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <!-- Character encoding for proper Unicode and special character support -->
        <meta charset="UTF-8">
        <!-- Viewport configuration for responsive design on all device sizes -->
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- Page title - displayed in browser tab, dynamically set based on popup type -->
        <title><?= htmlspecialchars(ucfirst($type)) ?></title>
        <!-- Link to main layout stylesheet for consistent styling -->
        <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    </head>

    <body>
        <!-- Main container for the error/notification popup -->
        <div class="error-container">
            <!-- Content wrapper for the popup message and controls -->
            <div class="error-content">
                <!-- Popup title/header - displays the type of message (e.g., "Error", "Warning") -->
                <!-- ucfirst() capitalizes the first letter for proper display -->
                <div class="error-title">
                    <?= htmlspecialchars(ucfirst($type)) ?>
                </div>

                <!-- Main message text displayed to the user -->
                <!-- htmlspecialchars() escapes HTML characters to prevent injection attacks -->
                <div class="error-message">
                    <?= htmlspecialchars($message) ?>
                </div>

                <!-- Button container for user action -->
                <div class="error-buttons">
                    <!-- Navigation button with dynamic URL and label -->
                    <!-- htmlspecialchars() prevents XSS attacks by escaping the URL -->
                    <!-- Label changes based on whether it's a redirect or going back -->
                    <a href="<?= htmlspecialchars($return_url) ?>" class="btn-primary">
                        <?= $redirect ? 'Continue' : 'Go Back' ?>
                    </a>
                </div>
            </div>
        </div>
    </body>

    </html>
    <?php
    // Exit immediately after rendering to prevent further script execution
    // and ensure the popup is the only content sent to the browser
    exit();
}

/**
 * Displays an error message popup
 * 
 * Convenience wrapper for popup_base() pre-configured for error messages.
 * Uses "error" as the type and "Oops!" as the title.
 * 
 * @param string $msg The error message to display to the user
 * @param string|null $redirect Optional URL to redirect to; if null, redirects to referrer or index.php
 * @param string $fallback The default page to redirect to if no referrer exists - default is "index.php"
 * @return void - Exits the script after displaying the error popup
 */
function popup_error($msg, $redirect = null, $fallback = "index.php")
{
    // Call the base popup function with error-specific type
    // "Oops!" is used as the user-friendly error title
    popup_base($msg, "Oops!", $redirect, $fallback);
}