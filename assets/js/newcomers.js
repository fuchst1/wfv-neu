(function () {
    const assignModal = document.getElementById('assignModal');
    const assignForm = document.getElementById('assignForm');
    const addModal = document.getElementById('newApplicantModal');
    const addForm = document.getElementById('newApplicantForm');
    const addButton = document.getElementById('openAddApplicant');
    const countDisplay = document.getElementById('newcomerCount');

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
                action: 'create_newcomer',
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

            fetch('api.php?action=create_newcomer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(r => r.json())
                .then(result => {
                    if (result.success && result.bewerber) {
                        addApplicantRow(result.bewerber);
                        adjustNewcomerCount(1);
                        hideModal(addModal);
                    } else {
                        alert(result.message || 'Neuwerber konnte nicht gespeichert werden.');
                    }
                })
                .catch(() => alert('Neuwerber konnte nicht gespeichert werden.'));
        });
    }

    document.querySelectorAll('tr[data-applicant]').forEach(row => {
        const applicant = JSON.parse(row.dataset.applicant);
        const assignBtn = row.querySelector('.assign');
        if (assignBtn) {
            assignBtn.addEventListener('click', () => openAssignModal(applicant, row));
        }
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
        assignFields.type.value = '';
        assignFields.cost.value = '';
        assignFields.tip.value = '0.00';
        assignFields.total.value = '0.00';
        assignFields.date.value = getTodayDateString();
        assignFields.notes.value = applicant.notizen || '';

        assignModal.hidden = false;
        assignFields.year.dispatchEvent(new Event('change'));
    }

    function openAddModal() {
        if (!addModal || !addForm || !addFields) return;
        addForm.reset();
        clearValidation(addForm);
        addFields.date.value = getTodayDateString();
        addModal.hidden = false;
        addFields.firstName.focus();
    }

    function hideModal(modal) {
        if (!modal) return;
        modal.hidden = true;
        if (modal === assignModal) {
            currentApplicant = null;
            currentRow = null;
        }
        if (modal === addModal && addForm) {
            addForm.reset();
            clearValidation(addForm);
        }
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
        const tbody = document.querySelector('tbody');
        if (!tbody) return;
        if (tbody.querySelectorAll('tr').length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.className = 'empty';
            cell.textContent = 'Keine Neuwerber vorhanden.';
            row.appendChild(cell);
            tbody.appendChild(row);
        }
    }

    function addApplicantRow(applicant) {
        const tbody = document.querySelector('tbody');
        if (!tbody) return;

        const emptyCell = tbody.querySelector('td.empty');
        if (emptyCell) {
            emptyCell.parentElement.remove();
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
        dateCell.textContent = applicant.bewerbungsdatum ? applicant.bewerbungsdatum : '–';

        const notesCell = document.createElement('td');
        notesCell.innerHTML = escapeHtml(applicant.notizen || '').replace(/\n/g, '<br>');

        const actionCell = document.createElement('td');
        actionCell.className = 'actions';
        const assignButton = document.createElement('button');
        assignButton.className = 'primary assign';
        assignButton.type = 'button';
        assignButton.textContent = 'Lizenz zuweisen';
        assignButton.addEventListener('click', () => openAssignModal(applicant, row));
        actionCell.appendChild(assignButton);

        row.appendChild(nameCell);
        row.appendChild(contactCell);
        row.appendChild(dateCell);
        row.appendChild(notesCell);
        row.appendChild(actionCell);

        tbody.insertBefore(row, tbody.firstChild);
    }

    function adjustNewcomerCount(delta) {
        if (!countDisplay) return;
        const current = parseInt(countDisplay.textContent, 10) || 0;
        countDisplay.textContent = String(Math.max(0, current + delta));
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
})();
