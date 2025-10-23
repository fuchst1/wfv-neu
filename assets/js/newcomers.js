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
    };

    const addFields = addForm ? {
        firstName: document.getElementById('applicantFirstName'),
        lastName: document.getElementById('applicantLastName'),
        street: document.getElementById('applicantStreet'),
        zip: document.getElementById('applicantZip'),
        city: document.getElementById('applicantCity'),
        phone: document.getElementById('applicantPhone'),
        email: document.getElementById('applicantEmail'),
        card: document.getElementById('applicantCard'),
        date: document.getElementById('applicantDate'),
        notes: document.getElementById('applicantNotes'),
    } : null;

    let currentApplicant = null;
    let currentRow = null;
    let editingApplicantId = null;
    let editingRow = null;
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
        addForm.addEventListener('submit', event => {
            event.preventDefault();
            if (!Validation.validateInput(addFields.firstName) || !Validation.validateInput(addFields.lastName)) {
                return;
            }

            const payload = {
                action: editingApplicantId ? 'update_newcomer' : 'create_newcomer',
                id: editingApplicantId,
                vorname: addFields.firstName.value,
                nachname: addFields.lastName.value,
                strasse: addFields.street.value,
                plz: addFields.zip.value,
                ort: addFields.city.value,
                telefon: addFields.phone.value,
                email: addFields.email.value,
                fischerkartennummer: addFields.card.value,
                bewerbungsdatum: addFields.date.value,
                notizen: addFields.notes.value,
            };

            const action = editingApplicantId ? 'update_newcomer' : 'create_newcomer';

            fetch(`api.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(r => r.json())
                .then(result => {
                    if (result.success && result.bewerber) {
                        if (editingApplicantId && editingRow) {
                            updateApplicantRow(editingRow, result.bewerber);
                        } else {
                            addApplicantRow(result.bewerber);
                            adjustNewcomerCount(1);
                        }
                        hideModal(addModal);
                    } else {
                        alert(result.message || 'Neuwerber konnte nicht gespeichert werden.');
                    }
                })
                .catch(() => alert('Neuwerber konnte nicht gespeichert werden.'));
        });
    }

    const applicantRows = tableBody ? tableBody.querySelectorAll('tr[data-applicant]') : [];
    applicantRows.forEach(row => {
        populateActionCell(row.querySelector('td.actions'), row);
    });

    assignFields.tip.addEventListener('input', updateTotal);
    assignFields.cost.addEventListener('input', updateTotal);

    assignFields.type.addEventListener('change', () => {
        const year = assignFields.year.value;
        const type = assignFields.type.value;
        const prices = priceCache[year];
        if (prices && prices[type]) {
            assignFields.cost.value = Number(prices[type]).toFixed(2);
        }
        updateTotal();
    });

    assignFields.year.addEventListener('change', event => {
        const year = event.target.value;
        if (!year) return;
        if (priceCache[year]) {
            updatePriceSuggestion();
            return;
        }
        fetch(`api.php?action=get_prices&year=${year}`)
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    priceCache[year] = result.preise || {};
                    updatePriceSuggestion();
                }
            });
    });

    assignForm.addEventListener('submit', event => {
        event.preventDefault();
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

        fetch('api.php?action=assign_newcomer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    if (currentRow) {
                        currentRow.remove();
                        adjustNewcomerCount(-1);
                        checkEmptyTable();
                        refreshTableSearch();
                    }
                    hideModal(assignModal);
                } else {
                    alert(result.message || 'Lizenz konnte nicht zugewiesen werden.');
                }
            })
            .catch(() => alert('Lizenz konnte nicht zugewiesen werden.'));
    });

    function openAssignModal(applicant, row) {
        currentApplicant = applicant;
        currentRow = row;
        assignForm.reset();
        clearValidation(assignForm);

        assignFields.year.value = CURRENT_YEAR;
        assignFields.type.value = 'Angel';
        assignFields.cost.value = '';
        assignFields.tip.value = '0.00';
        assignFields.total.value = '0.00';
        assignFields.date.value = getTodayDateString();
        assignFields.notes.value = applicant.notizen || '';

        showModal(assignModal);
        assignFields.type.dispatchEvent(new Event('change'));
        assignFields.year.dispatchEvent(new Event('change'));
    }

    function openAddModal() {
        if (!addModal || !addForm || !addFields) return;
        addForm.reset();
        clearValidation(addForm);
        addFields.date.value = getTodayDateString();
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
        }
        if (modal === addModal && addForm) {
            addForm.reset();
            clearValidation(addForm);
            editingApplicantId = null;
            editingRow = null;
        }
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
        addFields.card.value = applicant?.fischerkartennummer || '';
        const dateValue = applicant?.bewerbungsdatum;
        addFields.date.value = dateValue && dateValue !== '0000-00-00' ? dateValue : '';
        addFields.notes.value = applicant?.notizen || '';

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
        contactCell.innerHTML = [
            `<small>${escapeHtml(applicant.strasse || '')}</small>`,
            `<small>${escapeHtml(applicant.plz || '')} ${escapeHtml(applicant.ort || '')}</small>`,
            `<small>Telefon: ${escapeHtml(applicant.telefon || '-')} · E-Mail: ${escapeHtml(applicant.email || '-')}</small>`,
            `<small>Fischerkartennummer: ${escapeHtml(applicant.fischerkartennummer || '-')}</small>`
        ].join('<br>');

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
            contactCell.innerHTML = [
                `<small>${escapeHtml(applicant.strasse || '')}</small>`,
                `<small>${escapeHtml(applicant.plz || '')} ${escapeHtml(applicant.ort || '')}</small>`,
                `<small>Telefon: ${escapeHtml(applicant.telefon || '-')} · E-Mail: ${escapeHtml(applicant.email || '-')}</small>`,
                `<small>Fischerkartennummer: ${escapeHtml(applicant.fischerkartennummer || '-')}</small>`
            ].join('<br>');
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
