'use strict';

// ── PASSWORD VISIBILITY TOGGLE ────────────────────────────────
// Clicks on .pw-toggle button toggle the adjacent input between
// type=password (hidden) and type=text (visible).
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var input    = document.getElementById(targetId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.innerHTML = '&#128064;'; // open eye = currently visible
            } else {
                input.type = 'password';
                btn.innerHTML = '&#128065;'; // closed eye = currently hidden
            }
        });
    });
});

// ── PASSWORD STRENGTH INDICATOR ──────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var pwField    = document.getElementById('password');
    var indicator  = document.getElementById('pw-strength');
    if (!pwField || !indicator) return;

    pwField.addEventListener('input', function () {
        var val   = pwField.value;
        var score = 0;
        if (val.length >= 8)          score++;
        if (/[A-Z]/.test(val))        score++;
        if (/[0-9]/.test(val))        score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        var labels = ['', 'WEAK', 'FAIR', 'GOOD', 'STRONG'];
        var colors = ['', '#e03333', '#f0a500', '#cccc00', '#00e639'];
        indicator.textContent = val.length === 0 ? '' : labels[score] || 'WEAK';
        indicator.style.color = colors[score] || colors[1];
    });
});

// ── AUTO-HIDE SUCCESS ALERTS ──────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.atm-alert-success').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.6s';
            el.style.opacity    = '0';
            setTimeout(function () { el.remove(); }, 600);
        }, 4000);
    });
});

// ── TRANSFER CONFIRM MODAL ────────────────────────────────────
// Replaces browser window.confirm() with an in-page modal dialog.
// The modal shows recipient + amount and asks the user to confirm.
// Only on submit of the actual form button does the form get submitted.
document.addEventListener('DOMContentLoaded', function () {
    var trigger  = document.getElementById('transfer-trigger');
    var modal    = document.getElementById('transfer-modal');
    var confirm  = document.getElementById('modal-confirm');
    var cancel   = document.getElementById('modal-cancel');
    var form     = document.getElementById('transfer-form');

    if (!trigger || !modal || !form) return;

    trigger.addEventListener('click', function () {
        console.log("clicked");
        var username = document.getElementById('to_username');
        var amount   = document.getElementById('amount');
        if (!username || !amount) return;

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Populate modal with current values before showing
        document.getElementById('modal-recipient').textContent = username.value;
        document.getElementById('modal-amount').textContent    =
            'Rs. ' + parseFloat(amount.value || 0).toFixed(2);

        modal.style.display = 'flex';
    });

    if (cancel) {
        cancel.addEventListener('click', function () {
            modal.style.display = 'none';
        });
    }

    // Clicking overlay background also closes modal
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.style.display = 'none';
    });

    if (confirm) {
        confirm.addEventListener('click', function () {
            modal.style.display = 'none';
            form.submit();
        });
    }
});

// ── COMMENT CHARACTER COUNTER ─────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var comment = document.getElementById('comment');
    var counter = document.getElementById('comment-count');
    if (!comment || !counter) return;
    comment.addEventListener('input', function () {
        var left = 500 - comment.value.length;
        counter.textContent = left;
        counter.style.color = left < 50 ? '#e03333' : '';
    });
});

// ── BIO CHARACTER COUNTER ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var bio     = document.getElementById('bio');
    var counter = document.getElementById('bio-counter');
    if (!bio || !counter) return;
    var MAX = 5000;
    function update() {
        var left = MAX - bio.value.length;
        counter.textContent = left + ' left';
        counter.style.color = left < 200 ? '#e03333' : '';
    }
    bio.addEventListener('input', update);
    update();
});