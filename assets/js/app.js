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

    const AGE_RULES = {
        Kinder: { min: 10, max: 14 },
        Jugend: { min: 14, max: 18 },
    };

    const LICENSE_TYPE_LABELS = {
        Kinder: 'Kinderlizenz',
        Jugend: 'Jugendlizenz',
    };

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
        birthdate: document.getElementById('geburtsdatum'),
        ageHint: document.getElementById('licenseAgeHint'),
        ageWarning: document.getElementById('licenseAgeWarning'),
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
        license: document.getElementById('extendLicense'),
        type: document.getElementById('extendType'),
        cost: document.getElementById('extendCost'),
        tip: document.getElementById('extendTip'),
        total: document.getElementById('extendTotal'),
        date: document.getElementById('extendDate'),
        notes: document.getElementById('extendNotes'),
    };

    const extendSubmitButton = extendModal ? extendModal.querySelector('button[type="submit"]') : null;

    const createYearForm = document.getElementById('createYearForm');
    const createYearButton = document.getElementById('openCreateYear');
    const createYearPriceInputs = createYearForm ? Array.from(createYearForm.querySelectorAll('.price-input')) : [];
    const createYearInput = createYearForm ? document.getElementById('newYear') : null;

    const licenseDataMap = new Map();
    const licenseRowMap = new Map();

    let currentRow = null;
    let currentLicense = null;
    let pendingBlockPayload = null;

    const YEAR_LOCKED = typeof IS_YEAR_CLOSED !== 'undefined' && !!IS_YEAR_CLOSED;

    if (!licenseModal || !licenseForm) {
        return;
    }

    Validation.attach(licenseForm);

    const extendForm = document.getElementById('extendForm');
    if (extendForm) {
        Validation.attach(extendForm);
    }
    if (createYearForm) {
        Validation.attach(createYearForm);
    }

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

    const addLicenseButton = document.getElementById('openAddLicense');
    if (addLicenseButton) {
        addLicenseButton.addEventListener('click', () => {
            if (YEAR_LOCKED) {
                return;
            }
            openLicenseModal(null);
        });
        if (YEAR_LOCKED) {
            addLicenseButton.disabled = true;
        }
    }

    if (createYearButton) {
        createYearButton.addEventListener('click', () => {
            prefillCreateYearForm();
            createYearModal.hidden = false;
        });
    }

    document.querySelectorAll('tbody tr[data-license]').forEach(row => {
        const data = JSON.parse(row.dataset.license);
        const licenseId = parseInt(data.lizenz_id, 10);
        if (!Number.isNaN(licenseId)) {
            licenseDataMap.set(licenseId, data);
            licenseRowMap.set(licenseId, row);
        }
        const editButton = row.querySelector('.edit');
        const deleteButton = row.querySelector('.delete');
        const extendButton = row.querySelector('.extend');

        if (editButton) {
            editButton.addEventListener('click', () => {
                if (YEAR_LOCKED) return;
                openLicenseModal(data, row);
            });
        }
        if (deleteButton) {
            deleteButton.addEventListener('click', () => {
                if (YEAR_LOCKED) return;
                openDeleteModal(data, row);
            });
        }
        if (extendButton) {
            extendButton.addEventListener('click', () => {
                if (YEAR_LOCKED) return;
                openExtendModal(data, row);
            });
        }
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

    if (licenseFields.tip) {
        licenseFields.tip.addEventListener('input', updateTotal);
    }
    if (licenseFields.cost) {
        licenseFields.cost.addEventListener('input', updateTotal);
    }
    if (extendFields.tip) {
        extendFields.tip.addEventListener('input', updateExtendTotal);
    }
    if (extendFields.cost) {
        extendFields.cost.addEventListener('input', updateExtendTotal);
    }

    if (licenseFields.type) {
        licenseFields.type.addEventListener('change', event => {
            const type = event.target.value;
            if (LICENSE_PRICES[type]) {
                licenseFields.cost.value = Number(LICENSE_PRICES[type]).toFixed(2);
            }
            toggleBoat(type === 'Boot');
            updateTotal();
            updateLicenseAgeState();
        });
    }

    if (licenseFields.birthdate) {
        ['input', 'change'].forEach(eventName => {
            licenseFields.birthdate.addEventListener(eventName, () => {
                updateLicenseAgeState();
            });
        });
    }

    if (extendFields.year) {
        extendFields.year.addEventListener('change', () => {
            updateExtendPricing();
            updateExtendSubmitButtonState();
        });
    }

    if (extendFields.type) {
        extendFields.type.addEventListener('change', () => {
            updateExtendPricing();
        });
    }

    if (extendFields.license) {
        extendFields.license.addEventListener('change', () => {
            const selectedId = parseInt(extendFields.license.value, 10);
            const selectedLicense = Number.isNaN(selectedId) ? null : licenseDataMap.get(selectedId);
            currentLicense = selectedLicense || null;
            if (selectedLicense) {
                applyExtendLicenseDefaults(selectedLicense);
                if (extendFields.year && extendFields.year.value) {
                    updateExtendPricing();
                } else {
                    updateExtendTotal();
                }
                if (selectedLicense && licenseRowMap.has(selectedId)) {
                    currentRow = licenseRowMap.get(selectedId);
                }
            }
            updateExtendSubmitButtonState();
        });
    }

    if (licenseFields.zip) {
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
    }

    licenseForm.addEventListener('submit', event => {
        event.preventDefault();
        if (YEAR_LOCKED) return;
        if (!licenseForm.checkValidity()) return;
        const payload = buildLicensePayload();
        pendingBlockPayload = payload;
        submitLicensePayload(payload, false);
    });

    const confirmDeleteButton = document.getElementById('confirmDelete');
    if (confirmDeleteButton) {
        confirmDeleteButton.addEventListener('click', () => {
            if (YEAR_LOCKED) return;
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
    }

    if (extendForm) {
        extendForm.addEventListener('submit', event => {
            event.preventDefault();
            if (YEAR_LOCKED) return;
            const toYear = parseInt(extendFields.year.value, 10);
            const selectedLicenseId = extendFields.license ? parseInt(extendFields.license.value, 10) : (currentLicense ? parseInt(currentLicense.lizenz_id, 10) : NaN);
            if (!toYear || Number.isNaN(selectedLicenseId) || selectedLicenseId <= 0) return;
            const selectedLicense = licenseDataMap.get(selectedLicenseId) || currentLicense || {};
            currentLicense = selectedLicense || null;
            fetch('api.php?action=move_license', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    from_year: CURRENT_YEAR,
                    to_year: toYear,
                    license_id: selectedLicenseId,
                    lizenztyp: extendFields.type ? extendFields.type.value || selectedLicense.lizenztyp : selectedLicense.lizenztyp,
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
    }

    if (createYearForm) {
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
    }

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
        updateLicenseAgeState();
        licenseModal.hidden = false;
    }

    function openDeleteModal(data, row) {
        currentRow = row;
        currentLicense = data;
        if (deleteModal) {
            deleteModal.hidden = false;
        }
    }

    function openExtendModal(data, row) {
        currentRow = row;
        currentLicense = data;
        const yearField = extendFields.year;
        const optionElements = yearField ? Array.from(yearField.options).filter(option => option.value) : [];
        let defaultYear = '';
        if (optionElements.length) {
            const targetYear = CURRENT_YEAR + 1;
            const exactMatch = optionElements.find(option => parseInt(option.value, 10) === targetYear);
            if (exactMatch) {
                defaultYear = exactMatch.value;
            } else {
                const futureYear = optionElements.find(option => parseInt(option.value, 10) > CURRENT_YEAR);
                if (futureYear) {
                    defaultYear = futureYear.value;
                } else {
                    defaultYear = optionElements[0].value;
                }
            }
        }
        if (yearField) {
            yearField.disabled = optionElements.length === 0;
            yearField.value = defaultYear;
        }
        if (extendFields.license) {
            populateExtendLicenseOptions(parseInt(data.lizenz_id, 10));
        }
        applyExtendLicenseDefaults(data);
        if (extendFields.date) {
            extendFields.date.value = getTodayDateString();
        }
        if (extendFields.notes) {
            extendFields.notes.value = '';
        }
        if (extendModal) {
            extendModal.hidden = false;
        }
        if (defaultYear && yearField) {
            updateExtendPricing();
        } else {
            updateExtendTotal();
        }
        if (!optionElements.length) {
            alert('Kein Zieljahr vorhanden. Bitte neues Jahr im Adminbereich anlegen.');
        }
        updateExtendSubmitButtonState();
    }

    function populateExtendLicenseOptions(selectedLicenseId) {
        if (!extendFields.license) {
            return;
        }

        const select = extendFields.license;
        const previousValue = select.value;
        select.innerHTML = '';

        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = '– bitte wählen –';
        select.appendChild(placeholderOption);

        const licenses = Array.from(licenseDataMap.values()).sort((a, b) => {
            const lastNameCompare = (a.nachname || '').localeCompare(b.nachname || '', 'de', { sensitivity: 'base' });
            if (lastNameCompare !== 0) {
                return lastNameCompare;
            }
            const firstNameCompare = (a.vorname || '').localeCompare(b.vorname || '', 'de', { sensitivity: 'base' });
            if (firstNameCompare !== 0) {
                return firstNameCompare;
            }
            return String(a.lizenztyp || '').localeCompare(String(b.lizenztyp || ''), 'de', { sensitivity: 'base' });
        });

        licenses.forEach(license => {
            const option = document.createElement('option');
            option.value = String(license.lizenz_id);
            const parts = [];
            if (license.nachname) {
                parts.push(license.nachname);
            }
            if (license.vorname) {
                parts.push(license.vorname);
            }
            const name = parts.length ? parts.join(', ') : `#${license.lizenz_id}`;
            option.textContent = `${name} – ${license.lizenztyp}`;
            select.appendChild(option);
        });

        let targetValue = '';
        if (selectedLicenseId && licenses.some(license => parseInt(license.lizenz_id, 10) === selectedLicenseId)) {
            targetValue = String(selectedLicenseId);
        } else if (previousValue && licenses.some(license => String(license.lizenz_id) === previousValue)) {
            targetValue = previousValue;
        } else if (licenses.length === 1) {
            targetValue = String(licenses[0].lizenz_id);
        }

        if (targetValue) {
            select.value = targetValue;
        }

        select.disabled = licenses.length === 0;
    }

    function applyExtendLicenseDefaults(licenseData) {
        if (!licenseData) {
            return;
        }

        currentLicense = licenseData;
        if (extendFields.license) {
            extendFields.license.value = String(licenseData.lizenz_id);
        }
        if (extendFields.type) {
            const typeValue = licenseData.lizenztyp || '';
            const hasTypeOption = Array.from(extendFields.type.options || []).some(option => option.value === typeValue);
            extendFields.type.value = hasTypeOption ? typeValue : '';
        }
        if (extendFields.cost) {
            const baseCost = parseFloat(licenseData.kosten);
            extendFields.cost.value = Number.isFinite(baseCost) ? baseCost.toFixed(2) : Number(0).toFixed(2);
        }
        if (extendFields.tip) {
            extendFields.tip.value = Number(0).toFixed(2);
        }
        updateExtendTotal();
    }

    function updateExtendSubmitButtonState() {
        if (!extendSubmitButton) {
            return;
        }

        const yearValid = !extendFields.year || (!extendFields.year.disabled && extendFields.year.value);
        const licenseValid = !extendFields.license || (!extendFields.license.disabled && extendFields.license.value);

        extendSubmitButton.disabled = !(yearValid && licenseValid);
    }

    function resetLicenseForm() {
        licenseForm.reset();
        licenseFields.total.value = '0.00';
        toggleBoat(false);
        document.querySelectorAll('.validation-hint').forEach(h => h.textContent = '');
        document.querySelectorAll('.validation-error').forEach(el => el.classList.remove('validation-error'));
        if (licenseFields.ageHint) {
            licenseFields.ageHint.textContent = '';
        }
        if (licenseFields.ageWarning) {
            licenseFields.ageWarning.textContent = '';
            licenseFields.ageWarning.hidden = true;
        }
        updateLicenseAgeState();
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
        if (licenseFields.birthdate) {
            const birthdateValue = data.geburtsdatum && data.geburtsdatum !== '0000-00-00' ? data.geburtsdatum : '';
            licenseFields.birthdate.value = birthdateValue;
        }
        licenseFields.type.value = data.lizenztyp;
        licenseFields.cost.value = Number(data.kosten).toFixed(2);
        licenseFields.tip.value = Number(data.trinkgeld).toFixed(2);
        licenseFields.total.value = Number(data.gesamt).toFixed(2);
        licenseFields.date.value = data.zahlungsdatum || '';
        licenseFields.notes.value = data.lizenz_notizen || '';
        licenseFields.boatNumber.value = data.bootnummer || '';
        licenseFields.boatNotes.value = data.boot_notizen || '';
        toggleBoat(data.lizenztyp === 'Boot');
        updateLicenseAgeState();
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

    function updateExtendPricing() {
        if (!extendFields.year || !extendFields.type) {
            return;
        }
        const year = extendFields.year.value;
        const type = extendFields.type.value;
        if (!year || !type) {
            return;
        }
        fetch(`api.php?action=get_prices&year=${year}`)
            .then(r => r.json())
            .then(result => {
                if (!result.success) {
                    return;
                }
                const price = result.preise && result.preise[type];
                if (typeof price === 'number') {
                    extendFields.cost.value = Number(price).toFixed(2);
                } else {
                    extendFields.cost.value = Number(0).toFixed(2);
                }
                updateExtendTotal();
            });
    }

    function updateExtendTotal() {
        const cost = parseFloat(extendFields.cost.value || '0');
        const tip = parseFloat(extendFields.tip.value || '0');
        extendFields.total.value = (cost + tip).toFixed(2);
    }

    function toggleBoat(show) {
        if (!licenseFields.boatDetails) {
            return;
        }
        if (show) {
            licenseFields.boatDetails.hidden = false;
            licenseFields.boatDetails.setAttribute('open', '');
        } else {
            licenseFields.boatDetails.hidden = true;
            licenseFields.boatDetails.removeAttribute('open');
        }
    }

    function prefillCreateYearForm() {
        if (!createYearForm) return;
        createYearForm.reset();
        if (createYearInput) {
            const nextYear = new Date().getFullYear() + 1;
            createYearInput.value = nextYear;
        }

        if (!Array.isArray(createYearPriceInputs) || createYearPriceInputs.length === 0) {
            return;
        }
        createYearPriceInputs.forEach(input => {
            const type = input.dataset.type;
            if (type && LICENSE_PRICES && Object.prototype.hasOwnProperty.call(LICENSE_PRICES, type)) {
                const value = Number(LICENSE_PRICES[type]);
                if (!Number.isNaN(value)) {
                    input.value = value.toFixed(2);
                    return;
                }
            }
            input.value = '';
        });
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

    function updateLicenseAgeState() {
        if (!licenseFields.birthdate) {
            return;
        }

        const birthdate = licenseFields.birthdate.value;
        const age = calculateAgeFromBirthdate(birthdate);

        if (licenseFields.ageHint) {
            licenseFields.ageHint.textContent = birthdate && age !== null ? `Alter: ${age} Jahre` : '';
        }

        if (licenseFields.ageWarning) {
            const type = licenseFields.type ? licenseFields.type.value : '';
            const warning = age !== null ? getAgeWarningMessage(age, type) : null;
            if (warning) {
                licenseFields.ageWarning.textContent = warning;
                licenseFields.ageWarning.hidden = false;
            } else {
                licenseFields.ageWarning.textContent = '';
                licenseFields.ageWarning.hidden = true;
            }
        }
    }

    function getAgeWarningMessage(age, type) {
        if (!type || !Object.prototype.hasOwnProperty.call(AGE_RULES, type)) {
            return null;
        }

        const rule = AGE_RULES[type];
        if (!rule) {
            return null;
        }

        if (age >= rule.min && age <= rule.max) {
            return null;
        }

        const label = LICENSE_TYPE_LABELS[type] || type;
        const range = `${rule.min}–${rule.max} Jahre`;
        return `Achtung: Alter ${age} passt nicht zur ${label} (${range}).`;
    }

    function calculateAgeFromBirthdate(dateString) {
        if (!dateString) {
            return null;
        }

        const match = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(dateString);
        if (!match) {
            return null;
        }

        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
            return null;
        }

        const today = new Date();
        let age = today.getFullYear() - year;
        const currentMonth = today.getMonth() + 1;
        const currentDay = today.getDate();
        if (currentMonth < month || (currentMonth === month && currentDay < day)) {
            age -= 1;
        }

        return age >= 0 ? age : null;
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
                fischerkartennummer: licenseFields.card.value,
                geburtsdatum: licenseFields.birthdate ? licenseFields.birthdate.value : ''
            },
            boat: {
                bootnummer: licenseFields.boatNumber.value,
                notizen: licenseFields.boatNotes.value
            }
        };
    }
})();
