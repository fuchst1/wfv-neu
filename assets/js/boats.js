(function () {
    const modal = document.getElementById('addBoatModal');
    const form = document.getElementById('addBoatForm');
    const openButton = document.getElementById('openAddBoat');

    if (!modal || !form || !openButton) {
        return;
    }

    const fields = {
        year: document.getElementById('boatYear'),
        number: document.getElementById('boatNumber'),
        cost: document.getElementById('boatCost'),
        tip: document.getElementById('boatTip'),
        total: document.getElementById('boatTotal'),
        date: document.getElementById('boatDate'),
        firstName: document.getElementById('boatFirstName'),
        lastName: document.getElementById('boatLastName'),
        street: document.getElementById('boatStreet'),
        zip: document.getElementById('boatZip'),
        city: document.getElementById('boatCity'),
        phone: document.getElementById('boatPhone'),
        email: document.getElementById('boatEmail'),
        card: document.getElementById('boatCard'),
        licenseNotes: document.getElementById('boatLicenseNotes'),
        boatNotes: document.getElementById('boatNotes'),
    };

    Validation.attach(form);

    openButton.addEventListener('click', () => {
        resetForm();
        modal.hidden = false;
        fields.firstName.focus();
    });

    modal.querySelectorAll('[data-close]').forEach(button => {
        button.addEventListener('click', () => closeModal());
    });

    fields.cost.addEventListener('input', updateTotal);
    fields.tip.addEventListener('input', updateTotal);

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.checkValidity()) {
            return;
        }

        const payload = {
            year: parseInt(fields.year.value, 10),
            license: {
                id: null,
                lizenztyp: 'Boot',
                kosten: fields.cost.value,
                trinkgeld: fields.tip.value || 0,
                zahlungsdatum: fields.date.value,
                notizen: fields.licenseNotes.value,
            },
            licensee: {
                id: null,
                vorname: fields.firstName.value,
                nachname: fields.lastName.value,
                strasse: fields.street.value,
                plz: fields.zip.value,
                ort: fields.city.value,
                telefon: fields.phone.value,
                email: fields.email.value,
                fischerkartennummer: fields.card.value,
            },
            boat: {
                bootnummer: fields.number.value,
                notizen: fields.boatNotes.value,
            },
        };

        fetch('api.php?action=save_license', {
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
        fields.year.value = CURRENT_YEAR;
        const defaultCost = typeof BOOT_PRICE === 'number' ? BOOT_PRICE : parseFloat(BOOT_PRICE || '0');
        fields.cost.value = Number(defaultCost || 0).toFixed(2);
        fields.tip.value = '0.00';
        fields.total.value = (Number(fields.cost.value) + Number(fields.tip.value)).toFixed(2);
        fields.date.value = getTodayDateString();
    }

    function closeModal() {
        modal.hidden = true;
        form.reset();
        clearValidation();
    }

    function updateTotal() {
        const cost = parseFloat(fields.cost.value || '0');
        const tip = parseFloat(fields.tip.value || '0');
        fields.total.value = (cost + tip).toFixed(2);
    }

    function clearValidation() {
        form.querySelectorAll('.validation-hint').forEach(hint => {
            hint.textContent = '';
        });
        form.querySelectorAll('.validation-error').forEach(input => {
            input.classList.remove('validation-error');
        });
    }

    function getTodayDateString() {
        const today = new Date();
        today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
        return today.toISOString().split('T')[0];
    }
})();
