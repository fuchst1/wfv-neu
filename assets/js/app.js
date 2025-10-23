(function () {
    const licenseModal = document.getElementById('licenseModal');
    const licenseForm = document.getElementById('licenseForm');
    const deleteModal = document.getElementById('deleteModal');
    const extendModal = document.getElementById('extendModal');
    const createYearModal = document.getElementById('createYearModal');
    const blockWarningModal = document.getElementById('blockWarningModal');
    const blockWarningText = document.getElementById('blockWarningText');
    const confirmBlockOverride = document.getElementById('confirmBlockOverride');
    const cancelBlockWarning = document.getElementById('cancelBlockWarning');
    const closeBlockWarningButton = document.getElementById('closeBlockWarning');

    const licenseFields = {
        id: document.getElementById('licenseId'),
        licenseeId: document.getElementById('licenseeId'),
        firstName: document.getElementById('vorname'),
        lastName: document.getElementById('nachname'),
        street: document.getElementById('strasse'),
        zip: document.getElementById('plz'),
        city: document.getElementById('ort'),
        phone: document.getElementById('telefon'),
        email: document.getElementById('email'),
        card: document.getElementById('fischerkartennummer'),
        type: document.getElementById('lizenztyp'),
        cost: document.getElementById('kosten'),
        tip: document.getElementById('trinkgeld'),
        total: document.getElementById('gesamt'),
        date: document.getElementById('zahlungsdatum'),
        notes: document.getElementById('lizenzNotizen'),
        boatNumber: document.getElementById('bootnummer'),
        boatNotes: document.getElementById('bootNotizen'),
        boatDetails: document.getElementById('boatDetails'),
    };

    const extendFields = {
        year: document.getElementById('extendYear'),
        cost: document.getElementById('extendCost'),
        tip: document.getElementById('extendTip'),
        total: document.getElementById('extendTotal'),
        date: document.getElementById('extendDate'),
        notes: document.getElementById('extendNotes'),
    };

    const createYearForm = document.getElementById('createYearForm');

    let currentRow = null;
    let currentLicense = null;
    let pendingBlockPayload = null;

    Validation.attach(licenseForm);
    Validation.attach(document.getElementById('extendForm'));
    Validation.attach(createYearForm);

    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', () => closeModals());
    });

    if (cancelBlockWarning) {
        cancelBlockWarning.addEventListener('click', () => {
            closeBlockWarning();
        });
    }

    if (closeBlockWarningButton) {
        closeBlockWarningButton.addEventListener('click', () => {
            closeBlockWarning();
        });
    }

    if (confirmBlockOverride) {
        confirmBlockOverride.addEventListener('click', () => {
            if (!pendingBlockPayload) {
                closeBlockWarning();
                return;
            }
            blockWarningModal.hidden = true;
            submitLicensePayload(pendingBlockPayload, true);
        });
    }

    document.getElementById('openAddLicense').addEventListener('click', () => {
        openLicenseModal(null);
    });

    document.getElementById('openCreateYear').addEventListener('click', () => {
        createYearModal.hidden = false;
    });

    document.querySelectorAll('tbody tr[data-license]').forEach(row => {
        const data = JSON.parse(row.dataset.license);
        row.querySelector('.edit').addEventListener('click', () => openLicenseModal(data, row));
        row.querySelector('.delete').addEventListener('click', () => openDeleteModal(data, row));
        row.querySelector('.extend').addEventListener('click', () => openExtendModal(data, row));
    });

    if (typeof OPEN_LICENSE_MODAL !== 'undefined' && OPEN_LICENSE_MODAL) {
        if (OPEN_LICENSE_MODAL === 'boat') {
            openLicenseModal(null);
            licenseFields.type.value = 'Boot';
            licenseFields.type.dispatchEvent(new Event('change'));
        } else if (OPEN_LICENSE_MODAL === 'license') {
            openLicenseModal(null);
        }
    }

    licenseFields.tip.addEventListener('input', updateTotal);
    licenseFields.cost.addEventListener('input', updateTotal);
    extendFields.tip.addEventListener('input', updateExtendTotal);
    extendFields.cost.addEventListener('input', updateExtendTotal);

    licenseFields.type.addEventListener('change', event => {
        const type = event.target.value;
        if (LICENSE_PRICES[type]) {
            licenseFields.cost.value = Number(LICENSE_PRICES[type]).toFixed(2);
        }
        toggleBoat(type === 'Boot');
        updateTotal();
    });

    extendFields.year.addEventListener('change', event => {
        const year = event.target.value;
        if (!year) return;
        fetch(`api.php?action=get_prices&year=${year}`)
            .then(r => r.json())
            .then(result => {
                if (result.success && currentLicense) {
                    const type = currentLicense.lizenztyp;
                    if (result.preise[type]) {
                        extendFields.cost.value = Number(result.preise[type]).toFixed(2);
                        updateExtendTotal();
                    }
                }
            });
    });

    document.getElementById('licenseeSelect').addEventListener('change', event => {
        const option = event.target.selectedOptions[0];
        if (!option) return;
        if (!option.value) {
            resetLicenseeFields();
            licenseFields.licenseeId.value = '';
            return;
        }
        licenseFields.licenseeId.value = option.value;
        licenseFields.firstName.value = option.dataset.vorname || '';
        licenseFields.lastName.value = option.dataset.nachname || '';
        licenseFields.street.value = option.dataset.strasse || '';
        licenseFields.zip.value = option.dataset.plz || '';
        licenseFields.city.value = option.dataset.ort || '';
        licenseFields.phone.value = option.dataset.telefon || '';
        licenseFields.email.value = option.dataset.email || '';
        licenseFields.card.value = option.dataset.karte || '';
    });

    licenseFields.zip.addEventListener('blur', event => {
        const value = event.target.value.trim();
        if (!value) return;
        fetch(`api.php?action=lookup_zip&plz=${encodeURIComponent(value)}`)
            .then(r => r.json())
            .then(result => {
                if (result.success && result.ort) {
                    licenseFields.city.value = result.ort;
                }
            });
    });

    licenseForm.addEventListener('submit', event => {
        event.preventDefault();
        if (!licenseForm.checkValidity()) return;
        const payload = buildLicensePayload();
        pendingBlockPayload = payload;
        submitLicensePayload(payload, false);
    });

    document.getElementById('confirmDelete').addEventListener('click', () => {
        if (!currentLicense) return;
        fetch('api.php?action=delete_license', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ year: CURRENT_YEAR, license_id: currentLicense.lizenz_id })
        })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.message || 'Löschen fehlgeschlagen');
                }
            })
            .catch(() => alert('Löschen fehlgeschlagen'));
    });

    document.getElementById('extendForm').addEventListener('submit', event => {
        event.preventDefault();
        const toYear = parseInt(extendFields.year.value, 10);
        if (!toYear) return;
        fetch('api.php?action=move_license', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                from_year: CURRENT_YEAR,
                to_year: toYear,
                license_id: currentLicense.lizenz_id,
                kosten: extendFields.cost.value,
                trinkgeld: extendFields.tip.value || 0,
                zahlungsdatum: extendFields.date.value,
                notizen: extendFields.notes.value
            })
        })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    window.location.href = `?jahr=${toYear}`;
                } else {
                    alert(result.message || 'Verlängerung fehlgeschlagen');
                }
            })
            .catch(() => alert('Verlängerung fehlgeschlagen'));
    });

    createYearForm.addEventListener('submit', event => {
        event.preventDefault();
        const year = parseInt(document.getElementById('newYear').value, 10);
        if (!year) return;
        const prices = {};
        createYearForm.querySelectorAll('.price-input').forEach(input => {
            prices[input.dataset.type] = input.value || 0;
        });
        fetch('api.php?action=create_year', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ year, preise: prices })
        })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    window.location.href = `?jahr=${year}`;
                } else {
                    alert(result.message || 'Jahr konnte nicht erstellt werden');
                }
            })
            .catch(() => alert('Jahr konnte nicht erstellt werden'));
    });

    function openLicenseModal(data, row) {
        currentRow = row || null;
        currentLicense = data || null;
        closeBlockWarning();
        document.getElementById('licenseModalTitle').textContent = data ? 'Lizenz bearbeiten' : 'Neue Lizenz';
        resetLicenseForm();
        if (!data) {
            licenseFields.date.value = getTodayDateString();
        }
        if (data) {
            fillLicenseForm(data);
        }
        licenseModal.hidden = false;
    }

    function openDeleteModal(data, row) {
        currentRow = row;
        currentLicense = data;
        deleteModal.hidden = false;
    }

    function openExtendModal(data, row) {
        currentRow = row;
        currentLicense = data;
        extendFields.year.value = CURRENT_YEAR + 1;
        extendFields.cost.value = data.kosten;
        extendFields.tip.value = 0;
        extendFields.total.value = (parseFloat(extendFields.cost.value || 0) + parseFloat(extendFields.tip.value || 0)).toFixed(2);
        extendFields.date.value = '';
        extendFields.notes.value = '';
        extendModal.hidden = false;
    }

    function resetLicenseForm() {
        licenseForm.reset();
        licenseFields.total.value = '0.00';
        toggleBoat(false);
        document.querySelectorAll('.validation-hint').forEach(h => h.textContent = '');
        document.querySelectorAll('.validation-error').forEach(el => el.classList.remove('validation-error'));
    }

    function resetLicenseeFields() {
        licenseFields.firstName.value = '';
        licenseFields.lastName.value = '';
        licenseFields.street.value = '';
        licenseFields.zip.value = '';
        licenseFields.city.value = '';
        licenseFields.phone.value = '';
        licenseFields.email.value = '';
        licenseFields.card.value = '';
    }

    function fillLicenseForm(data) {
        licenseFields.id.value = data.lizenz_id;
        licenseFields.licenseeId.value = data.id;
        licenseFields.firstName.value = data.vorname || '';
        licenseFields.lastName.value = data.nachname || '';
        licenseFields.street.value = data.strasse || '';
        licenseFields.zip.value = data.plz || '';
        licenseFields.city.value = data.ort || '';
        licenseFields.phone.value = data.telefon || '';
        licenseFields.email.value = data.email || '';
        licenseFields.card.value = data.fischerkartennummer || '';
        licenseFields.type.value = data.lizenztyp;
        licenseFields.cost.value = Number(data.kosten).toFixed(2);
        licenseFields.tip.value = Number(data.trinkgeld).toFixed(2);
        licenseFields.total.value = Number(data.gesamt).toFixed(2);
        licenseFields.date.value = data.zahlungsdatum || '';
        licenseFields.notes.value = data.lizenz_notizen || '';
        licenseFields.boatNumber.value = data.bootnummer || '';
        licenseFields.boatNotes.value = data.boot_notizen || '';
        toggleBoat(data.lizenztyp === 'Boot');
    }

    function closeModals() {
        [licenseModal, deleteModal, extendModal, createYearModal].forEach(modal => {
            if (modal) {
                modal.hidden = true;
            }
        });
        closeBlockWarning();
    }

    function updateTotal() {
        const cost = parseFloat(licenseFields.cost.value || '0');
        const tip = parseFloat(licenseFields.tip.value || '0');
        licenseFields.total.value = (cost + tip).toFixed(2);
    }

    function updateExtendTotal() {
        const cost = parseFloat(extendFields.cost.value || '0');
        const tip = parseFloat(extendFields.tip.value || '0');
        extendFields.total.value = (cost + tip).toFixed(2);
    }

    function toggleBoat(show) {
        if (show) {
            licenseFields.boatDetails.setAttribute('open', '');
        } else {
            licenseFields.boatDetails.removeAttribute('open');
        }
    }

    function getTodayDateString() {
        const today = new Date();
        today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
        return today.toISOString().split('T')[0];
    }

    function submitLicensePayload(payload, force) {
        const requestBody = { ...payload, force };
        fetch('api.php?action=save_license', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestBody)
        })
            .then(r => r.json())
            .then(result => handleLicenseSaveResult(result, payload, force))
            .catch(() => {
                pendingBlockPayload = null;
                alert('Speichern fehlgeschlagen');
            });
    }

    function handleLicenseSaveResult(result, payload, force) {
        if (result.success) {
            pendingBlockPayload = null;
            window.location.reload();
            return;
        }

        if (result.blocked && !force) {
            pendingBlockPayload = payload;
            showBlockWarning(result.entry, payload);
            return;
        }

        pendingBlockPayload = null;
        alert(result.message || 'Speichern fehlgeschlagen');
    }

    function showBlockWarning(entry, payload) {
        const licensee = payload && payload.licensee ? payload.licensee : {};
        const firstName = licensee.vorname || '';
        const lastName = licensee.nachname || '';
        const displayName = [lastName, firstName].filter(Boolean).join(', ');
        let message = 'Der ausgewählte Lizenznehmer steht auf der Sperrliste.';
        if (displayName) {
            message = `Der Lizenznehmer ${displayName} steht auf der Sperrliste.`;
        }
        if (entry && entry.lizenznummer) {
            message += ` Lizenznummer: ${entry.lizenznummer}.`;
        }
        message += ' Möchtest du trotzdem fortfahren?';

        if (!blockWarningModal || !blockWarningText || !confirmBlockOverride) {
            if (window.confirm(message)) {
                submitLicensePayload(payload, true);
            } else {
                pendingBlockPayload = null;
            }
            return;
        }

        blockWarningText.textContent = message;
        blockWarningModal.hidden = false;
    }

    function closeBlockWarning() {
        if (blockWarningModal) {
            blockWarningModal.hidden = true;
        }
        pendingBlockPayload = null;
    }

    function buildLicensePayload() {
        return {
            year: CURRENT_YEAR,
            license: {
                id: licenseFields.id.value || null,
                lizenztyp: licenseFields.type.value,
                kosten: licenseFields.cost.value,
                trinkgeld: licenseFields.tip.value || 0,
                zahlungsdatum: licenseFields.date.value,
                notizen: licenseFields.notes.value
            },
            licensee: {
                id: licenseFields.licenseeId.value || null,
                vorname: licenseFields.firstName.value,
                nachname: licenseFields.lastName.value,
                strasse: licenseFields.street.value,
                plz: licenseFields.zip.value,
                ort: licenseFields.city.value,
                telefon: licenseFields.phone.value,
                email: licenseFields.email.value,
                fischerkartennummer: licenseFields.card.value
            },
            boat: {
                bootnummer: licenseFields.boatNumber.value,
                notizen: licenseFields.boatNotes.value
            }
        };
    }
})();
