(function () {
    const createYearModal = document.getElementById('createYearModal');
    const deleteYearModal = document.getElementById('deleteYearModal');
    const closeYearModal = document.getElementById('closeYearModal');
    const createYearForm = document.getElementById('createYearForm');
    const createYearButton = document.getElementById('openCreateYear');
    const yearInput = document.getElementById('newYear');
    const priceInputs = createYearForm ? Array.from(createYearForm.querySelectorAll('.price-input')) : [];
    const yearToDeleteLabel = document.getElementById('yearToDelete');
    const confirmDeleteButton = document.getElementById('confirmDeleteYear');
    const yearToCloseLabel = document.getElementById('yearToClose');
    const confirmCloseYearButton = document.getElementById('confirmCloseYear');

    let pendingYearDelete = null;
    let pendingYearClose = null;

    function closeModals() {
        [createYearModal, deleteYearModal, closeYearModal].forEach(modal => {
            if (modal) {
                modal.hidden = true;
            }
        });
        pendingYearDelete = null;
        pendingYearClose = null;
    }

    function prefillCreateYearForm() {
        if (!createYearForm) return;
        createYearForm.reset();
        if (yearInput) {
            let nextYear = new Date().getFullYear() + 1;
            if (Array.isArray(EXISTING_YEARS) && EXISTING_YEARS.length > 0) {
                const maxYear = Math.max(...EXISTING_YEARS.map(year => Number(year) || 0));
                if (Number.isFinite(maxYear) && maxYear >= 0) {
                    nextYear = maxYear + 1;
                }
            }
            yearInput.value = nextYear;
        }

        if (!Array.isArray(priceInputs) || priceInputs.length === 0) {
            return;
        }

        priceInputs.forEach(input => {
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

    document.querySelectorAll('[data-close]').forEach(button => {
        button.addEventListener('click', closeModals);
    });

    if (createYearButton) {
        createYearButton.addEventListener('click', () => {
            prefillCreateYearForm();
            if (createYearModal) {
                createYearModal.hidden = false;
            }
        });
    }

    if (createYearForm) {
        Validation.attach(createYearForm);
        createYearForm.addEventListener('submit', event => {
            event.preventDefault();
            if (!createYearForm.checkValidity()) {
                return;
            }
            const year = parseInt(yearInput.value, 10);
            if (!year) return;
            const prices = {};
            priceInputs.forEach(input => {
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
                        window.location.href = `admin.php?jahr=${year}`;
                    } else {
                        alert(result.message || 'Jahr konnte nicht erstellt werden');
                    }
                })
                .catch(() => alert('Jahr konnte nicht erstellt werden'));
        });
    }

    document.querySelectorAll('[data-delete-year]').forEach(button => {
        button.addEventListener('click', () => {
            if (button.disabled) return;
            const { deleteYear } = button.dataset;
            const year = parseInt(deleteYear, 10);
            if (!year) return;
            pendingYearDelete = year;
            if (yearToDeleteLabel) {
                yearToDeleteLabel.textContent = year.toString();
            }
            if (deleteYearModal) {
                deleteYearModal.hidden = false;
            }
        });
    });

    document.querySelectorAll('[data-close-year]').forEach(button => {
        button.addEventListener('click', () => {
            if (button.disabled) return;
            const { closeYear } = button.dataset;
            const year = parseInt(closeYear, 10);
            if (!year) return;
            pendingYearClose = year;
            if (yearToCloseLabel) {
                yearToCloseLabel.textContent = year.toString();
            }
            if (closeYearModal) {
                closeYearModal.hidden = false;
            }
        });
    });

    if (confirmDeleteButton) {
        confirmDeleteButton.addEventListener('click', () => {
            if (!pendingYearDelete) return;
            fetch('api.php?action=delete_year', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ year: pendingYearDelete })
            })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        window.location.href = 'admin.php';
                    } else {
                        alert(result.message || 'Jahr konnte nicht gelöscht werden');
                    }
                })
                .catch(() => alert('Jahr konnte nicht gelöscht werden'));
        });
    }

    if (confirmCloseYearButton) {
        confirmCloseYearButton.addEventListener('click', () => {
            if (!pendingYearClose) return;
            fetch('api.php?action=close_year', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ year: pendingYearClose })
            })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        window.location.href = `admin.php?jahr=${pendingYearClose}`;
                    } else {
                        alert(result.message || 'Jahr konnte nicht abgeschlossen werden');
                    }
                })
                .catch(() => alert('Jahr konnte nicht abgeschlossen werden'));
        });
    }
})();
