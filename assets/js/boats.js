(function () {
    const modal = document.getElementById('addBoatModal');
    const form = document.getElementById('addBoatForm');
    const openButton = document.getElementById('openAddBoat');
    const modalTitle = document.getElementById('boatModalTitle');
    const assignModal = document.getElementById('assignLicenseModal');
    const assignForm = document.getElementById('assignLicenseForm');
    const table = document.getElementById('boatTable');

    if (!modal || !form || !openButton || !table) {
        return;
    }

    const fields = {
        id: document.getElementById('boatId'),
        year: document.getElementById('boatYear'),
        number: document.getElementById('boatNumber'),
        boatNotes: document.getElementById('boatNotes'),
    };

    const assignFields = {
        boatId: document.getElementById('assignBoatId'),
        boatYear: document.getElementById('assignBoatYear'),
        select: document.getElementById('assignLicenseSelect'),
        info: document.getElementById('assignBoatInfo'),
        hint: document.getElementById('assignLicenseHint'),
    };

    const licenseCache = new Map();

    Validation.attach(form);

    openButton.addEventListener('click', () => {
        openCreateModal();
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.checkValidity()) {
            return;
        }

        const isEdit = Boolean(fields.id.value);
        const year = parseInt(fields.year.value || CURRENT_YEAR, 10);
        const action = isEdit ? 'update_boat' : 'save_boat';
        const payload = {
            year,
            boat: {
                id: fields.id.value ? parseInt(fields.id.value, 10) : undefined,
                bootnummer: fields.number.value,
                notizen: fields.boatNotes.value,
            },
        };

        fetch(`api.php?action=${action}`, {
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

    setupModal(modal, () => {
        resetForm();
        if (modalTitle) {
            modalTitle.textContent = 'Boot hinzufügen';
        }
    });

    if (assignModal && assignForm) {
        setupModal(assignModal, () => {
            assignForm.reset();
            assignFields.select.innerHTML = '<option value="">Kein Lizenznehmer</option>';
            assignFields.info.textContent = '';
            assignFields.hint.textContent = '';
        });

        assignForm.addEventListener('submit', event => {
            event.preventDefault();
            const year = parseInt(assignFields.boatYear.value, 10);
            const boatId = parseInt(assignFields.boatId.value, 10);
            if (!year || !boatId) {
                alert('Boot konnte nicht zugewiesen werden.');
                return;
            }

            const licenseId = assignFields.select.value ? parseInt(assignFields.select.value, 10) : null;
            const payload = {
                year,
                boat_id: boatId,
                license_id: licenseId,
            };

            fetch('api.php?action=assign_boat_license', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        window.location.reload();
                    } else {
                        alert(result.message || 'Lizenz konnte nicht zugewiesen werden.');
                    }
                })
                .catch(() => alert('Lizenz konnte nicht zugewiesen werden.'));
        });
    }

    table.addEventListener('click', event => {
        const button = event.target instanceof HTMLElement ? event.target.closest('button') : null;
        if (!button) {
            return;
        }

        const row = button.closest('tr');
        if (!row || !row.dataset.boat) {
            return;
        }

        let boat;
        try {
            boat = JSON.parse(row.dataset.boat);
        } catch (error) {
            console.error('Boat data could not be parsed', error);
            return;
        }

        if (button.classList.contains('edit')) {
            openEditModal(boat);
        } else if (button.classList.contains('delete')) {
            deleteBoat(boat);
        } else if (button.classList.contains('assign')) {
            openAssignModal(boat);
        }
    });

    function openCreateModal() {
        resetForm();
        fields.year.value = String(CURRENT_YEAR);
        fields.id.value = '';
        if (modalTitle) {
            modalTitle.textContent = 'Boot hinzufügen';
        }
        modal.hidden = false;
        fields.number.focus();
    }

    function openEditModal(boat) {
        resetForm();
        fields.id.value = boat.id || '';
        fields.year.value = String(boat.jahr || CURRENT_YEAR);
        fields.number.value = boat.bootnummer || '';
        fields.boatNotes.value = boat.notizen || '';
        if (modalTitle) {
            modalTitle.textContent = 'Boot bearbeiten';
        }
        modal.hidden = false;
        fields.number.focus();
    }

    function deleteBoat(boat) {
        const message = boat.bootnummer ? `Boot ${boat.bootnummer} wirklich löschen?` : 'Boot wirklich löschen?';
        if (!window.confirm(message)) {
            return;
        }

        const payload = {
            year: boat.jahr,
            boat_id: boat.id,
        };

        fetch('api.php?action=delete_boat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.message || 'Boot konnte nicht gelöscht werden.');
                }
            })
            .catch(() => alert('Boot konnte nicht gelöscht werden.'));
    }

    function openAssignModal(boat) {
        if (!assignModal || !assignForm) {
            return;
        }

        assignFields.boatId.value = boat.id || '';
        assignFields.boatYear.value = boat.jahr || '';
        assignFields.info.textContent = boat.bootnummer ? `Boot ${boat.bootnummer}` : 'Boot';
        assignFields.hint.textContent = '';
        assignFields.select.disabled = true;
        assignFields.select.innerHTML = '<option value="">Kein Lizenznehmer</option>';

        assignModal.hidden = false;

        const year = boat.jahr;
        loadLicenses(year)
            .then(licenses => {
                assignFields.select.disabled = false;
                assignFields.select.innerHTML = '<option value="">Kein Lizenznehmer</option>';
                let selectedSet = false;
                licenses.forEach(license => {
                    const option = document.createElement('option');
                    option.value = String(license.id);
                    const labelParts = [`${license.nachname}, ${license.vorname}`, `#${license.id}`];
                    if (license.bootnummer) {
                        labelParts.push(`Boot ${license.bootnummer}`);
                    }
                    option.textContent = labelParts.join(' · ');
                    if (license.boat_id && license.boat_id !== boat.id) {
                        option.disabled = true;
                        option.textContent += ' (bereits vergeben)';
                    }
                    if (!selectedSet && boat.lizenz_id && license.id === boat.lizenz_id) {
                        option.selected = true;
                        selectedSet = true;
                    }
                    assignFields.select.appendChild(option);
                });

                if (!selectedSet && boat.lizenz_id) {
                    assignFields.hint.textContent = 'Die zugewiesene Lizenz wurde nicht gefunden.';
                }

                assignFields.select.focus();
            })
            .catch(() => {
                assignFields.select.disabled = false;
                assignFields.hint.textContent = 'Lizenzen konnten nicht geladen werden.';
            });
    }

    function loadLicenses(year) {
        if (licenseCache.has(year)) {
            return Promise.resolve(licenseCache.get(year));
        }
        return fetch(`api.php?action=get_boat_licenses&year=${encodeURIComponent(year)}`)
            .then(r => r.json())
            .then(result => {
                if (!result.success) {
                    throw new Error('Request failed');
                }
                licenseCache.set(year, result.licenses || []);
                return result.licenses || [];
            });
    }

    function resetForm() {
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

    function setupModal(modalElement, onClose) {
        modalElement.querySelectorAll('[data-close]').forEach(button => {
            button.addEventListener('click', () => {
                modalElement.hidden = true;
                if (typeof onClose === 'function') {
                    onClose();
                }
            });
        });
    }
})();
