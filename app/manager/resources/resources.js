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

    const modalWrap = document.getElementById('distribute-modal-wrap');
    const memberIdInput = document.getElementById('distribute-member-id');
    const memberLabel = document.getElementById('distribute-member-label');
    const form = document.getElementById('distribute-form');
    const closeBtn = document.getElementById('close-distribute');

    document.querySelectorAll('.rd-distribute-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            memberIdInput.value = btn.dataset.id;
            memberLabel.textContent = 'For: ' + btn.dataset.name;
            clearError('distribute-error');
            form.reset();
            memberIdInput.value = btn.dataset.id;
            openModal('distribute-modal-wrap');
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', () => closeModal('distribute-modal-wrap'));
    }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearError('distribute-error');

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            try {
                await postJSON(AJAX_BASE + 'distribute.php', data);
                window.location.reload();
            } catch (err) {
                showError('distribute-error', err.message);
            }
        });
    }
})();