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
    const searchForm = document.getElementById('licenseeSearchForm');
    const searchInput = document.getElementById('licenseeSearchInput');
    const searchReset = document.getElementById('licenseeSearchReset');
    const searchMessage = document.getElementById('licenseeSearchMessage');
    const searchResults = document.getElementById('licenseeSearchResults');
    const defaultSearchMessage = 'Geben Sie einen Namen ein, um die Suche zu starten.';

    let pendingYearDelete = null;
    let pendingYearClose = null;
    let closeYearInFlight = false;
    let activeSearchController = null;

    function updateSearchMessage(message, variant = 'info') {
        if (!searchMessage) {
            return;
        }

        const text = typeof message === 'string' ? message : '';
        searchMessage.textContent = text;
        searchMessage.hidden = text === '';

        if (variant === 'error') {
            searchMessage.classList.add('error');
        } else {
            searchMessage.classList.remove('error');
        }
    }

    function clearSearchResults() {
        if (!searchResults) {
            return;
        }

        searchResults.innerHTML = '';
        searchResults.hidden = true;
    }

    function resetSearch(options = {}) {
        const { skipFormReset = false } = options;

        if (activeSearchController) {
            activeSearchController.abort();
            activeSearchController = null;
        }

        if (searchForm && !skipFormReset) {
            searchForm.reset();
        }

        clearSearchResults();
        updateSearchMessage(defaultSearchMessage);

        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
    }

    function formatCurrencyValue(entry, key) {
        const formatted = entry && typeof entry[`${key}_formatted`] === 'string' ? entry[`${key}_formatted`] : null;
        const raw = entry ? entry[key] : null;
        if (formatted && formatted !== '') {
            return `${formatted} €`;
        }
        if (typeof raw === 'number' && Number.isFinite(raw)) {
            return `${raw.toFixed(2)} €`;
        }
        return '–';
    }

    function formatDateValue(entry, key) {
        const formatted = entry && typeof entry[`${key}_formatted`] === 'string' ? entry[`${key}_formatted`] : null;
        const raw = entry ? entry[key] : null;
        if (formatted && formatted !== '') {
            return formatted;
        }
        if (typeof raw === 'string' && raw) {
            return raw;
        }
        return '–';
    }

    function renderSearchResults(results) {
        if (!searchResults) {
            return;
        }

        searchResults.innerHTML = '';

        if (!Array.isArray(results) || results.length === 0) {
            searchResults.hidden = true;
            return;
        }

        searchResults.hidden = false;

        results.forEach(result => {
            const card = document.createElement('article');
            card.className = 'licensee-card';

            const header = document.createElement('header');
            const title = document.createElement('h4');
            const nameParts = [];
            if (result && result.vorname) {
                nameParts.push(result.vorname);
            }
            if (result && result.nachname) {
                nameParts.push(result.nachname);
            }
            if (nameParts.length === 0 && result && typeof result.id === 'number') {
                nameParts.push(`#${result.id}`);
            }
            title.textContent = nameParts.length ? nameParts.join(' ') : 'Unbekannter Lizenznehmer';
            header.appendChild(title);

            const meta = document.createElement('span');
            meta.className = 'licensee-card-meta';
            const metaParts = [];
            if (result && result.fischerkartennummer) {
                metaParts.push(`Lizenznummer: ${result.fischerkartennummer}`);
            }
            const licenseCount = Array.isArray(result && result.licenses) ? result.licenses.length : 0;
            metaParts.push(licenseCount === 1 ? '1 Lizenz' : `${licenseCount} Lizenzen`);
            meta.textContent = metaParts.join(' · ');
            header.appendChild(meta);

            card.appendChild(header);

            const detailsList = document.createElement('dl');
            detailsList.className = 'licensee-details';

            function appendDetail(label, value, options = {}) {
                if (!value) {
                    return;
                }
                const wrapper = document.createElement('div');
                wrapper.className = 'licensee-detail';
                const dt = document.createElement('dt');
                dt.textContent = label;
                const dd = document.createElement('dd');
                if (options.type === 'email') {
                    const link = document.createElement('a');
                    link.href = `mailto:${value}`;
                    link.textContent = value;
                    dd.appendChild(link);
                } else if (options.type === 'phone') {
                    const telValue = String(value).replace(/\s+/g, '');
                    const link = document.createElement('a');
                    link.href = `tel:${telValue}`;
                    link.textContent = value;
                    dd.appendChild(link);
                } else {
                    dd.textContent = value;
                }
                wrapper.appendChild(dt);
                wrapper.appendChild(dd);
                detailsList.appendChild(wrapper);
            }

            if (result && result.geburtsdatum_formatted) {
                const parts = [result.geburtsdatum_formatted];
                if (typeof result.alter === 'number' && Number.isFinite(result.alter)) {
                    parts.push(`${result.alter} Jahre`);
                }
                appendDetail('Geburtsdatum', parts.join(' · '));
            }

            const addressParts = [];
            if (result && result.strasse) {
                addressParts.push(result.strasse);
            }
            const cityParts = [];
            if (result && result.plz) {
                cityParts.push(result.plz);
            }
            if (result && result.ort) {
                cityParts.push(result.ort);
            }
            if (cityParts.length) {
                addressParts.push(cityParts.join(' '));
            }
            if (addressParts.length) {
                appendDetail('Adresse', addressParts.join(', '));
            }

            if (result && result.telefon) {
                appendDetail('Telefon', result.telefon, { type: 'phone' });
            }
            if (result && result.email) {
                appendDetail('E-Mail', result.email, { type: 'email' });
            }
            if (result && result.fischerkartennummer) {
                appendDetail('Fischerkartennummer', result.fischerkartennummer);
            }

            if (detailsList.children.length > 0) {
                card.appendChild(detailsList);
            }

            const historyContainer = document.createElement('div');
            historyContainer.className = 'licensee-history';
            const historyTitle = document.createElement('h5');
            historyTitle.textContent = 'Lizenzen';
            historyContainer.appendChild(historyTitle);

            const licenses = Array.isArray(result && result.licenses) ? result.licenses : [];
            if (licenses.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'empty';
                empty.textContent = 'Keine Lizenzen gefunden.';
                historyContainer.appendChild(empty);
            } else {
                const table = document.createElement('table');
                table.className = 'licensee-history-table';

                const thead = document.createElement('thead');
                const headRow = document.createElement('tr');
                ['Lizenz-ID', 'Jahr', 'Lizenztyp', 'Zahlungsdatum', 'Kosten', 'Trinkgeld', 'Gesamt', 'Notizen'].forEach(text => {
                    const th = document.createElement('th');
                    th.textContent = text;
                    headRow.appendChild(th);
                });
                thead.appendChild(headRow);
                table.appendChild(thead);

                const tbody = document.createElement('tbody');
                licenses.forEach(license => {
                    const row = document.createElement('tr');

                    const idCell = document.createElement('td');
                    if (license && typeof license.lizenz_id === 'number' && license.lizenz_id > 0 && typeof license.jahr === 'number') {
                        const link = document.createElement('a');
                        link.href = `index.php?jahr=${encodeURIComponent(license.jahr)}#license-${license.lizenz_id}`;
                        link.textContent = `#${license.lizenz_id}`;
                        idCell.appendChild(link);
                    } else {
                        idCell.textContent = '–';
                    }
                    row.appendChild(idCell);

                    const yearCell = document.createElement('td');
                    yearCell.textContent = license && typeof license.jahr === 'number' ? String(license.jahr) : '–';
                    row.appendChild(yearCell);

                    const typeCell = document.createElement('td');
                    typeCell.textContent = license && license.lizenztyp ? license.lizenztyp : '–';
                    row.appendChild(typeCell);

                    const dateCell = document.createElement('td');
                    dateCell.textContent = formatDateValue(license, 'zahlungsdatum');
                    row.appendChild(dateCell);

                    const costCell = document.createElement('td');
                    costCell.textContent = formatCurrencyValue(license, 'kosten');
                    row.appendChild(costCell);

                    const tipCell = document.createElement('td');
                    tipCell.textContent = formatCurrencyValue(license, 'trinkgeld');
                    row.appendChild(tipCell);

                    const totalCell = document.createElement('td');
                    totalCell.textContent = formatCurrencyValue(license, 'gesamt');
                    row.appendChild(totalCell);

                    const notesCell = document.createElement('td');
                    notesCell.className = 'notes';
                    notesCell.textContent = license && license.notizen ? license.notizen : '–';
                    row.appendChild(notesCell);

                    tbody.appendChild(row);
                });

                table.appendChild(tbody);
                historyContainer.appendChild(table);
            }

            card.appendChild(historyContainer);
            searchResults.appendChild(card);
        });
    }

    function markYearAsClosed(year, closure) {
        if (!year) {
            return;
        }

        const closeButton = document.querySelector(`[data-close-year="${year}"]`);
        const row = closeButton ? closeButton.closest('tr') : null;
        if (!row) {
            return;
        }

        const statusCell = row.querySelector('td:nth-child(2)');
        if (statusCell) {
            statusCell.innerHTML = '';
            const badge = document.createElement('span');
            badge.className = 'badge badge-closed';
            badge.textContent = 'Abgeschlossen';
            statusCell.appendChild(badge);

            const formattedDate = closure && (closure.abgeschlossen_am_formatted || closure.abgeschlossen_am);
            if (formattedDate) {
                statusCell.appendChild(document.createElement('br'));
                const small = document.createElement('small');
                small.textContent = `am ${formattedDate}`;
                statusCell.appendChild(small);
            }
        }

        const deleteButton = row.querySelector('[data-delete-year]');
        [closeButton, deleteButton].forEach(btn => {
            if (!btn) return;
            btn.disabled = true;
            btn.setAttribute('aria-disabled', 'true');
        });
    }

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
            if (closeYearInFlight) return;
            closeYearInFlight = true;
            confirmCloseYearButton.disabled = true;
            fetch('api.php?action=close_year', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ year: pendingYearClose })
            })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        markYearAsClosed(pendingYearClose, result.closure || null);
                        closeModals();
                    } else {
                        alert(result.message || 'Jahr konnte nicht abgeschlossen werden');
                    }
                })
                .catch(() => alert('Jahr konnte nicht abgeschlossen werden'))
                .finally(() => {
                    closeYearInFlight = false;
                    confirmCloseYearButton.disabled = false;
                });
        });
    }

    if (searchForm) {
        searchForm.addEventListener('submit', event => {
            event.preventDefault();

            if (!searchInput) {
                return;
            }

            const query = searchInput.value.trim();
            if (query.length < 2) {
                clearSearchResults();
                updateSearchMessage('Bitte mindestens zwei Zeichen eingeben.', 'error');
                return;
            }

            if (activeSearchController) {
                activeSearchController.abort();
            }

            activeSearchController = new AbortController();
            updateSearchMessage('Suche läuft…');
            clearSearchResults();

            const params = new URLSearchParams({ action: 'search_licensees', query });

            fetch(`api.php?${params.toString()}`, { signal: activeSearchController.signal })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Netzwerkfehler');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data || data.success === false) {
                        const message = data && data.message ? data.message : 'Die Suche ist fehlgeschlagen.';
                        updateSearchMessage(message, 'error');
                        clearSearchResults();
                        return;
                    }

                    const results = Array.isArray(data.results) ? data.results : [];
                    if (results.length === 0) {
                        clearSearchResults();
                        updateSearchMessage('Keine Lizenznehmer gefunden.');
                        return;
                    }

                    renderSearchResults(results);
                    updateSearchMessage(`${results.length} Lizenznehmer gefunden.`);
                })
                .catch(error => {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                    updateSearchMessage('Die Suche ist fehlgeschlagen.', 'error');
                    clearSearchResults();
                })
                .finally(() => {
                    activeSearchController = null;
                });
        });

        searchForm.addEventListener('reset', event => {
            event.preventDefault();
            resetSearch({ skipFormReset: true });
        });
    }

    if (searchReset) {
        searchReset.addEventListener('click', event => {
            event.preventDefault();
            resetSearch();
        });
    }

    updateSearchMessage(defaultSearchMessage);
})();
