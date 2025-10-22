const Validation = (() => {
    const validators = {
        required(value) {
            return value.trim().length > 0;
        },
        email(value) {
            if (!value) return true;
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        },
        zip(value) {
            if (!value) return true;
            return /^\d{4,5}$/.test(value.trim());
        }
    };

    function validateInput(input) {
        const rules = (input.dataset.validate || '').split(',').map(r => r.trim()).filter(Boolean);
        let valid = true;
        let message = '';
        for (const rule of rules) {
            if (validators[rule] && !validators[rule](input.value || '')) {
                valid = false;
                message = rule === 'required' ? 'Pflichtfeld' : 'Bitte prüfen';
                break;
            }
        }
        toggleState(input, valid, message);
        return valid;
    }

    function toggleState(input, valid, message) {
        let hint = input.nextElementSibling;
        if (!hint || !hint.classList.contains('validation-hint')) {
            hint = document.createElement('span');
            hint.className = 'validation-hint';
            input.insertAdjacentElement('afterend', hint);
        }
        if (valid) {
            input.classList.remove('validation-error');
            hint.textContent = '';
        } else {
            input.classList.add('validation-error');
            hint.textContent = message;
        }
    }

    function attach(form) {
        const inputs = form.querySelectorAll('input[data-validate], select[data-validate], textarea[data-validate]');
        inputs.forEach(input => {
            input.addEventListener('input', () => validateInput(input));
            input.addEventListener('blur', () => validateInput(input));
        });

        form.addEventListener('submit', event => {
            let allValid = true;
            inputs.forEach(input => {
                if (!validateInput(input)) {
                    allValid = false;
                }
            });
            if (!allValid) {
                event.preventDefault();
                form.querySelector('.validation-error')?.focus();
            }
        });
    }

    return { attach, validateInput };
})();
