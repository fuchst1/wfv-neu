(function () {
    const assignModal = document.getElementById('assignModal');
    const assignForm = document.getElementById('assignForm');

    if (!assignModal || !assignForm) {
        return;
    }

    const fields = {
        year: document.getElementById('assignYear'),
        type: document.getElementById('assignType'),
        cost: document.getElementById('assignCost'),
        tip: document.getElementById('assignTip'),
        total: document.getElementById('assignTotal'),
        date: document.getElementById('assignDate'),
        notes: document.getElementById('assignNotes'),
    };

    let currentApplicant = null;
    let currentRow = null;
    const priceCache = {};

    if (typeof LICENSE_PRICES === 'object') {
        priceCache[CURRENT_YEAR] = LICENSE_PRICES;
    }

    Validation.attach(assignForm);

    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', () => closeModal());
    });

    document.querySelectorAll('tr[data-applicant]').forEach(row => {
        const applicant = JSON.parse(row.dataset.applicant);
        const assignBtn = row.querySelector('.assign');
        if (assignBtn) {
            assignBtn.addEventListener('click', () => openAssignModal(applicant, row));
        }
    });

    fields.tip.addEventListener('input', updateTotal);
    fields.cost.addEventListener('input', updateTotal);

    fields.type.addEventListener('change', () => {
        const year = fields.year.value;
        const type = fields.type.value;
        const prices = priceCache[year];
        if (prices && prices[type]) {
            fields.cost.value = Number(prices[type]).toFixed(2);
        }
        updateTotal();
    });

    fields.year.addEventListener('change', event => {
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
        if (!Validation.validateInput(fields.year) || !Validation.validateInput(fields.type) || !Validation.validateInput(fields.cost)) {
            return;
        }
        const payload = {
            action: 'assign_newcomer',
            applicant_id: currentApplicant?.id,
            year: fields.year.value,
            license_type: fields.type.value,
            kosten: fields.cost.value,
            trinkgeld: fields.tip.value || 0,
            zahlungsdatum: fields.date.value,
            notizen: fields.notes.value,
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
                        checkEmptyTable();
                    }
                    closeModal();
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
        document.querySelectorAll('.validation-hint').forEach(h => h.textContent = '');
        document.querySelectorAll('.validation-error').forEach(el => el.classList.remove('validation-error'));

        fields.year.value = CURRENT_YEAR;
        fields.type.value = '';
        fields.cost.value = '';
        fields.tip.value = '0.00';
        fields.total.value = '0.00';
        fields.date.value = getTodayDateString();
        fields.notes.value = applicant.notizen || '';

        assignModal.hidden = false;
        fields.year.dispatchEvent(new Event('change'));
    }

    function closeModal() {
        assignModal.hidden = true;
        currentApplicant = null;
        currentRow = null;
    }

    function updateTotal() {
        const cost = parseFloat(fields.cost.value || '0');
        const tip = parseFloat(fields.tip.value || '0');
        fields.total.value = (cost + tip).toFixed(2);
    }

    function updatePriceSuggestion() {
        const year = fields.year.value;
        const type = fields.type.value;
        const prices = priceCache[year];
        if (prices && type && prices[type]) {
            fields.cost.value = Number(prices[type]).toFixed(2);
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
})();
