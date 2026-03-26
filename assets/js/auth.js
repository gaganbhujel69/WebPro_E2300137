/* ============================================================
   EMS — Authentication Pages JavaScript
   assets/js/auth.js
   ============================================================ */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // ── 1. Role Toggle ──────────────────────────────────────
    const roleRadios = document.querySelectorAll('input[name="role"]');
    const providerFields = document.getElementById('providerFields');
    const providerInputs = providerFields
        ? providerFields.querySelectorAll('input, textarea, select')
        : [];

    function applyRoleToggle(role) {
        if (!providerFields) return;

        if (role === 'training_provider') {
            providerFields.classList.add('show');
            providerInputs.forEach(function (inp) {
                if (inp.dataset.required) inp.required = true;
            });
        } else {
            providerFields.classList.remove('show');
            providerInputs.forEach(function (inp) { inp.required = false; });
        }
    }

    roleRadios.forEach(function (radio) {
        radio.addEventListener('change', function () { applyRoleToggle(this.value); });
    });

    // Restore role on page load (e.g., after server-side validation fail)
    var checkedRole = document.querySelector('input[name="role"]:checked');
    if (checkedRole) applyRoleToggle(checkedRole.value);


    // ── 2. Password Visibility Toggles ──────────────────────
    document.querySelectorAll('.btn-pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = this.dataset.target;
            var input = document.getElementById(targetId);
            if (!input) return;

            var isHidden = (input.type === 'password');
            input.type = isHidden ? 'text' : 'password';
            this.innerHTML = isHidden
                ? '<i class="bi bi-eye-slash"></i>'
                : '<i class="bi bi-eye"></i>';
        });
    });


    // ── 3. Password Strength Meter ───────────────────────────
    var pwInput = document.getElementById('regPassword');
    var strengthBar = document.getElementById('pwStrengthFill');
    var strengthLbl = document.getElementById('pwStrengthLabel');

    var levels = [
        { label: '', color: '#e2e8f0', pct: 0 },
        { label: 'Weak', color: '#dc2626', pct: 25 },
        { label: 'Fair', color: '#f59e0b', pct: 50 },
        { label: 'Good', color: '#3b82f6', pct: 75 },
        { label: 'Strong', color: '#16a34a', pct: 100 },
    ];

    function calcStrength(val) {
        if (!val) return 0;
        var score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        return score; // 0–4
    }

    if (pwInput && strengthBar && strengthLbl) {
        pwInput.addEventListener('input', function () {
            var lvl = levels[calcStrength(this.value)];
            strengthBar.style.width = lvl.pct + '%';
            strengthBar.style.background = lvl.color;
            strengthLbl.textContent = lvl.label;
            strengthLbl.style.color = lvl.color;
        });
    }


    // ── 4. Live Password Confirm Match ──────────────────────
    var pwConfirm = document.getElementById('passwordConfirm');
    var matchMsg = document.getElementById('pwMatchMsg');

    if (pwInput && pwConfirm && matchMsg) {
        function checkMatch() {
            if (!pwConfirm.value) { matchMsg.textContent = ''; return; }
            if (pwInput.value === pwConfirm.value) {
                matchMsg.textContent = '✓ Passwords match';
                matchMsg.style.color = '#16a34a';
                pwConfirm.classList.remove('is-invalid');
            } else {
                matchMsg.textContent = '✗ Passwords do not match';
                matchMsg.style.color = '#dc2626';
                pwConfirm.classList.add('is-invalid');
            }
        }
        pwConfirm.addEventListener('input', checkMatch);
        if (pwInput) pwInput.addEventListener('input', checkMatch);
    }


    // ── 5. Client-side required field validation ─────────────
    var authForm = document.getElementById('authForm');
    if (authForm) {
        authForm.addEventListener('submit', function (e) {
            var valid = true;

            // Check all visible required fields
            authForm.querySelectorAll('[required]').forEach(function (field) {
                // Skip hidden/collapsed provider fields if role ≠ provider
                var inProviderSection = providerFields && providerFields.contains(field);
                if (inProviderSection && !providerFields.classList.contains('show')) return;

                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    valid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!valid) {
                e.preventDefault();
                // Scroll to first error
                var first = authForm.querySelector('.is-invalid');
                if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            // Show loading state on button
            var btn = document.getElementById('btnSubmitAuth');
            if (btn) btn.classList.add('loading');
        });

        // Clear invalid on input
        authForm.querySelectorAll('.form-control, .form-select').forEach(function (field) {
            field.addEventListener('input', function () { this.classList.remove('is-invalid'); });
        });
    }


    // ── 6. Animate form fields on load ─────────────────────
    var authInputGroups = document.querySelectorAll('.auth-input-group');
    authInputGroups.forEach(function (g, i) {
        g.style.opacity = '0';
        g.style.transform = 'translateY(12px)';
        g.style.transition = 'opacity .35s ease ' + (i * 0.05) + 's, transform .35s ease ' + (i * 0.05) + 's';
        requestAnimationFrame(function () {
            g.style.opacity = '1';
            g.style.transform = 'translateY(0)';
        });
    });

});
