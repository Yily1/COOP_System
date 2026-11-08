(function () {
    'use strict';

    const AJAX_BASE = window.CROPS_AJAX_BASE;

    function openModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('cm-open');
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('cm-open');
    }
    function showError(boxId, message) {
        const box = document.getElementById(boxId);
        if (!box) return;
        box.textContent = message;
        box.classList.add('cm-show');
    }
    function clearError(boxId) {
        const box = document.getElementById(boxId);
        if (!box) return;
        box.textContent = '';
        box.classList.remove('cm-show');
    }

    async function postJSON(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        const rawText = await res.text();
        let payload;
        try {
            payload = JSON.parse(rawText);
        } catch (e) {
            console.error('Non-JSON response:', rawText);
            throw new Error('Unexpected server response. Check the console (F12).');
        }
        if (!res.ok || payload.success === false) {
            throw new Error(payload.message || 'Something went wrong.');
        }
        return payload;
    }

    console.log('crops.js loaded (delegation version)');

    // ---------- ADD PLANTING (user) ----------
    const addBtn = document.getElementById('add-planting-btn');
    if (addBtn) {
        addBtn.addEventListener('click', () => openModal('add-planting-modal-wrap'));
    }

    const closeAdd = document.getElementById('close-add-planting');
    if (closeAdd) {
        closeAdd.addEventListener('click', () => closeModal('add-planting-modal-wrap'));
    }

    const addForm = document.getElementById('add-planting-form');
    if (addForm) {
        addForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearError('add-planting-error');

            const formData = new FormData(addForm);
            const data = Object.fromEntries(formData.entries());

            if (new Date(data.expected_harvest_date) < new Date(data.planting_date)) {
                showError('add-planting-error', 'Expected harvest date cannot be before the planting date.');
                return;
            }

            try {
                await postJSON(AJAX_BASE + 'add-planting.php', data);
                window.location.reload();
            } catch (err) {
                showError('add-planting-error', err.message);
            }
        });
    }

    // ---------- TABLE ACTIONS (event delegation) ----------
    // ISANG listener sa document. Hindi ito nawawala kapag pinalitan ng
    // auto-update ang laman ng table.
    let actionBusy = false;

    document.addEventListener('click', async function (e) {
        const target = e.target;
        if (!target || !target.closest) return;

        // APPROVE (manager)
        const approveBtn = target.closest('.cm-approve-btn');
        if (approveBtn) {
            console.log('Approve clicked, id =', approveBtn.dataset.id);
            if (actionBusy) return;
            if (!confirm('Approve this planting?')) return;
            actionBusy = true;
            approveBtn.disabled = true;
            try {
                await postJSON(AJAX_BASE + 'planting-action.php', {
                    action: 'approve',
                    id: approveBtn.dataset.id,
                });
                window.location.reload();
            } catch (err) {
                alert(err.message);
                approveBtn.disabled = false;
                actionBusy = false;
            }
            return;
        }

        // REJECT (manager)
        const rejectBtn = target.closest('.cm-reject-btn');
        if (rejectBtn) {
            console.log('Reject clicked, id =', rejectBtn.dataset.id);
            if (actionBusy) return;
            const reason = prompt('Reason for rejecting this planting (optional):');
            if (reason === null) return; // Cancel = huwag i-reject
            actionBusy = true;
            rejectBtn.disabled = true;
            try {
                await postJSON(AJAX_BASE + 'planting-action.php', {
                    action: 'reject',
                    id: rejectBtn.dataset.id,
                    reason: reason || null,
                });
                window.location.reload();
            } catch (err) {
                alert(err.message);
                rejectBtn.disabled = false;
                actionBusy = false;
            }
            return;
        }

        // ADVANCE STATUS (owner: growing -> ready -> harvested)
        const advanceBtn = target.closest('.cm-advance-btn');
        if (advanceBtn) {
            console.log('Advance clicked, id =', advanceBtn.dataset.id);
            if (actionBusy) return;
            const next = advanceBtn.dataset.next;
            const label = next === 'harvested' ? 'harvested' : 'ready to harvest';
            if (!confirm('Mark this planting as ' + label + '?')) return;
            actionBusy = true;
            advanceBtn.disabled = true;
            try {
                await postJSON(AJAX_BASE + 'planting-action.php', {
                    action: 'advance',
                    id: advanceBtn.dataset.id,
                    status: next,
                });
                window.location.reload();
            } catch (err) {
                alert(err.message);
                advanceBtn.disabled = false;
                actionBusy = false;
            }
            return;
        }
    });
})();