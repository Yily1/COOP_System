(function () {
    'use strict';

    const AJAX_BASE = window.CROPS_AJAX_BASE;

    function openModal(id) {
        document.getElementById(id).classList.add('cm-open');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('cm-open');
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
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        let payload;
        try {
            payload = await res.json();
        } catch (e) {
            throw new Error('Unexpected server response.');
        }
        if (!res.ok || payload.success === false) {
            throw new Error(payload.message || 'Something went wrong.');
        }
        return payload;
    }

    // ---------- ADD PLANTING ----------
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

    // ---------- APPROVE / REJECT (manager) ----------
    document.querySelectorAll('.cm-approve-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('Approve this planting?')) return;
            try {
                await postJSON(AJAX_BASE + 'planting-action.php', {
                    action: 'approve',
                    id: btn.dataset.id,
                });
                window.location.reload();
            } catch (err) {
                alert(err.message);
            }
        });
    });

    document.querySelectorAll('.cm-reject-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const reason = prompt('Reason for rejecting this planting (optional):') || null;
            try {
                await postJSON(AJAX_BASE + 'planting-action.php', {
                    action: 'reject',
                    id: btn.dataset.id,
                    reason: reason,
                });
                window.location.reload();
            } catch (err) {
                alert(err.message);
            }
        });
    });

    // ---------- ADVANCE STATUS (owner: growing -> ready -> harvested) ----------
    document.querySelectorAll('.cm-advance-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const next = btn.dataset.next;
            const label = next === 'harvested' ? 'harvested' : 'ready to harvest';
            if (!confirm('Mark this planting as ' + label + '?')) return;
            try {
                await postJSON(AJAX_BASE + 'planting-action.php', {
                    action: 'advance',
                    id: btn.dataset.id,
                    status: next,
                });
                window.location.reload();
            } catch (err) {
                alert(err.message);
            }
        });
    });
})();