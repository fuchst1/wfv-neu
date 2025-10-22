(function () {
    const instances = new Map();

    function init() {
        document.querySelectorAll('[data-table-search]').forEach(input => {
            if (instances.has(input)) {
                return;
            }
            const instance = createInstance(input);
            if (instance) {
                instances.set(input, instance);
            }
        });
    }

    function createInstance(input) {
        const selector = input.getAttribute('data-table-search');
        if (!selector) {
            return null;
        }

        const table = document.querySelector(selector);
        if (!table) {
            return null;
        }

        const instance = {
            input,
            table,
            tbody: table.tBodies[0] || null,
            placeholderRows: [],
            dataRows: [],
            noResultsRow: null,
            columnCount: 1,
        };

        instance.refreshRows = function refreshRows() {
            const tbody = table.tBodies[0] || null;
            instance.tbody = tbody;
            instance.placeholderRows = [];
            instance.dataRows = [];
            instance.noResultsRow = null;

            if (!tbody) {
                instance.columnCount = getColumnCount(table, instance.placeholderRows, instance.noResultsRow);
                return;
            }

            const rows = Array.from(tbody.rows);
            instance.placeholderRows = rows.filter(row => row.hasAttribute('data-empty-row'));
            const existingNoResults = rows.find(row => row.getAttribute('data-no-results') === 'true');
            instance.noResultsRow = existingNoResults || createNoResultsRow(table);
            instance.columnCount = getColumnCount(table, instance.placeholderRows, instance.noResultsRow);

            if (instance.noResultsRow) {
                const cell = instance.noResultsRow.querySelector('td');
                if (cell) {
                    cell.colSpan = instance.columnCount;
                }
            }

            instance.dataRows = Array.from(tbody.rows).filter(row => row !== instance.noResultsRow && !row.hasAttribute('data-empty-row'));
        };

        instance.filterRows = function filterRows() {
            if (!instance.tbody) {
                instance.refreshRows();
            }

            if (!instance.tbody) {
                return;
            }

            const query = instance.input.value.trim().toLowerCase();
            const hasQuery = query.length > 0;
            let visibleCount = 0;

            instance.placeholderRows.forEach(row => {
                row.hidden = hasQuery;
            });

            instance.dataRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const matches = !hasQuery || text.includes(query);
                row.hidden = !matches;
                if (matches) {
                    visibleCount++;
                }
            });

            if (instance.noResultsRow) {
                const shouldShow = hasQuery ? visibleCount === 0 : (!hasPlaceholder(instance) && instance.dataRows.length === 0);
                instance.noResultsRow.hidden = !shouldShow;
            }
        };

        instance.input.addEventListener('input', instance.filterRows);
        instance.input.addEventListener('search', instance.filterRows);

        instance.refreshRows();
        instance.filterRows();

        return instance;
    }

    function hasPlaceholder(instance) {
        return instance.placeholderRows && instance.placeholderRows.length > 0;
    }

    function getColumnCount(table, placeholderRows, noResultsRow) {
        const headerRow = table.tHead && table.tHead.rows && table.tHead.rows[0];
        if (headerRow && headerRow.cells.length) {
            return headerRow.cells.length;
        }

        const body = table.tBodies[0];
        if (body) {
            const dataRow = Array.from(body.rows).find(row => row !== noResultsRow && !row.hasAttribute('data-empty-row'));
            if (dataRow && dataRow.cells.length) {
                return dataRow.cells.length;
            }
        }

        if (placeholderRows && placeholderRows[0]) {
            const cell = placeholderRows[0].querySelector('td');
            if (cell && cell.colSpan) {
                return cell.colSpan;
            }
        }

        if (noResultsRow) {
            const cell = noResultsRow.querySelector('td');
            if (cell && cell.colSpan) {
                return cell.colSpan;
            }
        }

        return 1;
    }

    function createNoResultsRow(table) {
        const tbody = table.tBodies[0];
        if (!tbody) {
            return null;
        }

        let row = tbody.querySelector('tr[data-no-results="true"]');
        if (row) {
            return row;
        }

        row = document.createElement('tr');
        row.setAttribute('data-no-results', 'true');
        const cell = document.createElement('td');
        cell.className = 'empty';
        cell.textContent = 'Keine Ergebnisse gefunden.';
        row.appendChild(cell);
        row.hidden = true;
        tbody.appendChild(row);
        return row;
    }

    function resolveInstances(target) {
        if (!target) {
            return Array.from(instances.values());
        }

        if (typeof target === 'string') {
            const table = document.querySelector(target);
            if (!table) {
                return [];
            }
            return Array.from(instances.values()).filter(instance => instance.table === table);
        }

        if (target instanceof Element) {
            return Array.from(instances.values()).filter(instance => instance.table === target);
        }

        if (Array.isArray(target)) {
            return target.reduce((accumulator, item) => {
                resolveInstances(item).forEach(instance => accumulator.push(instance));
                return accumulator;
            }, []);
        }

        return [];
    }

    init();

    window.TableSearch = {
        refresh(target) {
            resolveInstances(target).forEach(instance => {
                instance.refreshRows();
                instance.filterRows();
            });
        }
    };
})();
