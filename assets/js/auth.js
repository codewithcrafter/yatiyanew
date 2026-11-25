/**
 * =====================================================================
 * YATIYA COUTURE - Client-Side Authentication Controller
 * Pure Vanilla JavaScript (No Frameworks or Libraries)
 * Password toggles, dynamic password strength meter, and form validation
 * =====================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    initPasswordToggles();
    initPasswordStrengthMeter();
    initFormValidation();
});

/**
 * Password Visibility Toggles
 */
function initPasswordToggles() {
    const toggleButtons = document.querySelectorAll('.password-toggle-btn');
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            // Toggle icon visual
            const svg = btn.querySelector('svg');
            if (svg) {
                if (isPassword) {
                    // Open eye icon
                    svg.innerHTML = `
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    `;
                } else {
                    // Slashed eye icon
                    svg.innerHTML = `
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    `;
                }
            }
        });
    });
}

/**
 * Dynamic Password Strength Meter for Registration and Reset
 */
function initPasswordStrengthMeter() {
    const passwordInput = document.getElementById('regPassword') || document.getElementById('newPassword');
    const fillBar = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');

    if (!passwordInput || !fillBar) return;

    passwordInput.addEventListener('input', function () {
        const val = passwordInput.value;
        let score = 0;

        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        if (val.length === 0) {
            fillBar.style.width = '0%';
            if (label) label.innerText = 'At least 8 characters with letters & numbers';
            return;
        }

        switch (score) {
            case 1:
                fillBar.style.width = '25%';
                fillBar.style.backgroundColor = '#EF4444'; // Weak Red
                if (label) label.innerText = 'Weak — add uppercase, numbers, or symbols';
                break;
            case 2:
                fillBar.style.width = '50%';
                fillBar.style.backgroundColor = '#F59E0B'; // Medium Amber
                if (label) label.innerText = 'Fair — add special characters for luxury security';
                break;
            case 3:
                fillBar.style.width = '75%';
                fillBar.style.backgroundColor = '#3B82F6'; // Good Blue
                if (label) label.innerText = 'Good security level';
                break;
            case 4:
                fillBar.style.width = '100%';
                fillBar.style.backgroundColor = '#10B981'; // Strong Green
                if (label) label.innerText = 'Excellent luxury security';
                break;
        }
    });
}

/**
 * Client-Side Instant Pre-Validation
 */
function initFormValidation() {
    const regForm = document.getElementById('registerForm');
    if (regForm) {
        regForm.addEventListener('submit', function (e) {
            const pass = document.getElementById('regPassword')?.value;
            const confirm = document.getElementById('regConfirmPassword')?.value;

            if (pass !== confirm) {
                e.preventDefault();
                alert('Password and confirm password do not match.');
                document.getElementById('regConfirmPassword')?.focus();
            }
        });
    }

    const resetForm = document.getElementById('resetPasswordForm');
    if (resetForm) {
        resetForm.addEventListener('submit', function (e) {
            const pass = document.getElementById('newPassword')?.value;
            const confirm = document.getElementById('confirmNewPassword')?.value;

            if (pass !== confirm) {
                e.preventDefault();
                alert('New password and confirmation do not match.');
                document.getElementById('confirmNewPassword')?.focus();
            }
        });
    }
}

