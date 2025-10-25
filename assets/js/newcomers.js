(function () {
    const assignModal = document.getElementById('assignModal');
    const assignForm = document.getElementById('assignForm');
    const addModal = document.getElementById('newApplicantModal');
    const addForm = document.getElementById('newApplicantForm');
    const addButton = document.getElementById('openAddApplicant');
    const countDisplay = document.getElementById('newcomerCount');
    const table = document.getElementById('newcomerTable');
    const tableBody = table ? table.tBodies[0] : null;
    const addModalTitle = addModal ? addModal.querySelector('h2') : null;
    const addSubmitButton = addForm ? addForm.querySelector('button[type="submit"]') : null;
    const blockWarningModal = document.getElementById('assignBlockWarningModal');
    const blockWarningText = document.getElementById('assignBlockWarningText');
    const confirmBlockOverride = document.getElementById('confirmAssignBlockOverride');
    const cancelBlockWarning = document.getElementById('cancelAssignBlockWarning');
    const closeBlockWarningButton = document.getElementById('closeAssignBlockWarning');

    const AGE_RULES = {
        Kinder: { min: 10, max: 14 },
        Jugend: { min: 14, max: 18 },
    };

    const LICENSE_TYPE_LABELS = {
        Kinder: 'Kinderlizenz',
        Jugend: 'Jugendlizenz',
    };

    if (!assignModal || !assignForm) {
        return;
    }

    const assignFields = {
        year: document.getElementById('assignYear'),
        type: document.getElementById('assignType'),
        cost: document.getElementById('assignCost'),
        tip: document.getElementById('assignTip'),
        total: document.getElementById('assignTotal'),
        date: document.getElementById('assignDate'),
        notes: document.getElementById('assignNotes'),
        ageHint: document.getElementById('assignAgeHint'),
        ageWarning: document.getElementById('assignAgeWarning'),
    };
    const DEFAULT_ASSIGN_YEAR = typeof LATEST_YEAR === 'number' ? String(LATEST_YEAR) : '';

    const addFields = addForm ? {
        firstName: document.getElementById('applicantFirstName'),
        lastName: document.getElementById('applicantLastName'),
        street: document.getElementById('applicantStreet'),
        zip: document.getElementById('applicantZip'),
        city: document.getElementById('applicantCity'),
        phone: document.getElementById('applicantPhone'),
        email: document.getElementById('applicantEmail'),
        birthdate: document.getElementById('applicantBirthdate'),
        ageHint: document.getElementById('applicantAgeHint'),
        card: document.getElementById('applicantCard'),
        date: document.getElementById('applicantDate'),
        notes: document.getElementById('applicantNotes'),
    } : null;

    let currentApplicant = null;
    let currentRow = null;
    let editingApplicantId = null;
    let editingRow = null;
    let pendingAssignmentPayload = null;
    let pendingApplicantPayload = null;
    let pendingApplicantAction = null;
    let pendingBlockContext = null;
    const priceCache = {};

    if (typeof LICENSE_PRICES === 'object') {
        priceCache[CURRENT_YEAR] = LICENSE_PRICES;
    }

    Validation.attach(assignForm);
    if (addForm) {
        Validation.attach(addForm);
    }

    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal');
            hideModal(modal);
        });
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
            if (pendingBlockContext === 'assign' && pendingAssignmentPayload) {
                const payload = pendingAssignmentPayload;
                pendingBlockContext = null;
                hideBlockWarning();
                submitAssignment(payload, true);
                return;
            }

            if (pendingBlockContext === 'applicant' && pendingApplicantPayload && pendingApplicantAction) {
                const action = pendingApplicantAction;
                const payload = pendingApplicantPayload;
                pendingBlockContext = null;
                hideBlockWarning();
                submitApplicant(action, payload, true);
                return;
            }

            closeBlockWarning();
        });
    }

    if (addButton && addForm && addModal && addFields) {
        addButton.addEventListener('click', () => openAddModal());
        addFields.zip.addEventListener('blur', event => {
            const value = event.target.value.trim();
            if (!value) return;
            fetch(`api.php?action=lookup_zip&plz=${encodeURIComponent(value)}`)
                .then(r => r.json())
                .then(result => {
                    if (result.success && result.ort) {
                        addFields.city.value = result.ort;
                    }
                });
        });
        if (addFields.birthdate) {
            ['input', 'change'].forEach(eventName => {
                addFields.birthdate.addEventListener(eventName, () => {
                    updateApplicantAgeHint();
                });
            });
        }
        addForm.addEventListener('submit', event => {
            event.preventDefault();
            if (!Validation.validateInput(addFields.firstName) || !Validation.validateInput(addFields.lastName)) {
                return;
            }

            const payload = {
                id: editingApplicantId,
                vorname: addFields.firstName.value,
                nachname: addFields.lastName.value,
                strasse: addFields.street.value,
                plz: addFields.zip.value,
                ort: addFields.city.value,
                telefon: addFields.phone.value,
                email: addFields.email.value,
                geburtsdatum: addFields.birthdate ? addFields.birthdate.value : '',
                fischerkartennummer: addFields.card.value,
                bewerbungsdatum: addFields.date.value,
                notizen: addFields.notes.value,
            };

            const action = editingApplicantId ? 'update_newcomer' : 'create_newcomer';

            submitApplicant(action, payload, false);
        });
    }

    const applicantRows = tableBody ? tableBody.querySelectorAll('tr[data-applicant]') : [];
    applicantRows.forEach(row => {
        populateActionCell(row.querySelector('td.actions'), row);
    });

    if (assignFields.tip) {
        assignFields.tip.addEventListener('input', updateTotal);
    }
    if (assignFields.cost) {
        assignFields.cost.addEventListener('input', updateTotal);
    }

    if (assignFields.type) {
        assignFields.type.addEventListener('change', () => {
            const year = assignFields.year ? assignFields.year.value : '';
            const type = assignFields.type.value;
            const prices = priceCache[year];
            if (prices && prices[type]) {
                assignFields.cost.value = Number(prices[type]).toFixed(2);
            }
            updateTotal();
            updateAssignAgeInfo();
        });
    }

    if (assignFields.year) {
        assignFields.year.addEventListener('change', event => {
            const year = event.target.value;
            if (!year) return;
            if (priceCache[year]) {
                updatePriceSuggestion();
                updateAssignAgeInfo();
                return;
            }
            fetch(`api.php?action=get_prices&year=${year}`)
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        priceCache[year] = result.preise || {};
                        updatePriceSuggestion();
                        updateAssignAgeInfo();
                    }
                });
        });
    }

    assignForm.addEventListener('submit', event => {
        event.preventDefault();
        if (!assignFields.year || assignFields.year.disabled || !assignFields.year.value) {
            alert('Kein Jahr vorhanden. Bitte neues Jahr im Adminbereich anlegen.');
            return;
        }
        if (!Validation.validateInput(assignFields.year) || !Validation.validateInput(assignFields.type) || !Validation.validateInput(assignFields.cost)) {
            return;
        }
        const payload = {
            action: 'assign_newcomer',
            applicant_id: currentApplicant?.id,
            year: assignFields.year.value,
            license_type: assignFields.type.value,
            kosten: assignFields.cost.value,
            trinkgeld: assignFields.tip.value || 0,
            zahlungsdatum: assignFields.date.value,
            notizen: assignFields.notes.value,
        };

        submitAssignment(payload, false);
    });

    function submitAssignment(payload, force) {
        const requestBody = { ...payload, force };
        fetch('api.php?action=assign_newcomer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestBody)
        })
            .then(r => r.json())
            .then(result => handleAssignmentResult(result, payload, force))
            .catch(() => {
                pendingAssignmentPayload = null;
                pendingBlockContext = null;
                alert('Lizenz konnte nicht zugewiesen werden.');
            });
    }

    function handleAssignmentResult(result, payload, force) {
        if (result.success) {
            pendingAssignmentPayload = null;
            pendingBlockContext = null;
            if (currentRow) {
                currentRow.remove();
                adjustNewcomerCount(-1);
                checkEmptyTable();
                refreshTableSearch();
            }
            hideModal(assignModal);
            return;
        }

        if (result.blocked && !force) {
            pendingAssignmentPayload = payload;
            pendingBlockContext = 'assign';
            showBlockWarning(result.entry, { type: 'assign', payload });
            return;
        }

        pendingAssignmentPayload = null;
        pendingBlockContext = null;
        alert(result.message || 'Lizenz konnte nicht zugewiesen werden.');
    }

    function showBlockWarning(entry, options = {}) {
        const context = options.type || 'assign';
        pendingBlockContext = context;

        let firstName = '';
        let lastName = '';

        if (context === 'assign') {
            firstName = currentApplicant?.vorname || '';
            lastName = currentApplicant?.nachname || '';
        } else {
            const payload = options.payload || pendingApplicantPayload || {};
            firstName = payload?.vorname || '';
            lastName = payload?.nachname || '';
        }

        const displayName = [lastName, firstName].filter(Boolean).join(', ');
        let message = context === 'assign'
            ? 'Der ausgewählte Bewerber steht auf der Sperrliste.'
            : 'Der Bewerber steht auf der Sperrliste.';

        if (displayName) {
            message = `Der Bewerber ${displayName} steht auf der Sperrliste.`;
        }

        if (entry && entry.lizenznummer) {
            message += ` Lizenznummer: ${entry.lizenznummer}.`;
        }

        message += context === 'assign'
            ? ' Möchtest du trotzdem fortfahren?'
            : ' Möchtest du den Bewerber trotzdem speichern?';

        const confirmLabel = context === 'assign' ? 'Trotzdem speichern' : 'Trotzdem speichern';

        if (!blockWarningModal || !blockWarningText || !confirmBlockOverride) {
            if (window.confirm(message)) {
                if (context === 'assign' && pendingAssignmentPayload) {
                    pendingBlockContext = null;
                    submitAssignment(pendingAssignmentPayload, true);
                } else if (context === 'applicant' && pendingApplicantPayload && pendingApplicantAction) {
                    pendingBlockContext = null;
                    submitApplicant(pendingApplicantAction, pendingApplicantPayload, true);
                }
            } else {
                clearPendingBlockState();
            }
            return;
        }

        blockWarningText.textContent = message;
        confirmBlockOverride.textContent = confirmLabel;
        showModal(blockWarningModal);
    }

    function hideBlockWarning() {
        hideElement(blockWarningModal);
    }

    function closeBlockWarning() {
        hideBlockWarning();
        clearPendingBlockState();
    }

    function openAssignModal(applicant, row) {
        currentApplicant = applicant;
        currentRow = row;
        assignForm.reset();
        clearValidation(assignForm);

        if (assignFields.year) {
            if (DEFAULT_ASSIGN_YEAR) {
                assignFields.year.value = DEFAULT_ASSIGN_YEAR;
            } else {
                const defaultOption = Array.from(assignFields.year.options || []).find(option => option.defaultSelected);
                assignFields.year.value = defaultOption ? defaultOption.value : assignFields.year.value;
            }
        }
        if (assignFields.type) {
            assignFields.type.value = 'Angel';
        }
        if (assignFields.cost) {
            assignFields.cost.value = '';
        }
        if (assignFields.tip) {
            assignFields.tip.value = '0.00';
        }
        if (assignFields.total) {
            assignFields.total.value = '0.00';
        }
        if (assignFields.date) {
            assignFields.date.value = getTodayDateString();
        }
        if (assignFields.notes) {
            assignFields.notes.value = applicant.notizen || '';
        }

        updateAssignAgeInfo();
        showModal(assignModal);
        if (assignFields.type) {
            assignFields.type.dispatchEvent(new Event('change'));
        }
        if (assignFields.year && assignFields.year.value) {
            assignFields.year.dispatchEvent(new Event('change'));
        }
    }

    function submitApplicant(action, payload, force) {
        const requestBody = { ...payload, force };
        fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestBody)
        })
            .then(r => r.json())
            .then(result => handleApplicantResult(action, result, payload, force))
            .catch(() => {
                pendingApplicantPayload = null;
                pendingApplicantAction = null;
                pendingBlockContext = null;
                alert('Neuwerber konnte nicht gespeichert werden.');
            });
    }

    function handleApplicantResult(action, result, payload, force) {
        if (result.success && result.bewerber) {
            pendingApplicantPayload = null;
            pendingApplicantAction = null;
            pendingBlockContext = null;

            if (action === 'update_newcomer' && editingApplicantId && editingRow) {
                updateApplicantRow(editingRow, result.bewerber);
            } else {
                addApplicantRow(result.bewerber);
                adjustNewcomerCount(1);
            }
            hideModal(addModal);
            return;
        }

        if (result.blocked && !force) {
            pendingApplicantPayload = payload;
            pendingApplicantAction = action;
            pendingBlockContext = 'applicant';
            showBlockWarning(result.entry, { type: 'applicant', payload });
            return;
        }

        pendingApplicantPayload = null;
        pendingApplicantAction = null;
        pendingBlockContext = null;
        alert(result.message || 'Neuwerber konnte nicht gespeichert werden.');
    }

    function openAddModal() {
        if (!addModal || !addForm || !addFields) return;
        addForm.reset();
        clearValidation(addForm);
        if (addFields.birthdate) {
            addFields.birthdate.value = '';
        }
        addFields.date.value = getTodayDateString();
        updateApplicantAgeHint();
        showModal(addModal);
        addFields.firstName.focus();
        editingApplicantId = null;
        editingRow = null;
        if (addModalTitle) {
            addModalTitle.textContent = 'Neuwerber hinzufügen';
        }
        if (addSubmitButton) {
            addSubmitButton.textContent = 'Speichern';
        }
    }

    function hideModal(modal) {
        if (!modal) return;
        hideElement(modal);
        if (modal === assignModal) {
            currentApplicant = null;
            currentRow = null;
            if (assignFields.ageHint) {
                assignFields.ageHint.textContent = '';
            }
            if (assignFields.ageWarning) {
                assignFields.ageWarning.textContent = '';
                assignFields.ageWarning.hidden = true;
            }
        }
        if (modal === addModal && addForm) {
            addForm.reset();
            clearValidation(addForm);
            editingApplicantId = null;
            editingRow = null;
            updateApplicantAgeHint();
        }
    }

    function clearPendingBlockState() {
        pendingAssignmentPayload = null;
        pendingApplicantPayload = null;
        pendingApplicantAction = null;
        pendingBlockContext = null;
    }

    function openEditModal(applicant, row) {
        if (!addModal || !addForm || !addFields) return;
        editingApplicantId = applicant?.id || null;
        editingRow = row || null;

        addForm.reset();
        clearValidation(addForm);

        addFields.firstName.value = applicant?.vorname || '';
        addFields.lastName.value = applicant?.nachname || '';
        addFields.street.value = applicant?.strasse || '';
        addFields.zip.value = applicant?.plz || '';
        addFields.city.value = applicant?.ort || '';
        addFields.phone.value = applicant?.telefon || '';
        addFields.email.value = applicant?.email || '';
        if (addFields.birthdate) {
            const birthdateValue = applicant?.geburtsdatum && applicant.geburtsdatum !== '0000-00-00' ? applicant.geburtsdatum : '';
            addFields.birthdate.value = birthdateValue;
        }
        addFields.card.value = applicant?.fischerkartennummer || '';
        const dateValue = applicant?.bewerbungsdatum;
        addFields.date.value = dateValue && dateValue !== '0000-00-00' ? dateValue : '';
        addFields.notes.value = applicant?.notizen || '';
        updateApplicantAgeHint();

        if (addModalTitle) {
            addModalTitle.textContent = 'Neuwerber bearbeiten';
        }
        if (addSubmitButton) {
            addSubmitButton.textContent = 'Aktualisieren';
        }

        showModal(addModal);
        addFields.firstName.focus();
    }

    function showModal(modal) {
        if (!modal) return;
        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
    }

    function hideElement(modal) {
        if (!modal) return;
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
    }

    function updateTotal() {
        const cost = parseFloat(assignFields.cost.value || '0');
        const tip = parseFloat(assignFields.tip.value || '0');
        assignFields.total.value = (cost + tip).toFixed(2);
    }

    function updatePriceSuggestion() {
        const year = assignFields.year.value;
        const type = assignFields.type.value;
        const prices = priceCache[year];
        if (prices && type && prices[type]) {
            assignFields.cost.value = Number(prices[type]).toFixed(2);
            updateTotal();
        }
    }

    function getTodayDateString() {
        const today = new Date();
        today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
        return today.toISOString().split('T')[0];
    }

    function checkEmptyTable() {
        if (!tableBody) return;
        const hasEntries = Array.from(tableBody.rows).some(row => !row.hasAttribute('data-empty-row') && !row.hasAttribute('data-no-results'));
        if (hasEntries) {
            return;
        }
        let createdPlaceholder = false;
        if (!tableBody.querySelector('[data-empty-row]')) {
            const row = document.createElement('tr');
            row.setAttribute('data-empty-row', 'true');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.className = 'empty';
            cell.textContent = 'Keine Neuwerber vorhanden.';
            row.appendChild(cell);
            tableBody.appendChild(row);
            createdPlaceholder = true;
        }
        if (createdPlaceholder) {
            refreshTableSearch();
        }
    }

    function addApplicantRow(applicant) {
        if (!tableBody) return;

        const placeholderRow = tableBody.querySelector('[data-empty-row]');
        if (placeholderRow) {
            placeholderRow.remove();
        }

        const row = document.createElement('tr');
        row.dataset.applicant = JSON.stringify(applicant);

        const nameCell = document.createElement('td');
        nameCell.innerHTML = `<strong>${escapeHtml(applicant.nachname || '')}, ${escapeHtml(applicant.vorname || '')}</strong>`;

        const contactCell = document.createElement('td');
        contactCell.innerHTML = renderApplicantContact(applicant);

        const dateCell = document.createElement('td');
        dateCell.textContent = formatDateDisplay(applicant.bewerbungsdatum);

        const notesCell = document.createElement('td');
        notesCell.innerHTML = escapeHtml(applicant.notizen || '').replace(/\n/g, '<br>');

        const actionCell = document.createElement('td');
        row.appendChild(nameCell);
        row.appendChild(contactCell);
        row.appendChild(dateCell);
        row.appendChild(notesCell);
        row.appendChild(actionCell);

        populateActionCell(actionCell, row);

        tableBody.insertBefore(row, tableBody.firstChild);
        refreshTableSearch();
    }

    function updateApplicantRow(row, applicant) {
        if (!row) return;
        row.dataset.applicant = JSON.stringify(applicant);

        const [nameCell, contactCell, dateCell, notesCell, actionCell] = row.cells;
        if (nameCell) {
            nameCell.innerHTML = `<strong>${escapeHtml(applicant.nachname || '')}, ${escapeHtml(applicant.vorname || '')}</strong>`;
        }
        if (contactCell) {
            contactCell.innerHTML = renderApplicantContact(applicant);
        }
        if (dateCell) {
            dateCell.textContent = formatDateDisplay(applicant.bewerbungsdatum);
        }
        if (notesCell) {
            notesCell.innerHTML = escapeHtml(applicant.notizen || '').replace(/\n/g, '<br>');
        }
        populateActionCell(actionCell, row);
        refreshTableSearch();
    }

    function renderApplicantContact(applicant) {
        const parts = [
            `<small>${escapeHtml(applicant.strasse || '')}</small>`,
            `<small>${escapeHtml(applicant.plz || '')} ${escapeHtml(applicant.ort || '')}</small>`,
            `<small>Telefon: ${escapeHtml(applicant.telefon || '-')} · E-Mail: ${escapeHtml(applicant.email || '-')}</small>`,
            `<small>Fischerkartennummer: ${escapeHtml(applicant.fischerkartennummer || '-')}</small>`,
        ];

        const birthdate = applicant?.geburtsdatum || '';
        if (birthdate && birthdate !== '0000-00-00') {
            const formatted = escapeHtml(formatDateDisplay(birthdate));
            const age = calculateAgeFromBirthdate(birthdate);
            const ageSuffix = age !== null ? ` (Alter: ${age})` : '';
            parts.push(`<small>Geburtsdatum: ${formatted}${ageSuffix}</small>`);
        }

        return parts.join('<br>');
    }

    function updateApplicantAgeHint() {
        if (!addFields || !addFields.birthdate || !addFields.ageHint) {
            return;
        }

        const birthdate = addFields.birthdate.value;
        const age = calculateAgeFromBirthdate(birthdate);
        addFields.ageHint.textContent = birthdate && age !== null ? `Alter: ${age} Jahre` : '';
    }

    function updateAssignAgeInfo() {
        if (!assignFields.ageHint && !assignFields.ageWarning) {
            return;
        }

        const rawBirthdate = currentApplicant?.geburtsdatum || '';
        const birthdate = rawBirthdate && rawBirthdate !== '0000-00-00' ? rawBirthdate : '';
        const formatted = birthdate ? formatDateDisplay(birthdate) : null;
        const age = birthdate ? calculateAgeFromBirthdate(birthdate) : null;

        if (assignFields.ageHint) {
            if (formatted) {
                const ageSuffix = age !== null ? ` (Alter: ${age} Jahre)` : '';
                assignFields.ageHint.textContent = `Geburtsdatum: ${formatted}${ageSuffix}`;
            } else {
                assignFields.ageHint.textContent = '';
            }
        }

        if (assignFields.ageWarning) {
            if (age === null) {
                assignFields.ageWarning.textContent = '';
                assignFields.ageWarning.hidden = true;
            } else {
                const type = assignFields.type ? assignFields.type.value : '';
                const warning = getAgeWarningMessage(age, type);
                if (warning) {
                    assignFields.ageWarning.textContent = warning;
                    assignFields.ageWarning.hidden = false;
                } else {
                    assignFields.ageWarning.textContent = '';
                    assignFields.ageWarning.hidden = true;
                }
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

    function adjustNewcomerCount(delta) {
        if (!countDisplay) return;
        const current = parseInt(countDisplay.textContent, 10) || 0;
        countDisplay.textContent = String(Math.max(0, current + delta));
    }

    function populateActionCell(cell, row) {
        if (!cell || !row) return;
        cell.className = 'actions';
        cell.innerHTML = '';

        const assignButton = document.createElement('button');
        assignButton.className = 'primary assign';
        assignButton.type = 'button';
        assignButton.textContent = 'Lizenz zuweisen';
        assignButton.addEventListener('click', () => {
            const applicant = JSON.parse(row.dataset.applicant || '{}');
            openAssignModal(applicant, row);
        });
        cell.appendChild(assignButton);

        const editButton = document.createElement('button');
        editButton.className = 'secondary edit';
        editButton.type = 'button';
        editButton.textContent = 'Bearbeiten';
        editButton.addEventListener('click', () => {
            const applicant = JSON.parse(row.dataset.applicant || '{}');
            openEditModal(applicant, row);
        });
        cell.appendChild(editButton);

        const deleteButton = document.createElement('button');
        deleteButton.className = 'danger delete';
        deleteButton.type = 'button';
        deleteButton.textContent = 'Löschen';
        deleteButton.addEventListener('click', () => {
            const applicant = JSON.parse(row.dataset.applicant || '{}');
            confirmDeleteApplicant(applicant, row);
        });
        cell.appendChild(deleteButton);
    }

    function confirmDeleteApplicant(applicant, row) {
        const id = applicant?.id ? parseInt(applicant.id, 10) : 0;
        if (!id) {
            return;
        }

        const name = [applicant.vorname || '', applicant.nachname || ''].join(' ').trim();
        const question = name ? `Neuwerber "${name}" wirklich löschen?` : 'Neuwerber wirklich löschen?';
        if (!window.confirm(question)) {
            return;
        }

        fetch('api.php?action=delete_newcomer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_newcomer', id })
        })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    if (row && row.parentElement) {
                        row.remove();
                        adjustNewcomerCount(-1);
                        checkEmptyTable();
                        refreshTableSearch();
                    }
                } else {
                    alert(result.message || 'Neuwerber konnte nicht gelöscht werden.');
                }
            })
            .catch(() => alert('Neuwerber konnte nicht gelöscht werden.'));
    }

    function refreshTableSearch() {
        if (window.TableSearch && typeof window.TableSearch.refresh === 'function') {
            window.TableSearch.refresh('#newcomerTable');
        }
    }

    function clearValidation(form) {
        if (!form) return;
        form.querySelectorAll('.validation-hint').forEach(hint => hint.textContent = '');
        form.querySelectorAll('.validation-error').forEach(input => input.classList.remove('validation-error'));
    }

    function escapeHtml(value) {
        return (value || '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[char] || char));
    }

    function formatDateDisplay(value) {
        if (!value || value === '0000-00-00') {
            return '–';
        }

        const parts = String(value).split('-');
        if (parts.length === 3) {
            const [year, month, day] = parts;
            if (day && month && year) {
                return `${day.padStart(2, '0')}.${month.padStart(2, '0')}.${year}`;
            }
        }

        return value;
    }
})();
