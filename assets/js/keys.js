(function () {
    const searchForm = document.getElementById('keySearchForm');
    const searchInput = document.getElementById('keySearchInput');
    const searchReset = document.getElementById('keySearchReset');
    const searchMessage = document.getElementById('keySearchMessage');
    const searchResults = document.getElementById('keySearchResults');
    const keyModal = document.getElementById('keyModal');
    const keyForm = document.getElementById('keyForm');
    const keyLicenseeId = document.getElementById('keyLicenseeId');
    const keyLicenseeName = document.getElementById('keyLicenseeName');
    const keyGiven = document.getElementById('keyGiven');
    const keyGivenDate = document.getElementById('keyGivenDate');
    const keyOverviewBody = document.getElementById('keyOverviewBody');
    const keyOverviewCount = document.getElementById('keyOverviewCount');
    const keySubmitButton = keyForm ? keyForm.querySelector('button[type="submit"]') : null;
    const defaultSearchMessage = 'Geben Sie einen Namen ein, um die Suche zu starten.';

    let activeSearchController = null;
    let currentResults = [];
    let keyOverviewLicensees = [];
    let currentLicenseeId = null;
    let currentLicensee = null;
    let saveInFlight = false;

    if (!searchForm || !searchInput || !searchResults || !searchMessage || !keyModal || !keyForm || !keyGiven || !keyGivenDate) {
        return;
    }

    function updateSearchMessage(message, variant = 'info') {
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
        searchResults.innerHTML = '';
        searchResults.hidden = true;
    }

    function getTodayDateString() {
        const today = new Date();
        today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
        return today.toISOString().split('T')[0];
    }

    function getDisplayName(licensee) {
        const parts = [];
        if (licensee && licensee.vorname) {
            parts.push(licensee.vorname);
        }
        if (licensee && licensee.nachname) {
            parts.push(licensee.nachname);
        }
        if (parts.length > 0) {
            return parts.join(' ');
        }
        return licensee && licensee.id ? `#${licensee.id}` : 'Unbekannter Lizenznehmer';
    }

    function getTableDisplayName(licensee) {
        const lastName = licensee && licensee.nachname ? String(licensee.nachname).trim() : '';
        const firstName = licensee && licensee.vorname ? String(licensee.vorname).trim() : '';

        if (lastName && firstName) {
            return `${lastName}, ${firstName}`;
        }
        if (lastName) {
            return lastName;
        }
        if (firstName) {
            return firstName;
        }

        return licensee && licensee.id ? `#${licensee.id}` : 'Unbekannter Lizenznehmer';
    }

    function getLicenseYears(licensee) {
        const licenses = Array.isArray(licensee && licensee.licenses) ? licensee.licenses : [];
        const years = Array.from(new Set(licenses
            .map(license => (license && typeof license.jahr === 'number' ? license.jahr : null))
            .filter(year => year !== null)));
        years.sort((a, b) => b - a);
        return years;
    }

    function getAddressText(licensee) {
        const addressParts = [];
        if (licensee && licensee.strasse) {
            addressParts.push(licensee.strasse);
        }

        const cityParts = [];
        if (licensee && licensee.plz) {
            cityParts.push(licensee.plz);
        }
        if (licensee && licensee.ort) {
            cityParts.push(licensee.ort);
        }
        if (cityParts.length > 0) {
            addressParts.push(cityParts.join(' '));
        }

        return addressParts.length > 0 ? addressParts.join(', ') : '–';
    }

    function compareLicensees(a, b) {
        const lastNameCompare = String(a && a.nachname ? a.nachname : '').localeCompare(String(b && b.nachname ? b.nachname : ''), 'de', { sensitivity: 'base' });
        if (lastNameCompare !== 0) {
            return lastNameCompare;
        }

        const firstNameCompare = String(a && a.vorname ? a.vorname : '').localeCompare(String(b && b.vorname ? b.vorname : ''), 'de', { sensitivity: 'base' });
        if (firstNameCompare !== 0) {
            return firstNameCompare;
        }

        return Number(a && a.id ? a.id : 0) - Number(b && b.id ? b.id : 0);
    }

    function buildNormalizedLicensee(licensee) {
        if (!licensee || typeof licensee !== 'object') {
            return null;
        }

        const id = typeof licensee.id === 'number' ? licensee.id : parseInt(licensee.id, 10);
        if (!id) {
            return null;
        }

        const keyDate = licensee.schluessel_ausgegeben_am ? String(licensee.schluessel_ausgegeben_am) : null;
        return {
            ...licensee,
            id,
            schluessel_ausgegeben: !!licensee.schluessel_ausgegeben,
            schluessel_ausgegeben_am: keyDate,
            schluessel_ausgegeben_am_formatted: licensee.schluessel_ausgegeben_am_formatted || '–',
        };
    }

    function readInitialKeyOverviewLicensees() {
        if (!keyOverviewBody) {
            return [];
        }

        return Array.from(keyOverviewBody.querySelectorAll('tr[data-key-licensee]'))
            .map(row => {
                try {
                    return buildNormalizedLicensee(JSON.parse(row.dataset.keyLicensee || '{}'));
                } catch (error) {
                    return null;
                }
            })
            .filter(licensee => !!licensee);
    }

    function createDetailItem(label, value) {
        if (!value) {
            return null;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'licensee-detail';

        const dt = document.createElement('dt');
        dt.textContent = label;
        wrapper.appendChild(dt);

        const dd = document.createElement('dd');
        dd.textContent = value;
        wrapper.appendChild(dd);

        return wrapper;
    }

    function createKeySummary(licensee) {
        const wrapper = document.createElement('div');
        wrapper.className = 'licensee-key-summary';

        const badge = document.createElement('span');
        badge.className = `badge ${licensee && licensee.schluessel_ausgegeben ? 'badge-key-given' : 'badge-key-missing'}`;
        badge.textContent = licensee && licensee.schluessel_ausgegeben ? 'Schlüssel ausgegeben' : 'Kein Schlüssel';
        wrapper.appendChild(badge);

        const dateText = document.createElement('span');
        const formattedDate = licensee && licensee.schluessel_ausgegeben_am_formatted ? licensee.schluessel_ausgegeben_am_formatted : '–';
        dateText.textContent = `Ausgegeben am: ${formattedDate}`;
        wrapper.appendChild(dateText);

        return wrapper;
    }

    function createKeyOverviewRow(licensee) {
        const row = document.createElement('tr');
        row.dataset.keyLicensee = JSON.stringify(licensee);

        const nameCell = document.createElement('td');
        const strong = document.createElement('strong');
        strong.textContent = getTableDisplayName(licensee);
        nameCell.appendChild(strong);
        row.appendChild(nameCell);

        const cardCell = document.createElement('td');
        cardCell.textContent = licensee && licensee.fischerkartennummer ? licensee.fischerkartennummer : '–';
        row.appendChild(cardCell);

        const addressCell = document.createElement('td');
        addressCell.textContent = getAddressText(licensee);
        row.appendChild(addressCell);

        const dateCell = document.createElement('td');
        dateCell.textContent = licensee && licensee.schluessel_ausgegeben_am_formatted ? licensee.schluessel_ausgegeben_am_formatted : '–';
        row.appendChild(dateCell);

        const actionCell = document.createElement('td');
        actionCell.className = 'actions';
        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'primary edit-key';
        editButton.textContent = 'Schlüssel bearbeiten';
        editButton.addEventListener('click', () => {
            openKeyModal(licensee);
        });
        actionCell.appendChild(editButton);
        row.appendChild(actionCell);

        return row;
    }

    function renderKeyOverviewTable() {
        if (!keyOverviewBody) {
            return;
        }

        const sortedLicensees = keyOverviewLicensees
            .filter(licensee => licensee && licensee.schluessel_ausgegeben)
            .slice()
            .sort(compareLicensees);

        keyOverviewBody.innerHTML = '';

        if (sortedLicensees.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.setAttribute('data-empty-row', 'true');

            const emptyCell = document.createElement('td');
            emptyCell.colSpan = 5;
            emptyCell.className = 'empty';
            emptyCell.textContent = 'Aktuell ist kein Schlüssel ausgegeben.';
            emptyRow.appendChild(emptyCell);
            keyOverviewBody.appendChild(emptyRow);
        } else {
            sortedLicensees.forEach(licensee => {
                keyOverviewBody.appendChild(createKeyOverviewRow(licensee));
            });
        }

        if (keyOverviewCount) {
            keyOverviewCount.textContent = String(sortedLicensees.length);
        }
    }

    function renderSearchResults(results) {
        searchResults.innerHTML = '';

        if (!Array.isArray(results) || results.length === 0) {
            searchResults.hidden = true;
            return;
        }

        results.forEach(result => {
            const card = document.createElement('article');
            card.className = 'licensee-card';
            card.dataset.licenseeId = result && result.id ? String(result.id) : '';

            const header = document.createElement('header');
            const title = document.createElement('h4');
            title.textContent = getDisplayName(result);
            header.appendChild(title);

            const meta = document.createElement('span');
            meta.className = 'licensee-card-meta';
            const metaParts = [];
            if (result && result.fischerkartennummer) {
                metaParts.push(`Fischerkartennummer: ${result.fischerkartennummer}`);
            }
            const licenseCount = Array.isArray(result && result.licenses) ? result.licenses.length : 0;
            metaParts.push(licenseCount === 1 ? '1 Lizenz' : `${licenseCount} Lizenzen`);
            meta.textContent = metaParts.join(' · ');
            header.appendChild(meta);

            card.appendChild(header);
            card.appendChild(createKeySummary(result));

            const detailsList = document.createElement('dl');
            detailsList.className = 'licensee-details';

            if (result && result.geburtsdatum_formatted) {
                const birthdateParts = [result.geburtsdatum_formatted];
                if (typeof result.alter === 'number' && Number.isFinite(result.alter)) {
                    birthdateParts.push(`${result.alter} Jahre`);
                }
                const birthdateDetail = createDetailItem('Geburtsdatum', birthdateParts.join(' · '));
                if (birthdateDetail) {
                    detailsList.appendChild(birthdateDetail);
                }
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
            if (cityParts.length > 0) {
                addressParts.push(cityParts.join(' '));
            }
            const addressDetail = createDetailItem('Adresse', addressParts.join(', '));
            if (addressDetail) {
                detailsList.appendChild(addressDetail);
            }

            const phoneDetail = createDetailItem('Telefon', result && result.telefon ? result.telefon : null);
            if (phoneDetail) {
                detailsList.appendChild(phoneDetail);
            }

            const emailDetail = createDetailItem('E-Mail', result && result.email ? result.email : null);
            if (emailDetail) {
                detailsList.appendChild(emailDetail);
            }

            const licenseYears = getLicenseYears(result);
            const licenseYearsDetail = createDetailItem('Lizenzjahre', licenseYears.length > 0 ? licenseYears.join(', ') : '–');
            if (licenseYearsDetail) {
                detailsList.appendChild(licenseYearsDetail);
            }

            if (detailsList.children.length > 0) {
                card.appendChild(detailsList);
            }

            const actions = document.createElement('div');
            actions.className = 'licensee-card-actions';

            const editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'primary';
            editButton.textContent = 'Schlüssel bearbeiten';
            editButton.addEventListener('click', () => {
                openKeyModal(result);
            });
            actions.appendChild(editButton);

            card.appendChild(actions);
            searchResults.appendChild(card);
        });

        searchResults.hidden = false;
    }

    function closeKeyModal() {
        keyModal.hidden = true;
        keyForm.reset();
        currentLicenseeId = null;
        currentLicensee = null;
        saveInFlight = false;
        keyLicenseeId.value = '';
        keyLicenseeName.textContent = '–';
        keyGivenDate.value = '';
        keyGivenDate.disabled = true;
        if (keySubmitButton) {
            keySubmitButton.disabled = false;
        }
    }

    function updateKeyDateState() {
        if (!keyGiven.checked) {
            keyGivenDate.value = '';
            keyGivenDate.disabled = true;
            return;
        }

        keyGivenDate.disabled = false;
        if (!String(keyGivenDate.value || '').trim()) {
            keyGivenDate.value = getTodayDateString();
        }
    }

    function openKeyModal(licensee) {
        currentLicensee = buildNormalizedLicensee(licensee);
        currentLicenseeId = currentLicensee && currentLicensee.id ? currentLicensee.id : null;
        keyLicenseeId.value = currentLicenseeId ? String(currentLicenseeId) : '';
        keyLicenseeName.textContent = getDisplayName(currentLicensee);
        keyGiven.checked = !!(currentLicensee && currentLicensee.schluessel_ausgegeben);
        keyGivenDate.value = currentLicensee && currentLicensee.schluessel_ausgegeben_am ? currentLicensee.schluessel_ausgegeben_am : '';
        updateKeyDateState();
        keyModal.hidden = false;
    }

    function mergeUpdatedLicensee(updatedLicensee) {
        const normalizedUpdated = buildNormalizedLicensee(updatedLicensee);
        if (!normalizedUpdated) {
            return null;
        }

        if (currentLicensee && currentLicensee.id === normalizedUpdated.id) {
            currentLicensee = {
                ...currentLicensee,
                ...normalizedUpdated,
            };
        } else {
            currentLicensee = normalizedUpdated;
        }

        currentResults = currentResults.map(result => {
            if (!result || result.id !== normalizedUpdated.id) {
                return result;
            }

            currentLicensee = {
                ...result,
                ...currentLicensee,
            };

            return {
                ...result,
                ...currentLicensee,
            };
        });

        const overviewIndex = keyOverviewLicensees.findIndex(result => result && result.id === normalizedUpdated.id);
        if (currentLicensee && currentLicensee.schluessel_ausgegeben) {
            if (overviewIndex >= 0) {
                keyOverviewLicensees[overviewIndex] = {
                    ...keyOverviewLicensees[overviewIndex],
                    ...currentLicensee,
                };
            } else {
                keyOverviewLicensees.push(currentLicensee);
            }
        } else if (overviewIndex >= 0) {
            keyOverviewLicensees.splice(overviewIndex, 1);
        }

        renderKeyOverviewTable();
        return currentLicensee;
    }

    function resetSearch(options = {}) {
        const { skipFormReset = false } = options;

        if (activeSearchController) {
            activeSearchController.abort();
            activeSearchController = null;
        }

        closeKeyModal();

        if (!skipFormReset) {
            searchForm.reset();
        }

        currentResults = [];
        clearSearchResults();
        updateSearchMessage(defaultSearchMessage);
        searchInput.value = '';
        searchInput.focus();
    }

    document.querySelectorAll('[data-close]').forEach(button => {
        button.addEventListener('click', () => {
            closeKeyModal();
        });
    });

    keyGiven.addEventListener('change', () => {
        updateKeyDateState();
    });

    keyForm.addEventListener('submit', event => {
        event.preventDefault();

        if (saveInFlight || !currentLicenseeId) {
            return;
        }

        saveInFlight = true;
        if (keySubmitButton) {
            keySubmitButton.disabled = true;
        }

        const payload = {
            licensee_id: currentLicenseeId,
            schluessel_ausgegeben: keyGiven.checked,
            schluessel_ausgegeben_am: keyGiven.checked ? (keyGivenDate.value || '') : '',
        };

        fetch('api.php?action=save_licensee_key', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Netzwerkfehler');
                }
                return response.json();
            })
            .then(data => {
                if (!data || data.success === false) {
                    const message = data && data.message ? data.message : 'Der Schlüsselstatus konnte nicht gespeichert werden.';
                    alert(message);
                    return;
                }

                mergeUpdatedLicensee(data.licensee || null);
                renderSearchResults(currentResults);
                updateSearchMessage(currentResults.length > 0
                    ? `Schlüssel gespeichert. ${currentResults.length} Lizenznehmer gefunden.`
                    : 'Schlüssel gespeichert.');
                closeKeyModal();
            })
            .catch(() => {
                alert('Der Schlüsselstatus konnte nicht gespeichert werden.');
            })
            .finally(() => {
                saveInFlight = false;
                if (keySubmitButton) {
                    keySubmitButton.disabled = false;
                }
            });
    });

    searchForm.addEventListener('submit', event => {
        event.preventDefault();

        const query = searchInput.value.trim();
        if (query.length < 2) {
            currentResults = [];
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
                    currentResults = [];
                    clearSearchResults();
                    updateSearchMessage(message, 'error');
                    return;
                }

                currentResults = Array.isArray(data.results) ? data.results : [];
                if (currentResults.length === 0) {
                    clearSearchResults();
                    updateSearchMessage('Keine Lizenznehmer gefunden.');
                    return;
                }

                renderSearchResults(currentResults);
                updateSearchMessage(`${currentResults.length} Lizenznehmer gefunden.`);
            })
            .catch(error => {
                if (error && error.name === 'AbortError') {
                    return;
                }
                currentResults = [];
                clearSearchResults();
                updateSearchMessage('Die Suche ist fehlgeschlagen.', 'error');
            })
            .finally(() => {
                activeSearchController = null;
            });
    });

    searchForm.addEventListener('reset', event => {
        event.preventDefault();
        resetSearch({ skipFormReset: true });
    });

    if (searchReset) {
        searchReset.addEventListener('click', event => {
            event.preventDefault();
            resetSearch();
        });
    }

    keyOverviewLicensees = readInitialKeyOverviewLicensees();
    renderKeyOverviewTable();
    updateSearchMessage(defaultSearchMessage);
    closeKeyModal();
})();
