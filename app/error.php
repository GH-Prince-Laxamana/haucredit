<?php

function popup_base($message, $type = "info", $redirect = null, $fallback = "index.php")
{
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $return_url = $redirect ?: ($referrer !== '' ? $referrer : $fallback);
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
        <div class="error-container">
            <div class="error-content">
                <div class="error-title">
                    <?= htmlspecialchars(ucfirst($type)) ?>
                </div>

                <div class="error-message">
                    <?= htmlspecialchars($message) ?>
                </div>

                <div class="error-buttons">
                    <a href="<?= htmlspecialchars($return_url) ?>" class="btn-primary">
                        <?= $redirect ? 'Continue' : 'Go Back' ?>
                    </a>
                </div>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit();
}

function popup_error($msg, $redirect = null, $fallback = "index.php")
{
    popup_base($msg, "Oops!", $redirect, $fallback);
}