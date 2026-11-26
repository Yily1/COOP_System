document.addEventListener('DOMContentLoaded', function () {

    // Prevent double-binding of all event listeners below if this
    // script tag ever ends up included more than once on the page.
    if (window.__eqEquipmentJsInitialized) return;
    window.__eqEquipmentJsInitialized = true;

    /* ---------- Escape text before putting it into innerHTML ---------- */
    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showBox(box, message) {
        if (!box) { alert(message); return; }
        box.textContent = message;
        box.classList.add('eq-show');
    }

    var STATUS_LABELS = {
        pending: 'Pending',
        approved: 'Approved',
        ongoing: 'Ongoing',
        overdue: 'Overdue',
        returned: 'Returned'
    };

    // Bookings with these statuses no longer occupy the equipment,
    // so they are NOT shown on the schedule calendar (no dot, no detail).
    var HIDDEN_STATUSES = ['returned', 'rejected', 'cancelled'];

    /* ============================================================
       INPUT RESTRICTIONS
       Add data-filter="letters|alnum|integer|decimal" to any <input>.
       One listener on the document, so it also works for pasted text
       and for inputs inside modals.
       ============================================================ */
    var INPUT_FILTERS = {
        // letters only (plus space . ' -)
        letters: function (v) { return v.replace(/[^\p{L}\s.'\-]/gu, ''); },
        // letters + numbers (plus space . ' -)
        alnum: function (v) { return v.replace(/[^\p{L}0-9\s.'\-]/gu, ''); },
        // digits only
        integer: function (v) { return v.replace(/\D/g, ''); },
        // digits with one dot and up to 2 decimals
        decimal: function (v) {
            v = v.replace(/[^\d.]/g, '');
            var i = v.indexOf('.');
            if (i !== -1) {
                v = v.slice(0, i + 1) + v.slice(i + 1).replace(/\./g, '').slice(0, 2);
            }
            return v;
        }
    };

    document.addEventListener('input', function (e) {
        var el = e.target;
        var type = el && el.dataset ? el.dataset.filter : null;
        if (!type || !INPUT_FILTERS[type]) return;
        var clean = INPUT_FILTERS[type](el.value);
        if (clean !== el.value) el.value = clean;
    });

    /* ---------- Modal open/close helpers ---------- */
    function openModal(modalEl) {
        if (!modalEl) return;
        modalEl.classList.add('eq-open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalEl) {
        if (!modalEl) return;
        modalEl.classList.remove('eq-open');
        document.body.style.overflow = '';
    }

    /* ---------- Custom confirm dialog (replaces native confirm()) ---------- */
    var confirmModal = document.getElementById('confirm-modal-wrap');
    var confirmTitle = document.getElementById('confirm-title');
    var confirmMessage = document.getElementById('confirm-message');
    var confirmOkBtn = document.getElementById('confirm-ok');
    var confirmCancelBtn = document.getElementById('confirm-cancel');
    var confirmCloseBtn = document.getElementById('close-confirm');
    var confirmCallback = null;

    function confirmDialog(opts, onConfirm) {
        if (!confirmModal) {
            if (window.confirm(opts.message || 'Are you sure?')) onConfirm();
            return;
        }
        confirmTitle.textContent = opts.title || 'Are you sure?';
        confirmMessage.textContent = opts.message || '';
        confirmOkBtn.textContent = opts.confirmText || 'Confirm';
        confirmOkBtn.classList.toggle('eq-btn-danger', !!opts.danger);
        confirmOkBtn.classList.toggle('eq-btn-primary', !opts.danger);
        confirmCallback = onConfirm;
        openModal(confirmModal);
    }

    function closeConfirm() {
        confirmCallback = null;
        closeModal(confirmModal);
    }

    if (confirmOkBtn) {
        confirmOkBtn.addEventListener('click', function () {
            var cb = confirmCallback;
            closeConfirm();
            if (cb) cb();
        });
    }
    if (confirmCancelBtn) confirmCancelBtn.addEventListener('click', closeConfirm);
    if (confirmCloseBtn) confirmCloseBtn.addEventListener('click', closeConfirm);
    if (confirmModal) {
        confirmModal.addEventListener('click', function (e) {
            if (e.target === confirmModal) closeConfirm();
        });
    }

    /* ---------- Booking modal open/close ---------- */
    var bookBtn = document.getElementById('book-btn');
    var bookModal = document.getElementById('booking-modal-wrap');
    var closeBookBtn = document.getElementById('close-book');

    if (bookBtn) {
        bookBtn.addEventListener('click', function () {
            openModal(bookModal);
            ensureProvincesLoaded();
        });
    }
    if (closeBookBtn) closeBookBtn.addEventListener('click', function () { closeModal(bookModal); });
    if (bookModal) {
        bookModal.addEventListener('click', function (e) {
            if (e.target === bookModal) closeModal(bookModal);
        });
    }

    /* ---------- Add equipment modal open/close ---------- */
    var addBtn = document.getElementById('add-equipment-btn');
    var addModal = document.getElementById('add-equipment-modal-wrap');
    var closeAddBtn = document.getElementById('close-add-equipment');

    if (addBtn) addBtn.addEventListener('click', function () { openModal(addModal); });
    if (closeAddBtn) closeAddBtn.addEventListener('click', function () { closeModal(addModal); });
    if (addModal) {
        addModal.addEventListener('click', function (e) {
            if (e.target === addModal) closeModal(addModal);
        });
    }

    /* ---------- Edit equipment modal open/close ---------- */
    var editModal = document.getElementById('edit-equipment-modal-wrap');
    var closeEditBtn = document.getElementById('close-edit-equipment');

    if (closeEditBtn) closeEditBtn.addEventListener('click', function () { closeModal(editModal); });
    if (editModal) {
        editModal.addEventListener('click', function (e) {
            if (e.target === editModal) closeModal(editModal);
        });
    }

    /* ---------- Schedule modal open/close ---------- */
    var scheduleBtn = document.getElementById('cal-toggle');
    var scheduleModal = document.getElementById('schedule-modal-wrap');
    var closeScheduleBtn = document.getElementById('close-schedule');
    var calPrevBtn = document.getElementById('cal-prev');
    var calNextBtn = document.getElementById('cal-next');

    // Tracks which month the schedule modal is currently showing.
    var calState = (function () {
        var today = new Date();
        return { year: today.getFullYear(), month: today.getMonth() }; // month is 0-indexed
    })();

    if (scheduleBtn) {
        scheduleBtn.addEventListener('click', function () {
            var today = new Date();
            calState.year = today.getFullYear();
            calState.month = today.getMonth();
            openModal(scheduleModal);
            renderCalendar();
        });
    }
    if (closeScheduleBtn) closeScheduleBtn.addEventListener('click', function () { closeModal(scheduleModal); });
    if (scheduleModal) {
        scheduleModal.addEventListener('click', function (e) {
            if (e.target === scheduleModal) closeModal(scheduleModal);
        });
    }

    if (calPrevBtn) {
        calPrevBtn.addEventListener('click', function () {
            calState.month -= 1;
            if (calState.month < 0) { calState.month = 11; calState.year -= 1; }
            renderCalendar();
        });
    }
    if (calNextBtn) {
        calNextBtn.addEventListener('click', function () {
            calState.month += 1;
            if (calState.month > 11) { calState.month = 0; calState.year += 1; }
            renderCalendar();
        });
    }

    // Esc key closes any open modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal(bookModal);
            closeModal(addModal);
            closeModal(editModal);
            closeModal(scheduleModal);
            closeConfirm();
        }
    });

    function formatDateKey(year, monthIndex, day) {
        var mm = String(monthIndex + 1).padStart(2, '0');
        var dd = String(day).padStart(2, '0');
        return year + '-' + mm + '-' + dd;
    }

    // Lazily creates (once) and returns the "tap a day to see details" panel.
    function getDayDetailPanel() {
        var grid = document.getElementById('cal-grid');
        if (!grid) return null;

        var panel = document.getElementById('cal-day-detail');
        if (panel) return panel;

        panel = document.createElement('div');
        panel.id = 'cal-day-detail';
        panel.style.marginTop = '14px';
        panel.style.padding = '10px 12px';
        panel.style.background = '#FAF7EF';
        panel.style.borderRadius = '8px';
        panel.style.fontSize = '12.5px';
        panel.style.color = '#5B6B57';
        panel.style.minHeight = '20px';
        panel.textContent = 'Tap a day to see its bookings.';

        grid.insertAdjacentElement('afterend', panel);
        return panel;
    }

    function showDayDetail(cell, dateLabel, dayBookings) {
        var panel = getDayDetailPanel();
        if (!panel) return;

        var grid = document.getElementById('cal-grid');
        if (grid) {
            grid.querySelectorAll('.eq-cal-day.eq-selected').forEach(function (el) {
                el.classList.remove('eq-selected');
                el.style.outline = 'none';
            });
        }
        cell.classList.add('eq-selected');
        cell.style.outline = '2px solid #33502F';
        cell.style.outlineOffset = '-2px';

        if (!dayBookings.length) {
            panel.innerHTML = '<strong style="color:#2B3A2A;">' + escapeHtml(dateLabel) + '</strong> — No bookings.';
            return;
        }

        var rows = dayBookings.map(function (b) {
            var locationLine = b.location
                ? ' <span style="color:#5B6B57;">&middot; ' + escapeHtml(b.location) + '</span>'
                : '';
            var statusLine = b.status
                ? ' <span style="color:#C1892B;font-weight:600;">[' + escapeHtml(STATUS_LABELS[b.status] || b.status) + ']</span>'
                : '';
            return '<div style="margin-top:4px;">' +
                '<span style="font-weight:600;color:#223A20;">' + escapeHtml(b.renter_name) + '</span>' +
                ' <span style="color:#5B6B57;">(' + escapeHtml(b.equipment_name) + ')</span>' +
                locationLine +
                statusLine +
                '</div>';
        }).join('');

        panel.innerHTML = '<strong style="color:#2B3A2A;">' + escapeHtml(dateLabel) + '</strong>' + rows;
    }

    // Guards against a slow older response overwriting a newer month.
    var calRequestId = 0;

    function renderCalendar() {
        var grid = document.getElementById('cal-grid');
        var monthLabel = document.getElementById('cal-month-label');
        if (!grid) return;

        var year = calState.year;
        var month = calState.month;
        var firstDay = new Date(year, month, 1).getDay();
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

        if (monthLabel) monthLabel.textContent = monthNames[month] + ' ' + year;

        grid.innerHTML = '<div class="eq-cal-empty-msg">Loading schedule…</div>';

        var panel = document.getElementById('cal-day-detail');
        if (panel) panel.textContent = 'Tap a day to see its bookings.';

        var ajaxBase = typeof EQUIPMENT_AJAX_BASE !== 'undefined' ? EQUIPMENT_AJAX_BASE : '';
        var myRequest = ++calRequestId;

        fetch(ajaxBase + 'get-month-booking.php?year=' + year + '&month=' + (month + 1), {
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (myRequest !== calRequestId) return;

                var bookings = (data.success && data.bookings) ? data.bookings : [];

                bookings = bookings.filter(function (b) {
                    return HIDDEN_STATUSES.indexOf(String(b.status || '').toLowerCase()) === -1;
                });

                var byDate = {};
                bookings.forEach(function (b) {
                    var start = new Date(b.start_date + 'T00:00:00');
                    var end = new Date(b.end_date + 'T00:00:00');
                    if (isNaN(start) || isNaN(end)) return;
                    for (var d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                        var k = formatDateKey(d.getFullYear(), d.getMonth(), d.getDate());
                        if (!byDate[k]) byDate[k] = [];
                        byDate[k].push(b);
                    }
                });

                grid.innerHTML = '';
                for (var i = 0; i < firstDay; i++) {
                    var empty = document.createElement('div');
                    empty.className = 'eq-cal-day eq-empty';
                    grid.appendChild(empty);
                }

                var monthShort = monthNames[month].slice(0, 3);

                for (var dNum = 1; dNum <= daysInMonth; dNum++) {
                    var cell = document.createElement('div');
                    cell.className = 'eq-cal-day';
                    cell.style.cursor = 'pointer';
                    cell.style.display = 'flex';
                    cell.style.flexDirection = 'column';
                    cell.style.alignItems = 'center';
                    cell.style.justifyContent = 'center';

                    var dayNum = document.createElement('span');
                    dayNum.className = 'eq-cal-day-num';
                    dayNum.textContent = dNum;
                    cell.appendChild(dayNum);

                    var key = formatDateKey(year, month, dNum);
                    var dayBookings = byDate[key] || [];

                    if (dayBookings.length > 0) {
                        cell.classList.add('eq-booked');

                        var dot = document.createElement('span');
                        dot.style.width = '5px';
                        dot.style.height = '5px';
                        dot.style.borderRadius = '50%';
                        dot.style.background = '#C1892B';
                        dot.style.marginTop = '3px';
                        dot.style.display = 'block';
                        cell.appendChild(dot);
                    }

                    (function (cell, dNum, dayBookings) {
                        cell.addEventListener('click', function () {
                            showDayDetail(cell, monthShort + ' ' + dNum, dayBookings);
                        });
                    })(cell, dNum, dayBookings);

                    grid.appendChild(cell);
                }

                getDayDetailPanel();
            })
            .catch(function () {
                if (myRequest !== calRequestId) return;
                grid.innerHTML = '<div class="eq-cal-empty-msg">Could not load schedule.</div>';
            });
    }

    /* ============================================================
       LOCATION DROPDOWNS (Province -> City/Municipality -> Barangay)
       Data comes from api/psgc.php (same PSGC proxy idea as the
       member page, but open to both manager and user roles).
       The three selects only help the user pick; the value that is
       sent to the server is the hidden input #location, filled with
       "Barangay, City/Municipality, Province".
       ============================================================ */
    var NCR_VALUE = 'NCR';
    var NCR_REGION_CODE = '130000000';

    var locProvince = document.getElementById('loc-province');
    var locCity = document.getElementById('loc-city');
    var locBarangay = document.getElementById('loc-barangay');
    var locPurok = document.getElementById('loc-purok');
    var locHidden = document.getElementById('location');
    var provincesLoaded = false;
    var provincesLoading = false;

    // Calls api/psgc.php?type=...&code=... and resolves to [{code, name}, ...]
    function psgcGet(type, code) {
        var ajaxBase = typeof EQUIPMENT_AJAX_BASE !== 'undefined' ? EQUIPMENT_AJAX_BASE : '';
        var url = ajaxBase + 'psgc.php?type=' + encodeURIComponent(type) +
            (code ? '&code=' + encodeURIComponent(code) : '');

        return fetch(url, { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'PSGC error');
                return data.data || [];
            });
    }

    function setOptions(select, items, placeholder) {
        if (!select) return;
        select.innerHTML = '';
        var first = document.createElement('option');
        first.value = '';
        first.textContent = placeholder;
        select.appendChild(first);
        items.forEach(function (it) {
            var opt = document.createElement('option');
            opt.value = it.code;
            opt.textContent = it.name;
            select.appendChild(opt);
        });
    }

    function selectedText(select) {
        if (!select) return '';
        var opt = select.selectedOptions[0];
        return opt && opt.value ? opt.textContent : '';
    }

    function updateLocationValue() {
        if (!locHidden) return;
        var purok = locPurok ? locPurok.value.trim().replace(/\s+/g, ' ') : '';
        var b = selectedText(locBarangay);
        var c = selectedText(locCity);
        var p = selectedText(locProvince);
        // Final format: "Purok, Barangay, City/Municipality, Province"
        locHidden.value = (purok && b && c && p) ? [purok, b, c, p].join(', ') : '';
    }

    function locError(message) {
        var box = document.getElementById('booking-error');
        if (box) showBox(box, message);
    }

    function ensureProvincesLoaded() {
        if (!locProvince || provincesLoaded || provincesLoading) return;
        provincesLoading = true;
        psgcGet('provinces_all')
            .then(function (provinces) {
                var items = [{ code: NCR_VALUE, name: 'Metro Manila (NCR)' }].concat(provinces);
                setOptions(locProvince, items, 'Select province');
                provincesLoaded = true;
            })
            .catch(function () {
                locError('Could not load the location list. Please check your internet and reopen this form.');
            })
            .then(function () { provincesLoading = false; });
    }

    if (locProvince && locCity && locBarangay) {
        locProvince.addEventListener('change', function () {
            var code = locProvince.value;
            setOptions(locCity, [], 'Select city/municipality');
            setOptions(locBarangay, [], 'Select barangay');
            locCity.disabled = true;
            locBarangay.disabled = true;
            updateLocationValue();
            if (!code) return;

            var request = code === NCR_VALUE
                ? psgcGet('cities_by_region', NCR_REGION_CODE)
                : psgcGet('cities_by_province', code);

            request
                .then(function (cities) {
                    if (locProvince.value !== code) return; // user changed it meanwhile
                    setOptions(locCity, cities, 'Select city/municipality');
                    locCity.disabled = false;
                })
                .catch(function () { locError('Could not load cities/municipalities. Please try again.'); });
        });

        locCity.addEventListener('change', function () {
            var code = locCity.value;
            setOptions(locBarangay, [], 'Select barangay');
            locBarangay.disabled = true;
            updateLocationValue();
            if (!code) return;

            psgcGet('barangays', code)
                .then(function (barangays) {
                    if (locCity.value !== code) return;
                    setOptions(locBarangay, barangays, 'Select barangay');
                    locBarangay.disabled = false;
                })
                .catch(function () { locError('Could not load barangays. Please try again.'); });
        });

        locBarangay.addEventListener('change', updateLocationValue);
    }

    if (locPurok) locPurok.addEventListener('input', updateLocationValue);

    /* ---------- Live cost calculation in booking form (quantity-based) ---------- */
    var equipmentSelect = document.getElementById('book-equipment');
    var renterTypeSelect = document.getElementById('renter-type');
    var startDateInput = document.getElementById('start-date');
    var endDateInput = document.getElementById('end-date');
    var quantityInput = document.getElementById('quantity');
    var qtyLabel = document.getElementById('qty-label');
    var qtySummaryLabel = document.getElementById('qty-summary-label');
    var sumDays = document.getElementById('sum-days');
    var sumTotal = document.getElementById('sum-total');

    // End date can never be earlier than the start date
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function () {
            endDateInput.min = startDateInput.value || '';
            if (endDateInput.value && endDateInput.value < startDateInput.value) {
                endDateInput.value = startDateInput.value;
            }
        });
    }

    function pluralizeUnit(unit) {
        return unit ? unit + 's' : 'units';
    }

    function recalcCost() {
        if (!equipmentSelect || !quantityInput) return;

        var selected = equipmentSelect.selectedOptions[0];
        var renterType = renterTypeSelect ? renterTypeSelect.value : 'member';
        var rate = renterType === 'nonmember'
            ? parseFloat(selected?.dataset.rateNonmember || 0)
            : parseFloat(selected?.dataset.rateMember || 0);
        var unitLabel = selected?.dataset.unitLabel || 'unit';
        var qty = parseFloat(quantityInput.value || 0);

        var pluralUnit = pluralizeUnit(unitLabel);
        var labelText = 'Number of ' + pluralUnit;
        if (qtyLabel) qtyLabel.textContent = labelText;
        if (qtySummaryLabel) qtySummaryLabel.textContent = labelText;

        var total = qty * rate;
        if (sumDays) sumDays.textContent = qty || 0;
        if (sumTotal) sumTotal.textContent = '\u20B1' + total.toLocaleString();
    }

    [equipmentSelect, renterTypeSelect, quantityInput].forEach(function (el) {
        if (el) el.addEventListener('input', recalcCost);
        if (el) el.addEventListener('change', recalcCost);
    });

    recalcCost();

    // The page's auto-update can replace the equipment options, so recompute then too
    document.addEventListener('eq:refreshed', recalcCost);

    /* ---------- Booking form submit (AJAX) ---------- */
    var bookingForm = document.getElementById('booking-form');
    var errorBox = document.getElementById('booking-error');
    var successBox = document.getElementById('booking-success');

    if (bookingForm) {
        bookingForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (errorBox) errorBox.classList.remove('eq-show');
            if (successBox) successBox.classList.remove('eq-show');

            var startVal = startDateInput ? startDateInput.value : '';
            var endVal = endDateInput ? endDateInput.value : '';
            var renterEl = document.getElementById('renter-name');
            var renterVal = renterEl ? renterEl.value.trim() : '';
            var qtyVal = quantityInput ? parseFloat(quantityInput.value) : 0;

            updateLocationValue();
            var locationVal = locHidden ? locHidden.value.trim() : '';

            if (!locationVal) {
                showBox(errorBox, 'Please complete the farm location (province, city/municipality, barangay, and purok).');
                return;
            }
            if (!startVal || !endVal || new Date(endVal) < new Date(startVal)) {
                showBox(errorBox, 'Please choose a valid date range.');
                return;
            }
            if (!renterVal) {
                showBox(errorBox, 'Please enter the renter name.');
                return;
            }
            if (!/^[\p{L}\s.'\-]+$/u.test(renterVal)) {
                showBox(errorBox, 'Renter name may only contain letters and spaces.');
                return;
            }
            if (!qtyVal || qtyVal <= 0) {
                showBox(errorBox, 'Please enter a valid quantity.');
                return;
            }

            var formData = new FormData(bookingForm);
            var ajaxBase = typeof EQUIPMENT_AJAX_BASE !== 'undefined' ? EQUIPMENT_AJAX_BASE : '';

            fetch(ajaxBase + 'book-equipment.php', {
                method: 'POST',
                body: formData
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        if (successBox) {
                            successBox.textContent = data.message || 'Booking submitted.';
                            successBox.classList.add('eq-show');
                        }
                        setTimeout(function () { window.location.reload(); }, 900);
                    } else {
                        showBox(errorBox, data.message || 'Could not submit booking.');
                    }
                })
                .catch(function () {
                    showBox(errorBox, 'Something went wrong. Please try again.');
                });
        });
    }

    /* ---------- Reusable photo preview wiring (used by both add + edit forms) ---------- */
    function wirePhotoPreview(inputId, dropTextId, previewId, previewImgId) {
        var input = document.getElementById(inputId);
        var dropText = document.getElementById(dropTextId);
        var preview = document.getElementById(previewId);
        var previewImg = document.getElementById(previewImgId);

        if (!input) return;

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;

            var reader = new FileReader();
            reader.onload = function (e) {
                if (previewImg) previewImg.src = e.target.result;
                if (preview) preview.classList.add('eq-show');
                if (dropText) dropText.textContent = file.name;
            };
            reader.readAsDataURL(file);
        });
    }

    wirePhotoPreview('add-photo-input', 'add-photo-drop-text', 'add-photo-preview', 'add-photo-preview-img');
    wirePhotoPreview('edit-photo-input', 'edit-photo-drop-text', 'edit-photo-preview', 'edit-photo-preview-img');

    /* ---------- Shared check for the Add / Edit equipment forms ---------- */
    function checkEquipmentForm(form) {
        var name = form.elements['name'].value.trim();
        var member = form.elements['rate_member'].value.trim();
        var nonmember = form.elements['rate_nonmember'].value.trim();

        if (!name) return 'Equipment name is required.';
        if (!/^[\p{L}0-9\s.'\-]+$/u.test(name)) return 'Equipment name may only contain letters, numbers, and spaces.';
        if (!/^\d+$/.test(member)) return 'Member rate must be a whole number (digits only).';
        if (!/^\d+$/.test(nonmember)) return 'Non-member rate must be a whole number (digits only).';
        return '';
    }

    /* ---------- Add equipment form submit (AJAX) ---------- */
    var addForm = document.getElementById('add-equipment-form');
    var addErrorBox = document.getElementById('add-equipment-error');

    if (addForm) {
        addForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (addErrorBox) addErrorBox.classList.remove('eq-show');

            var problem = checkEquipmentForm(addForm);
            if (problem) { showBox(addErrorBox, problem); return; }

            var formData = new FormData(addForm);
            var ajaxBase = typeof EQUIPMENT_AJAX_BASE !== 'undefined' ? EQUIPMENT_AJAX_BASE : '';

            fetch(ajaxBase + 'add-equipment.php', {
                method: 'POST',
                body: formData
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        showBox(addErrorBox, data.message || 'Could not add equipment.');
                    }
                })
                .catch(function () {
                    showBox(addErrorBox, 'Something went wrong. Please try again.');
                });
        });
    }

    /* ---------- Edit equipment form (modal fields + submit) ---------- */
    var editForm = document.getElementById('edit-equipment-form');
    var editErrorBox = document.getElementById('edit-equipment-error');
    var editIdInput = document.getElementById('edit-equipment-id');
    var editNameInput = document.getElementById('edit-name');
    var editRateMemberInput = document.getElementById('edit-rate-member');
    var editRateNonmemberInput = document.getElementById('edit-rate-nonmember');
    var editUnitSelect = document.getElementById('edit-unit-type');
    var editPhotoPreview = document.getElementById('edit-photo-preview');
    var editPhotoPreviewImg = document.getElementById('edit-photo-preview-img');
    var editPhotoDropText = document.getElementById('edit-photo-drop-text');
    var editPhotoInput = document.getElementById('edit-photo-input');

    function openEditFromCard(card) {
        if (editErrorBox) editErrorBox.classList.remove('eq-show');
        if (editPhotoInput) editPhotoInput.value = '';
        if (editPhotoDropText) editPhotoDropText.textContent = 'Click to change photo';

        if (editIdInput) editIdInput.value = card.dataset.equipmentId || '';
        if (editNameInput) editNameInput.value = card.dataset.name || '';
        if (editRateMemberInput) editRateMemberInput.value = card.dataset.rateMember || '';
        if (editRateNonmemberInput) editRateNonmemberInput.value = card.dataset.rateNonmember || '';
        if (editUnitSelect) editUnitSelect.value = card.dataset.unitType || 'day';

        var photoUrl = card.dataset.photoUrl || '';
        if (photoUrl) {
            if (editPhotoPreviewImg) editPhotoPreviewImg.src = photoUrl;
            if (editPhotoPreview) editPhotoPreview.classList.add('eq-show');
        } else {
            if (editPhotoPreview) editPhotoPreview.classList.remove('eq-show');
            if (editPhotoPreviewImg) editPhotoPreviewImg.src = '';
        }

        openModal(editModal);
    }

    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (editErrorBox) editErrorBox.classList.remove('eq-show');

            var problem = checkEquipmentForm(editForm);
            if (problem) { showBox(editErrorBox, problem); return; }

            var formData = new FormData(editForm);
            var ajaxBase = typeof EQUIPMENT_AJAX_BASE !== 'undefined' ? EQUIPMENT_AJAX_BASE : '';

            fetch(ajaxBase + 'update-equipment.php', {
                method: 'POST',
                body: formData
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        showBox(editErrorBox, data.message || 'Could not update equipment.');
                    }
                })
                .catch(function () {
                    showBox(editErrorBox, 'Something went wrong. Please try again.');
                });
        });
    }

    /* ---------- Booking actions helpers ---------- */
    function rowInfo(btn) {
        var row = btn.closest('tr');
        if (!row || !row.cells || row.cells.length < 2) return '';
        return row.cells[0].textContent.trim() + ' (' + row.cells[1].textContent.trim() + ')';
    }

    function sendBookingRequest(btn, url, body) {
        var row = btn.closest('tr');
        var ajaxBase = typeof EQUIPMENT_AJAX_BASE !== 'undefined' ? EQUIPMENT_AJAX_BASE : '';

        var allBtns = row ? row.querySelectorAll('button') : [btn];
        allBtns.forEach(function (b) { b.disabled = true; });

        fetch(ajaxBase + url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Could not update booking.');
                    allBtns.forEach(function (b) { b.disabled = false; });
                }
            })
            .catch(function () {
                alert('Something went wrong. Please try again.');
                allBtns.forEach(function (b) { b.disabled = false; });
            });
    }

    /* ---------- Delegated clicks (manager only) ----------
       One listener on the document, so these buttons keep working
       after the page's auto-update replaces the cards and table rows. */
    document.addEventListener('click', function (e) {
        var target = e.target;
        if (!target || !target.closest) return;
        var ajaxBase = typeof EQUIPMENT_AJAX_BASE !== 'undefined' ? EQUIPMENT_AJAX_BASE : '';

        // Toggle equipment status (Available / Under repair)
        var statusBtn = target.closest('.eq-status-btn');
        if (statusBtn) {
            fetch(ajaxBase + 'toggle-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'equipment_id=' + encodeURIComponent(statusBtn.dataset.id)
            })
                .then(function (res) { return res.json(); })
                .then(function () { window.location.reload(); })
                .catch(function () { console.error('Could not toggle status.'); });
            return;
        }

        // Edit equipment (pre-fills the modal from the clicked card)
        var editBtn = target.closest('.eq-edit-btn');
        if (editBtn) {
            var card = editBtn.closest('.eq-card');
            if (card) openEditFromCard(card);
            return;
        }

        // Approve
        var approveBtn = target.closest('.eq-approve-btn');
        if (approveBtn) {
            confirmDialog({
                title: 'Approve booking',
                message: 'Approve the booking of ' + rowInfo(approveBtn) + '?',
                confirmText: 'Approve'
            }, function () {
                sendBookingRequest(approveBtn, 'update-booking-status.php',
                    'booking_id=' + encodeURIComponent(approveBtn.dataset.id) + '&action=approve');
            });
            return;
        }

        // Reject
        var rejectBtn = target.closest('.eq-reject-btn');
        if (rejectBtn) {
            confirmDialog({
                title: 'Reject booking',
                message: 'Reject the booking of ' + rowInfo(rejectBtn) + '? This cannot be undone.',
                confirmText: 'Reject',
                danger: true
            }, function () {
                sendBookingRequest(rejectBtn, 'update-booking-status.php',
                    'booking_id=' + encodeURIComponent(rejectBtn.dataset.id) + '&action=reject');
            });
            return;
        }

        // approved -> ongoing (equipment is now actually being used)
        var useBtn = target.closest('.eq-use-btn');
        if (useBtn) {
            confirmDialog({
                title: 'Start use',
                message: 'Mark ' + rowInfo(useBtn) + ' as in use (ongoing)?',
                confirmText: 'Use'
            }, function () {
                sendBookingRequest(useBtn, 'update-booking-status.php',
                    'booking_id=' + encodeURIComponent(useBtn.dataset.id) + '&action=use');
            });
            return;
        }

        // Mark returned
        var returnBtn = target.closest('.eq-return-btn');
        if (returnBtn) {
            confirmDialog({
                title: 'Mark as returned',
                message: 'Mark ' + rowInfo(returnBtn) + ' as returned?',
                confirmText: 'Mark returned'
            }, function () {
                sendBookingRequest(returnBtn, 'mark-returned.php',
                    'booking_id=' + encodeURIComponent(returnBtn.dataset.id));
            });
        }
    });
});