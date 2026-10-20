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
        addForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearError('add-distribution-error');

            const formData = new FormData(addForm);
            const data = Object.fromEntries(formData.entries());
            data.action = 'add'; // tells distributions.php which case to run

            if (!data.member_id) {
                showError('add-distribution-error', 'Please select a member.');
                return;
            }

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