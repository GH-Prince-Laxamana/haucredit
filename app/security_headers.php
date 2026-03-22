<?php
/**
 * Security Headers Configuration
 * 
 * This file provides the security headers that should be sent with every HTTP response.
 * These headers implement Content Security Policy (CSP) and other security measures
 * to protect against XSS, clickjacking, MIME type sniffing, and other web vulnerabilities.
 */

/**
 * Sends comprehensive security headers with the HTTP response
 * 
 * This function sets multiple security headers that:
 * - Enforce Content Security Policy to prevent XSS and injection attacks
 * - Prevent clickjacking and MIME type sniffing
 * - Restrict resource origins and permissions
 * - Enforce HTTPS for insecure connections
 * 
 * Should be called early in the request lifecycle (before any output is sent)
 * to ensure headers are properly sent to the client.
 * 
 * @return void - Sends HTTP headers to the client
 */
function send_security_headers(): void
{
    // ========== Content Security Policy (CSP) Construction ==========
    // CSP is a powerful security mechanism that constrains which resources
    // the browser can load and execute. This prevents XSS attacks and injection exploits.
    
    // Build CSP header value by combining individual directives
    $csp = implode("; ", [
        // Default source: Only load resources from the same origin (self)
        // This is the fallback policy for all resource types not explicitly defined
        "default-src 'self'",
        
        // Base URI: Restrict the <base> element to same origin only
        // Prevents attackers from injecting a different base URL
        "base-uri 'self'",
        
        // Object source: Disallow Flash, Java, and other legacy plugins entirely
        // 'none' prevents all object, embed, and applet elements
        "object-src 'none'",
        
        // Frame ancestors: Allow embedding only in frames from the same origin
        // Prevents the page from being framed by malicious sites (clickjacking protection)
        "frame-ancestors 'self'",
        
        // Image source: Allow images from same origin and data: URIs
        // data: URIs allow inline SVG and base64 encoded images
        "img-src 'self' data:",
        
        // Style source: Allow stylesheets from same origin and inline styles
        // 'unsafe-inline' is used here for practical CSS flexibility
        "style-src 'self' 'unsafe-inline'",
        
        // Script source: Allow scripts from same origin and FontAwesome CDN
        // Restricts to specific trusted sources to prevent malicious script injection
        "script-src 'self' https://kit.fontawesome.com",
        
        // Font source: Allow fonts from same origin and FontAwesome CDN
        // ka-f.fontawesome.com hosts the actual font files
        "font-src 'self' https://ka-f.fontawesome.com",
        
        // Connection source: Allow fetch, XHR, WebSocket, etc. to trusted origins
        // Restricts external API calls and prevents data exfiltration
        "connect-src 'self' https://ka-f.fontawesome.com https://kit.fontawesome.com",
        
        // Form action: Restrict where forms can be submitted
        // 'self' ensures forms only submit back to the same origin
        "form-action 'self'",
        
        // Upgrade insecure requests: Automatically upgrade HTTP to HTTPS
        // Forces secure encrypted connections even if resources specify http://
        "upgrade-insecure-requests"
    ]);

    // ========== Send Security Headers ==========
    
    // Content-Security-Policy header: Enforces the CSP directives defined above
    header("Content-Security-Policy: $csp");
    
    // X-Content-Type-Options: Prevents MIME type sniffing
    // 'nosniff' forces browser to respect the Content-Type header and not guess
    // Protects against MIME type confusion attacks
    header("X-Content-Type-Options: nosniff");
    
    // Referrer-Policy: Controls how much referrer information is sent to external sites
    // 'strict-origin-when-cross-origin' sends full URL for same-origin, only origin for cross-origin
    // Reduces information leakage while maintaining functionality
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // X-Frame-Options: Legacy clickjacking protection (superseded by CSP frame-ancestors)
    // 'SAMEORIGIN' prevents page from being framed by other origins
    // Included for broader browser compatibility with older browsers
    header("X-Frame-Options: SAMEORIGIN");
    
    // Permissions-Policy: Controls which browser features/APIs the page can use
    // Disables geolocation, microphone, and camera to protect user privacy
    // () = empty allowlist means feature is completely disabled
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}