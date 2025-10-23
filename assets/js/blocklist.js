(function () {
    const modal = document.getElementById('blocklistModal');
    const deleteModal = document.getElementById('blocklistDeleteModal');
    const form = document.getElementById('blocklistForm');
    const openButton = document.getElementById('openAddBlockEntry');
    const table = document.getElementById('blocklistTable');
    const modalTitle = document.getElementById('blocklistModalTitle');
    const deleteText = document.getElementById('blocklistDeleteText');
    const confirmDeleteButton = document.getElementById('confirmBlocklistDelete');

    if (!modal || !form || !openButton || !table) {
        return;
    }

    const fields = {
        id: document.getElementById('blocklistId'),
        firstName: document.getElementById('blocklistFirstName'),
        lastName: document.getElementById('blocklistLastName'),
        licenseNumber: document.getElementById('blocklistLicenseNumber'),
    };

    let currentEntry = null;

    if (typeof Validation !== 'undefined' && typeof Validation.attach === 'function') {
        Validation.attach(form);
    }

    setupModal(modal, () => {
        resetForm();
        if (modalTitle) {
            modalTitle.textContent = 'Person hinzufügen';
        }
    });

    if (deleteModal) {
        setupModal(deleteModal, () => {
            currentEntry = null;
            if (deleteText) {
                deleteText.textContent = 'Soll dieser Eintrag gelöscht werden?';
            }
        });
    }

    openButton.addEventListener('click', () => {
        resetForm();
        if (modalTitle) {
            modalTitle.textContent = 'Person hinzufügen';
        }
        modal.hidden = false;
        fields.firstName.focus();
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.checkValidity()) {
            return;
        }

        const entry = {
            id: fields.id.value ? parseInt(fields.id.value, 10) : undefined,
            vorname: fields.firstName.value.trim(),
            nachname: fields.lastName.value.trim(),
            lizenznummer: fields.licenseNumber.value.trim(),
        };

        fetch('api.php?action=save_block_entry', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ entry })
        })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.message || 'Eintrag konnte nicht gespeichert werden.');
                }
            })
            .catch(() => alert('Eintrag konnte nicht gespeichert werden.'));
    });

    table.addEventListener('click', event => {
        const button = event.target instanceof HTMLElement ? event.target.closest('button') : null;
        if (!button) {
            return;
        }

        const row = button.closest('tr');
        if (!row || !row.dataset.entry) {
            return;
        }

        let entry;
        try {
            entry = JSON.parse(row.dataset.entry);
        } catch (error) {
            console.error('Blocklist entry could not be parsed', error);
            return;
        }

        if (button.classList.contains('edit')) {
            openEditModal(entry);
        } else if (button.classList.contains('delete')) {
            openDeleteModal(entry);
        }
    });

    if (confirmDeleteButton) {
        confirmDeleteButton.addEventListener('click', () => {
            if (!currentEntry || !currentEntry.id) {
                deleteModal.hidden = true;
                return;
            }

            fetch('api.php?action=delete_block_entry', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: currentEntry.id })
            })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        window.location.reload();
                    } else {
                        alert(result.message || 'Eintrag konnte nicht gelöscht werden.');
                    }
                })
                .catch(() => alert('Eintrag konnte nicht gelöscht werden.'));
        });
    }

    function openEditModal(entry) {
        resetForm();
        fields.id.value = entry.id || '';
        fields.firstName.value = entry.vorname || '';
        fields.lastName.value = entry.nachname || '';
        fields.licenseNumber.value = entry.lizenznummer || '';
        if (modalTitle) {
            modalTitle.textContent = 'Person bearbeiten';
        }
        modal.hidden = false;
        fields.firstName.focus();
    }

    function openDeleteModal(entry) {
        currentEntry = entry;
        if (deleteText) {
            const name = [entry.nachname, entry.vorname].filter(Boolean).join(', ');
            const licenseInfo = entry.lizenznummer ? ` (Lizenznummer: ${entry.lizenznummer})` : '';
            deleteText.textContent = name ? `Eintrag für ${name}${licenseInfo} wirklich löschen?` : 'Eintrag wirklich löschen?';
        }
        if (deleteModal) {
            deleteModal.hidden = false;
        }
    }

    function resetForm() {
        form.reset();
        fields.id.value = '';
        fields.firstName.value = '';
        fields.lastName.value = '';
        fields.licenseNumber.value = '';
        form.querySelectorAll('.validation-hint').forEach(hint => {
            hint.textContent = '';
        });
        form.querySelectorAll('.validation-error').forEach(input => {
            input.classList.remove('validation-error');
        });
    }

    function setupModal(modalElement, onClose) {
        if (!modalElement) {
            return;
        }
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
