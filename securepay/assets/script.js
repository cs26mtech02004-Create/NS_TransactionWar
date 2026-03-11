/**
 * FILE: assets/script.js
 * PURPOSE: Minimal client-side enhancements for the ATM UI.
 *
 * SECURITY PHILOSOPHY:
 *   Client-side JavaScript is COSMETIC ONLY in this application.
 *   Every security check (CSRF, auth, input validation, balance check)
 *   happens on the SERVER in PHP. JS validation is a UX convenience —
 *   it can always be disabled or bypassed by the user.
 *
 *   Never trust client-side code for security decisions.
 */

'use strict';

// ── AUTO-HIDE ALERTS ─────────────────────────────────────────
// Success messages fade out after 4 seconds so the user isn't
// staring at stale feedback.
document.addEventListener('DOMContentLoaded', function () {
    const successAlerts = document.querySelectorAll('.atm-alert-success');
    successAlerts.forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.8s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 800);
        }, 4000);
    });
});

// ── CONFIRM TRANSFER ─────────────────────────────────────────
// Asks user to confirm before submitting a money transfer.
// NOTE: This is UX only — the server validates everything.
document.addEventListener('DOMContentLoaded', function () {
    const transferForm = document.getElementById('transfer-form');
    if (transferForm) {
        transferForm.addEventListener('submit', function (e) {
            const amount = document.getElementById('amount');
            const toUser = document.getElementById('to_user_id');
            if (amount && toUser) {
                const confirmed = window.confirm(
                    'CONFIRM TRANSFER\n\n' +
                    'To User ID : ' + toUser.value + '\n' +
                    'Amount     : Rs. ' + parseFloat(amount.value).toFixed(2) + '\n\n' +
                    'This transaction cannot be reversed.\n' +
                    'Press OK to confirm.'
                );
                if (!confirmed) {
                    e.preventDefault();
                }
            }
        });
    }
});

// ── PASSWORD STRENGTH INDICATOR ──────────────────────────────
// Visual feedback only — real validation is in PHP.
document.addEventListener('DOMContentLoaded', function () {
    const pwField = document.getElementById('password');
    const indicator = document.getElementById('pw-strength');
    if (!pwField || !indicator) return;

    pwField.addEventListener('input', function () {
        const val = pwField.value;
        let score = 0;
        if (val.length >= 8)          score++;
        if (/[A-Z]/.test(val))        score++;
        if (/[0-9]/.test(val))        score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const labels = ['', 'WEAK', 'FAIR', 'GOOD', 'STRONG'];
        const colors = ['', '#ff3333', '#ffaa00', '#ffdd00', '#00ff41'];

        indicator.textContent = val.length === 0 ? '' : '[ ' + (labels[score] || 'WEAK') + ' ]';
        indicator.style.color = colors[score] || colors[1];
    });
});

// ── CHARACTER COUNTER FOR BIOGRAPHY ──────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const bio = document.getElementById('bio');
    const counter = document.getElementById('bio-counter');
    if (!bio || !counter) return;

    const MAX = 5000;
    function updateCounter() {
        const remaining = MAX - bio.value.length;
        counter.textContent = remaining + ' chars remaining';
        counter.style.color = remaining < 200 ? '#ff3333' : '#007a1a';
    }
    bio.addEventListener('input', updateCounter);
    updateCounter();
});