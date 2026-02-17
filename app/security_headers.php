<?php
// =========================
// ../app/security_headers.php
// Put this file in: app/security_headers.php
// =========================

function send_security_headers(): void
{
    $csp = implode("; ", [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "img-src 'self' data:",
        "style-src 'self' 'unsafe-inline'",
        "script-src 'self'",
        "connect-src 'self'",
        "form-action 'self'",
        "upgrade-insecure-requests"
    ]);

    header("Content-Security-Policy: $csp");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("X-Frame-Options: SAMEORIGIN");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}
?>