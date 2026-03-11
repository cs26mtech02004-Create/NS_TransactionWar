<?php
/**
 * FILE: includes/security_headers.php
 * PURPOSE: Set HTTP security headers on every response.
 *
 * HTTP headers are instructions the server sends to the browser telling
 * it how to behave when handling the response. Several headers tell the
 * browser to enforce security policies that block entire classes of attack.
 *
 * Include this file BEFORE any output (before any echo or HTML).
 * Headers cannot be sent after output has started.
 */

/**
 * send_security_headers()
 * Call once at the top of every page (header.php calls it automatically).
 */
function send_security_headers(): void {

    // X-Content-Type-Options: nosniff
    //   Browsers sometimes "sniff" (guess) the content type of a response
    //   even if the server declares it. An attacker could upload a file
    //   that looks like an image but contains HTML/JS. This header tells
    //   the browser: "trust the Content-Type header I send, never guess."
    header('X-Content-Type-Options: nosniff');

    // X-Frame-Options: DENY
    //   Prevents your pages from being embedded in an <iframe> on another site.
    //   Clickjacking attack: attacker puts your page in a transparent iframe
    //   over their site — victim thinks they're clicking on a game button
    //   but they're actually clicking "Confirm Transfer" on your app.
    //   DENY = cannot be framed by anyone, including yourself.
    header('X-Frame-Options: DENY');

    // X-XSS-Protection: 1; mode=block
    //   Enables the browser's built-in reflected XSS filter.
    //   If the browser detects a script in the URL being reflected in the
    //   response, it blocks the page entirely rather than rendering it.
    //   (Modern browsers have mostly replaced this with CSP, but it's a
    //   useful fallback for older browsers.)
    header('X-XSS-Protection: 1; mode=block');

    // Referrer-Policy: strict-origin-when-cross-origin
    //   Controls what URL is sent in the Referer header when a user clicks
    //   a link to leave your site. "strict-origin-when-cross-origin" means:
    //   - Same-site navigation: full URL sent (for analytics)
    //   - Cross-site navigation: only the origin (no path, no query string)
    //   This prevents session tokens or sensitive query parameters from
    //   leaking to third-party sites via the Referer header.
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content-Security-Policy (CSP)
    //   The most powerful XSS defence available. Tells the browser exactly
    //   which sources of scripts, styles, and images are allowed.
    //   Any script from an unlisted source is BLOCKED — even if an attacker
    //   manages to inject a <script> tag, the browser won't run it.
    //
    //   Breaking down the directives:
    //   default-src 'self'        → By default, only load resources from our own domain
    //   script-src 'self'         → Only run scripts from our own domain (no inline scripts,
    //                               no external CDN scripts unless explicitly listed)
    //   style-src 'self' 'unsafe-inline' → Our own CSS + inline styles (needed for ATM theme)
    //   img-src 'self' data:      → Images from our domain or base64 data URIs
    //   font-src 'self'           → Fonts from our domain only
    //   object-src 'none'         → Block all <object>, <embed>, <applet> — Flash/plugin attacks
    //   base-uri 'self'           → Prevent <base> tag injection (changes all relative URLs)
    //   form-action 'self'        → Forms can only POST to our own domain
    //   frame-ancestors 'none'    → Cannot be framed (same as X-Frame-Options DENY but in CSP)
    header("Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self'; " .
        "style-src 'self' 'unsafe-inline'; " .
        "img-src 'self' data:; " .
        "font-src 'self'; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self'; " .
        "frame-ancestors 'none';"
    );

    // Permissions-Policy
    //   Disables browser features this app doesn't need.
    //   Prevents malicious scripts from accessing camera, microphone,
    //   geolocation etc. even if somehow injected.
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()");
}