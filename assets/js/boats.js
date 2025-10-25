(function () {
    const modal = document.getElementById('addBoatModal');
    const form = document.getElementById('addBoatForm');
    const openButton = document.getElementById('openAddBoat');
    const modalTitle = document.getElementById('boatModalTitle');
    const assignModal = document.getElementById('assignLicenseModal');
    const assignForm = document.getElementById('assignLicenseForm');
    const table = document.getElementById('boatTable');

    const currentYearAttribute = document.body ? document.body.getAttribute('data-current-year') : null;
    const currentYear = currentYearAttribute ? parseInt(currentYearAttribute, 10) : NaN;

    if (!modal || !form || !openButton || !table) {
        return;
    }

    const fields = {
        id: document.getElementById('boatId'),
        number: document.getElementById('boatNumber'),
        boatNotes: document.getElementById('boatNotes'),
    };

    const assignFields = {
        boatId: document.getElementById('assignBoatId'),
        select: document.getElementById('assignLicenseSelect'),
        info: document.getElementById('assignBoatInfo'),
        hint: document.getElementById('assignLicenseHint'),
    };

    let licenseCache = null;

    if (typeof Validation !== 'undefined' && typeof Validation.attach === 'function') {
        Validation.attach(form);
    }

    openButton.addEventListener('click', () => {
        openCreateModal();
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.checkValidity()) {
            return;
        }

        const isEdit = Boolean(fields.id.value);
        const action = isEdit ? 'update_boat' : 'save_boat';
        const payload = {
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
            const boatId = parseInt(assignFields.boatId.value, 10);
            if (!boatId) {
                alert('Boot konnte nicht zugewiesen werden.');
                return;
            }

            const licenseeId = assignFields.select.value ? parseInt(assignFields.select.value, 10) : null;
            const payload = {
                boat_id: boatId,
                licensee_id: licenseeId,
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
                        alert(result.message || 'Lizenznehmer konnte nicht zugewiesen werden.');
                    }
                })
                .catch(() => alert('Lizenznehmer konnte nicht zugewiesen werden.'));
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
        assignFields.info.textContent = boat.bootnummer ? `Boot ${boat.bootnummer}` : 'Boot';
        assignFields.hint.textContent = '';
        assignFields.select.disabled = true;
        assignFields.select.innerHTML = '<option value="">Kein Lizenznehmer</option>';

        assignModal.hidden = false;

        loadLicensees()
            .then(licensees => {
                assignFields.select.disabled = false;
                assignFields.select.innerHTML = '<option value="">Kein Lizenznehmer</option>';
                let selectedSet = false;
                licensees.forEach(licensee => {
                    const option = document.createElement('option');
                    option.value = String(licensee.id);
                    const labelParts = [`${licensee.nachname}, ${licensee.vorname}`, `#${licensee.id}`];
                    if (licensee.bootnummer) {
                        labelParts.push(`Boot ${licensee.bootnummer}`);
                    }
                    option.textContent = labelParts.join(' · ');
                    if (licensee.boat_id && licensee.boat_id !== boat.id) {
                        option.disabled = true;
                        option.textContent += ' (bereits vergeben)';
                    }
                    if (!selectedSet && boat.lizenznehmer && licensee.id === boat.lizenznehmer.id) {
                        option.selected = true;
                        selectedSet = true;
                    }
                    assignFields.select.appendChild(option);
                });

                if (!selectedSet && boat.lizenznehmer && boat.lizenznehmer.id) {
                    assignFields.hint.textContent = 'Der zugewiesene Lizenznehmer wurde nicht gefunden.';
                }

                assignFields.select.focus();
            })
            .catch(() => {
                assignFields.select.disabled = false;
                assignFields.hint.textContent = 'Lizenznehmer konnten nicht geladen werden.';
            });
    }

    function loadLicensees() {
        if (licenseCache) {
            return Promise.resolve(licenseCache);
        }
        const params = new URLSearchParams({ action: 'get_boat_licenses' });
        if (!Number.isNaN(currentYear) && currentYear > 0) {
            params.set('jahr', String(currentYear));
        }

        return fetch(`api.php?${params.toString()}`)
            .then(r => r.json())
            .then(result => {
                if (!result.success) {
                    throw new Error('Request failed');
                }
                licenseCache = result.licensees || [];
                return licenseCache;
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
