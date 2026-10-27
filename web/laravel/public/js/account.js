/*
 * Login, sign-up and the account pages (resources/views/auth, resources/views/account): the Show
 * button of password fields, the live check of a new password, a new picture for the picture
 * check, and copying, downloading or printing the recovery codes. A separate file because the CSP
 * allows no inline script; it changes attributes and text, never markup. Every page works without
 * it: the buttons it needs start hidden.
 */
(() => {
    'use strict';

    // Show or hide a password
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) {
            return;
        }
        button.setAttribute('aria-label', 'Show password');
        button.hidden = false;
        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    // A new password: the bar, its word and the 12-character rule, as the person types
    const levels = ['Choose a password', 'Too short', 'Almost: keep going', 'Good', 'Strong'];
    document.querySelectorAll('[data-password-rules]').forEach((rules) => {
        const input = document.getElementById(rules.dataset.passwordRules);
        const bar = rules.querySelector('.strength');
        const label = rules.querySelector('[data-strength-label]');
        const rule = rules.querySelector('[data-length-rule]');
        const count = rules.querySelector('[data-length-count]');
        if (!input || !bar || !label || !rule || !count) {
            return;
        }
        const update = () => {
            const length = input.value.length;
            const level = length === 0 ? 0 : length < 8 ? 1 : length < 12 ? 2 : length < 16 ? 3 : 4;
            bar.dataset.level = String(level);
            label.textContent = levels[level];
            label.hidden = false;
            rule.classList.toggle('is-met', length >= 12);
            count.textContent = ` (${length} now)`;
            count.hidden = false;
        };
        input.addEventListener('input', update);
        update();
    });

    // A new picture for the picture check, without reloading the page
    document.querySelectorAll('[data-captcha-refresh]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const picture = document.querySelector('[data-captcha]');
            if (!picture) {
                return;
            }
            event.preventDefault();
            const url = new URL(picture.src);
            url.search = String(Date.now());
            picture.src = url.toString();
            document.getElementById('captcha')?.focus();
        });
    });

    // The text to copy: a list of codes one per line, or a key without its spaces
    const textOf = (element) => {
        const codes = element.querySelectorAll('code');
        if (codes.length > 0) {
            return Array.from(codes, (code) => code.textContent.trim()).join('\n');
        }
        return element.textContent.replace(/\s+/g, '');
    };

    const flash = (button, text) => {
        const original = button.textContent;
        button.textContent = text;
        setTimeout(() => {
            button.textContent = original;
        }, 2000);
    };

    if (navigator.clipboard) {
        document.querySelectorAll('[data-copy]').forEach((button) => {
            const source = document.getElementById(button.dataset.copy);
            if (!source) {
                return;
            }
            button.hidden = false;
            button.addEventListener('click', () => {
                navigator.clipboard.writeText(textOf(source)).then(() => flash(button, 'Copied'));
            });
        });
    }

    document.querySelectorAll('[data-download]').forEach((button) => {
        const source = document.getElementById(button.dataset.download);
        if (!source) {
            return;
        }
        button.hidden = false;
        button.addEventListener('click', () => {
            const file = new Blob([`${document.title}\n\n${textOf(source)}\n`], { type: 'text/plain' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(file);
            link.download = source.dataset.codesFile || 'recovery-codes.txt';
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        });
    });

    document.querySelectorAll('[data-print]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => window.print());
    });
})();
