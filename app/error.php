<?php

function popup_base($message, $type = "info", $redirect = null)
{
    ?>
    <!DOCTYPE html>
    <html>

    <head>
        <meta charset="UTF-8">
        <title><?= ucfirst($type) ?></title>

        <link rel="stylesheet" href="assets/styles/layout.css">
    </head>

    <body>

        <div class="error-container">
            <div class="error-content">

                <div class="error-title">
                    <?= ucfirst($type) ?>
                </div>

                <div class="error-message">
                    <?= htmlspecialchars($message) ?>
                </div>

                <div class="error-buttons">

                    <?php if ($redirect): ?>
                        <a href="<?= $redirect ?>" class="btn-primary">
                            Continue
                        </a>
                    <?php else: ?>
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
    exit();
}

function popup_error($msg, $redirect = null)
{
    popup_base($msg, "Oops!", $redirect);
}