(function () {
    'use strict';

    const AJAX_BASE = window.RESOURCES_AJAX_BASE;

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
        addForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearError('add-distribution-error');

            const formData = new FormData(addForm);
            const data = Object.fromEntries(formData.entries());
            data.action = 'add';

            if (!data.member_id) {
                showError('add-distribution-error', 'Please select a member.');
                return;
            }

            try {
                await postJSON(AJAX_BASE + 'distribute.php', data);
                window.location.reload();
            } catch (err) {
                showError('add-distribution-error', err.message);
            }
        });
    }

    // ---------- CONFIRM / DECLINE ----------
    document.querySelectorAll('.rd-confirm-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('Confirm this distribution as released?')) return;
            try {
                await postJSON(AJAX_BASE + 'distribute.php', { id: btn.dataset.id, action: 'confirm' });
                window.location.reload();
            } catch (err) {
                alert(err.message);
            }
        });
    });

    document.querySelectorAll('.rd-decline-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('Decline this distribution? It will stay marked as not released.')) return;
            try {
                await postJSON(AJAX_BASE + 'distribute.php', { id: btn.dataset.id, action: 'decline' });
                window.location.reload();
            } catch (err) {
                alert(err.message);
            }
        });
    });
})();