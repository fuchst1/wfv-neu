(function () {
    const modal = document.getElementById('addBoatModal');
    const form = document.getElementById('addBoatForm');
    const openButton = document.getElementById('openAddBoat');

    if (!modal || !form || !openButton) {
        return;
    }

    const fields = {
        number: document.getElementById('boatNumber'),
        boatNotes: document.getElementById('boatNotes'),
    };

    Validation.attach(form);

    openButton.addEventListener('click', () => {
        resetForm();
        modal.hidden = false;
        fields.number.focus();
    });

    modal.querySelectorAll('[data-close]').forEach(button => {
        button.addEventListener('click', () => closeModal());
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.checkValidity()) {
            return;
        }

        const payload = {
            year: CURRENT_YEAR,
            boat: {
                bootnummer: fields.number.value,
                notizen: fields.boatNotes.value,
            },
        };

        fetch('api.php?action=save_boat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.message || 'Boot konnte nicht gespeichert werden.');
                }
            })
            .catch(() => alert('Boot konnte nicht gespeichert werden.'));
    });

    function resetForm() {
        form.reset();
        clearValidation();
    }

    function closeModal() {
        modal.hidden = true;
        form.reset();
        clearValidation();
    }

    function clearValidation() {
        form.querySelectorAll('.validation-hint').forEach(hint => {
            hint.textContent = '';
        });
        form.querySelectorAll('.validation-error').forEach(input => {
            input.classList.remove('validation-error');
        });
    }
})();
