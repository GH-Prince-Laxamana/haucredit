<?php

function send_security_headers(): void
{
    $csp = implode("; ", [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "img-src 'self' data:",
        "style-src 'self' 'unsafe-inline'",
        "script-src 'self' https://kit.fontawesome.com",
        "font-src 'self' https://ka-f.fontawesome.com",
        "connect-src 'self' https://ka-f.fontawesome.com https://kit.fontawesome.com",
        "form-action 'self'",
        "upgrade-insecure-requests"
    ]);

    header("Content-Security-Policy: $csp");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("X-Frame-Options: SAMEORIGIN");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}