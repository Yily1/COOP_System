(function () {
    'use strict';

    const API_URL = window.RESOURCES_API_URL;
    const LOGIN_URL = window.RESOURCES_LOGIN_URL;

    function openModal(id) { document.getElementById(id).classList.add('rd-open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('rd-open'); }
    function showError(boxId, message) {
        const box = document.getElementById(boxId);
        if (!box) return;
        box.textContent = message;
        box.classList.add('rd-show');
    }
    function clearError(boxId) {
        const box = document.getElementById(boxId);
        if (!box) return;
        box.textContent = '';
        box.classList.remove('rd-show');
    }

    // Generic API helper: sends JSON, handles 401 (session expired),
    // and turns any non-2xx response into an Error with the API's message.
    async function apiRequest(method, url, data) {
        const res = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: data ? JSON.stringify(data) : undefined,
        });

        if (res.status === 401) {
            window.location.href = LOGIN_URL;
            throw new Error('Session expired. Please log in again.');
        }

        let payload;
        try {
            payload = await res.json();
        } catch (e) {
            console.error('Non-JSON response from server. Status:', res.status);
            throw new Error('Unexpected server response.');
        }

        if (!res.ok || payload.success === false) {
            // The PHP API returns { success: false, message: "..." }
            throw new Error(payload.message || payload.error || 'Something went wrong.');
        }
        return payload;
    }

    // ---------- ADD DISTRIBUTION ----------
    const addBtn = document.getElementById('add-distribution-btn');
    if (addBtn) {
        addBtn.addEventListener('click', () => openModal('add-distribution-modal-wrap'));
    }

    const closeAdd = document.getElementById('close-add-distribution');
    if (closeAdd) {
        closeAdd.addEventListener('click', () => closeModal('add-distribution-modal-wrap'));
    }

    const addForm = document.getElementById('add-distribution-form');
    if (addForm) {
        const members     = window.RESOURCES_MEMBERS || [];
        const searchInput = document.getElementById('member-search');
        const clearBtn    = document.getElementById('member-search-clear');
        const suggestBox  = document.getElementById('member-suggest');
        const selectAll   = document.getElementById('member-select-all');
        const chosenWrap  = document.getElementById('member-chosen-wrap');
        const chosenList  = document.getElementById('member-chosen');
        const countEl     = document.getElementById('member-count');

        let picked = null;

        const labelOf = (m) => m.name + ' — ' + m.mid;

        function findMatches(query) {
            const tokens = query.toLowerCase().split(/\s+/).filter(Boolean);
            if (tokens.length === 0) return [];
            return members.filter((m) => {
                const key = (m.name + ' ' + m.mid).toLowerCase();
                return tokens.every((t) => key.includes(t));
            });
        }

        function hideSuggest() {
            if (!suggestBox) return;
            suggestBox.classList.remove('rd-show');
            suggestBox.innerHTML = '';
        }

        function renderSuggest() {
            if (!suggestBox || !searchInput) return;
            const query = searchInput.value.trim();
            if (query === '' || picked) {
                hideSuggest();
                return;
            }

            const results = findMatches(query);
            suggestBox.innerHTML = '';

            if (results.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'rd-suggest-empty';
                empty.textContent = 'No members match your search.';
                suggestBox.appendChild(empty);
            } else {
                results.forEach((m) => {
                    const item = document.createElement('div');
                    item.className = 'rd-suggest-item';
                    item.textContent = labelOf(m);
                    // mousedown (not click) so it fires before the input loses focus
                    item.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        pickMember(m);
                    });
                    suggestBox.appendChild(item);
                });
            }
            suggestBox.classList.add('rd-show');
        }

        function updateClearBtn() {
            if (!clearBtn || !searchInput) return;
            clearBtn.classList.toggle('rd-show', searchInput.value !== '' && !searchInput.disabled);
        }

        function pickMember(m) {
            picked = m;
            searchInput.value = labelOf(m);
            hideSuggest();
            updateClearBtn();
        }

        function clearSearch() {
            picked = null;
            searchInput.value = '';
            hideSuggest();
            updateClearBtn();
            searchInput.focus();
        }

        function renderChosen() {
            if (!chosenWrap || !chosenList || !countEl) return;
            chosenList.innerHTML = '';

            if (!selectAll || !selectAll.checked) {
                chosenWrap.classList.remove('rd-show');
                return;
            }

            members.forEach((m) => {
                const row = document.createElement('div');
                row.className = 'rd-chosen-item';
                row.textContent = labelOf(m);
                chosenList.appendChild(row);
            });
            countEl.textContent = members.length + ' selected';
            chosenWrap.classList.add('rd-show');
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                picked = null;
                updateClearBtn();
                renderSuggest();
            });

            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (!picked) {
                        const results = findMatches(searchInput.value.trim());
                        if (results.length > 0) pickMember(results[0]);
                    }
                } else if (e.key === 'Escape') {
                    hideSuggest();
                }
            });

            searchInput.addEventListener('blur', hideSuggest);
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', clearSearch);
        }

        // "Select all" = every eligible member (members with a crop planting).
        // The search is disabled and cleared while it is checked.
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                if (selectAll.checked) {
                    picked = null;
                    searchInput.value = '';
                    hideSuggest();
                }
                searchInput.disabled = selectAll.checked;
                updateClearBtn();
                renderChosen();
            });
        }

        addForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearError('add-distribution-error');

            let memberIds = [];
            if (selectAll && selectAll.checked) {
                memberIds = members.map((m) => m.id);
            } else if (picked) {
                memberIds = [picked.id];
            }

            if (memberIds.length === 0) {
                showError('add-distribution-error', 'Please search and pick a member, or select all.');
                return;
            }

            const fd = new FormData(addForm);
            const data = {
                action: 'add',
                member_ids: memberIds,
                resource_name: fd.get('resource_name'),
                quantity: fd.get('quantity'),
                distribution_date: fd.get('distribution_date'),
                notes: fd.get('notes'),
            };

            try {
                await apiRequest('POST', API_URL, data);
                window.location.reload();
            } catch (err) {
                showError('add-distribution-error', err.message);
            }
        });
    }

    // ---------- CONFIRM (released) ----------
    document.querySelectorAll('.rd-confirm-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('Confirm this distribution as released?')) return;
            try {
                await apiRequest('POST', API_URL, { action: 'confirm', id: Number(btn.dataset.id) });
                window.location.reload();
            } catch (err) {
                alert(err.message);
            }
        });
    });

    // ---------- DECLINE (not released) ----------
    document.querySelectorAll('.rd-decline-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('Decline this distribution? It will stay marked as not released.')) return;
            try {
                await apiRequest('POST', API_URL, { action: 'decline', id: Number(btn.dataset.id) });
                window.location.reload();
            } catch (err) {
                alert(err.message);
            }
        });
    });
})();