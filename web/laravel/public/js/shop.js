/*
 * Shop behaviour (resources/views/shop/*): the quantity stepper, the checkout button, and the card
 * payment's card picture, which fills in as you type. A separate file because the CSP allows no
 * inline script. It only builds elements and sets text, classes and data attributes: never
 * innerHTML (typed text must not become markup) and never inline styles (the CSP blocks them).
 */
(() => {
    'use strict';

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Medicine page: − and + around the quantity input, within its min and max
    document.querySelectorAll('[data-stepper]').forEach((stepper) => {
        const input = stepper.querySelector('input');
        const buttons = stepper.querySelectorAll('[data-step]');
        const sync = () => {
            const value = Number(input.value) || 1;
            buttons.forEach((button) => {
                button.disabled = Number(button.dataset.step) < 0 ? value <= Number(input.min) : value >= Number(input.max);
            });
        };

        buttons.forEach((button) => {
            button.hidden = false;
            button.addEventListener('click', () => {
                const next = (Number(input.value) || 1) + Number(button.dataset.step);
                input.value = String(Math.min(Number(input.max), Math.max(Number(input.min), next)));
                sync();
            });
        });
        input.addEventListener('input', sync);
        sync();
    });

    // Checkout: the button says what comes next, and waits for the consent box
    const checkout = document.querySelector('[data-checkout]');
    if (checkout) {
        const submit = checkout.querySelector('[data-checkout-submit]');
        const consent = checkout.querySelector('#consent');
        const hint = checkout.querySelector('[data-consent-hint]');
        const update = () => {
            const payment = checkout.querySelector('input[name="payment"]:checked');
            submit.textContent = payment && payment.value === 'counter' ? submit.dataset.labelCounter : submit.dataset.labelCard;
            submit.disabled = !consent.checked;
            hint.hidden = consent.checked;
        };
        checkout.addEventListener('change', update);
        update();
    }

    // Card payment
    const card = document.querySelector('[data-card]');
    if (!card) {
        return;
    }

    // Plays the "contacting the bank" wait on a busy button before the form really goes
    const sendAfterAWait = (form, ms, before) => {
        const button = form.querySelector('[data-busy-button]');
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const label = button.querySelector('[data-busy-label]');
            label.textContent = label.dataset.busyLabel;
            button.classList.add('is-busy');
            button.disabled = true;
            before();
            setTimeout(() => form.submit(), reducedMotion ? 0 : ms);
        });
    };

    const payForm = document.querySelector('[data-pay-form]');
    if (payForm) {
        setUpCardForm(payForm);
        sendAfterAWait(payForm, 1700, () => {
            delete card.dataset.focus;
            card.dataset.state = 'paying';
        });
    }

    const confirmForm = document.querySelector('[data-confirm-form]');
    if (confirmForm) {
        sendAfterAWait(confirmForm, 1300, () => {
            const bank = confirmForm.closest('[data-bank]');
            bank.querySelector('[data-message-code]').hidden = true;
            bank.querySelector('[data-message-done]').hidden = false;
        });
    }

    function setUpCardForm(form) {
        const fields = {
            number: form.querySelector('[data-card-input="number"]'),
            name: form.querySelector('[data-card-input="name"]'),
            expiry: form.querySelector('[data-card-input="expiry"]'),
            cvc: form.querySelector('[data-card-input="cvc"]'),
        };
        const tidy = {
            number: (value) => value.replace(/\D/g, '').slice(0, 16).replace(/(\d{4})(?=\d)/g, '$1 '),
            name: (value) => value.slice(0, 26),
            expiry: (value) => {
                const digits = value.replace(/\D/g, '').slice(0, 4);
                return digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
            },
            cvc: (value) => value.replace(/\D/g, '').slice(0, 3),
        };
        const shown = { number: '', name: '', expiry: '', cvc: '' };

        // One span per character; characters that changed since the last draw pop in
        const char = (className, text, previous, i, placeholder) => {
            const span = document.createElement('span');
            const ch = text.charAt(i);
            span.className = className;
            if (ch) {
                span.textContent = ch;
                if (ch !== previous.charAt(i)) {
                    span.classList.add('is-new');
                }
            } else {
                span.textContent = placeholder;
                span.classList.add('is-empty');
            }
            return span;
        };

        const draw = {
            number() {
                const digits = fields.number.value.replace(/\D/g, '');
                const groups = [0, 1, 2, 3].map((g) => {
                    const group = document.createElement('span');
                    group.className = 'card__group';
                    for (let k = 0; k < 4; k++) {
                        group.append(char('card__slot', digits, shown.number, g * 4 + k, '•'));
                    }
                    return group;
                });
                card.querySelector('[data-card-number]').replaceChildren(...groups);
                shown.number = digits;
            },
            name() {
                const name = fields.name.value.toUpperCase().replace(/ /g, ' ').slice(0, 22);
                const target = card.querySelector('[data-card-name]');
                if (name.replace(/ /g, '') === '') {
                    target.replaceChildren(char('', '', '', 0, 'YOUR NAME'));
                } else {
                    target.replaceChildren(...[...name].map((_, i) => char('card__char', name, shown.name, i, '')));
                }
                shown.name = name;
            },
            expiry() {
                const expiry = fields.expiry.value;
                card.querySelector('[data-card-expiry]').replaceChildren(...[...'MM/YY'].map((placeholder, i) => char('card__slot', expiry, shown.expiry, i, placeholder)));
                shown.expiry = expiry;
            },
            cvc() {
                const cvc = fields.cvc.value;
                card.querySelector('[data-card-cvc]').replaceChildren(...[0, 1, 2].map((i) => char('card__slot', cvc, shown.cvc, i, '•')));
                shown.cvc = cvc;
            },
        };

        const set = (key, value) => {
            fields[key].value = tidy[key](value);
            draw[key]();
        };

        Object.entries(fields).forEach(([key, input]) => {
            input.addEventListener('input', () => set(key, input.value));
            input.addEventListener('focus', () => {
                card.dataset.focus = key;
            });
            input.addEventListener('blur', () => {
                delete card.dataset.focus;
            });
        });
        draw.name();

        // "Demo cards": types one in, a character at a time, so the card fills in and turns over
        let typing = null;
        const typeIn = (number) => {
            const steps = [() => {
                Object.keys(fields).forEach((key) => set(key, ''));
                card.dataset.focus = 'number';
            }];
            for (let i = 1; i <= 16; i++) {
                steps.push(() => set('number', number.slice(0, i)));
            }
            steps.push(() => {
                card.dataset.focus = 'name';
            });
            const name = 'Test Client';
            for (let i = 1; i <= name.length; i++) {
                steps.push(() => set('name', name.slice(0, i)));
            }
            steps.push(() => {
                card.dataset.focus = 'expiry';
            });
            ['1', '12', '123', '1230'].forEach((value) => steps.push(() => set('expiry', value)));
            steps.push(() => {
                card.dataset.focus = 'cvc';
            }, null, null, null);
            ['1', '12', '123'].forEach((value) => steps.push(() => set('cvc', value)));
            steps.push(null, null, null, null, null, null, () => {
                delete card.dataset.focus;
            });

            clearInterval(typing);
            if (reducedMotion) {
                steps.forEach((step) => step && step());
                return;
            }
            typing = setInterval(() => {
                const step = steps.shift();
                if (step === undefined) {
                    clearInterval(typing);
                } else if (step) {
                    step();
                }
            }, 55);
        };

        const demo = form.querySelector('[data-demo-cards]');
        demo.hidden = false;
        demo.querySelectorAll('[data-demo-card]').forEach((button) => {
            button.addEventListener('click', () => typeIn(button.dataset.demoCard));
        });
    }
})();
