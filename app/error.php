<?php

// ===== POPUP BASE FUNCTION =====
// Generates a full HTML page to display a popup message with optional redirect
// Parameters:
// - $message: The message text to display (will be HTML-escaped)
// - $type: The type of message (e.g., "info", "error") - defaults to "info"
// - $redirect: Optional URL to redirect to; if null, shows a "Go Back" button
function popup_base($message, $type = "info", $redirect = null)
{
    // Output the HTML document structure
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars(ucfirst($type)) ?></title>
        <link rel="stylesheet" href="assets/styles/layout.css">
    </head>

    <body>
        <!-- ===== ERROR CONTAINER ===== -->
        <!-- Main container for the error/popup display -->
        <div class="error-container">
            <div class="error-content">
                <!-- ===== ERROR TITLE ===== -->
                <!-- Display the message type as the title -->
                <div class="error-title">
                    <?= htmlspecialchars(ucfirst($type)) ?>
                </div>

                <!-- ===== ERROR MESSAGE ===== -->
                <!-- Display the main message, properly escaped for security -->
                <div class="error-message">
                    <?= htmlspecialchars($message) ?>
                </div>

                <!-- ===== ERROR BUTTONS ===== -->
                <!-- Action buttons: either redirect link or go back button -->
                <div class="error-buttons">
                    <?php if ($redirect): ?>
                        <!-- If redirect URL provided, show continue button -->
                        <a href="<?= htmlspecialchars($redirect) ?>" class="btn-primary">
                            Continue
                        </a>
                    <?php else: ?>
                        <!-- Otherwise, show go back button -->
                        <button class="btn-primary" onclick="history.back()">
                            Go Back
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </body>

    </html>
    <?php
    // Exit to prevent further execution after displaying the popup
    exit();
}

// ===== POPUP ERROR FUNCTION =====
// Wrapper function for displaying error messages with a default "Oops!" title
// Parameters:
// - $msg: The error message to display
// - $redirect: Optional redirect URL (passed to popup_base)
function popup_error($msg, $redirect = null)
{
    // Call popup_base with "Oops!" as the type for error messages
    popup_base($msg, "Oops!", $redirect);
}