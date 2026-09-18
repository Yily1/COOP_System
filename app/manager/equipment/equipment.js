document.addEventListener('DOMContentLoaded', function () {

        // Prevent double-binding of all event listeners below if this
        // script tag ever ends up included more than once on the page.
        if (window.__eqEquipmentJsInitialized) return;
        window.__eqEquipmentJsInitialized = true;

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

        /* ---------- Booking modal open/close ---------- */
        var bookBtn = document.getElementById('book-btn');
        var bookModal = document.getElementById('booking-modal-wrap');
        var closeBookBtn = document.getElementById('close-book');

        if (bookBtn) bookBtn.addEventListener('click', function () { openModal(bookModal); });
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
            }
        });

        function formatDateKey(year, monthIndex, day) {
            var mm = String(monthIndex + 1).padStart(2, '0');
            var dd = String(day).padStart(2, '0');
            return year + '-' + mm + '-' + dd;
        }

        // Lazily creates (once) and returns the "tap a day to see details"
        // panel that sits below the calendar grid. Built at runtime so no
        // markup changes are needed in equipment.php (manager or user).
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

            // Clear the previously-selected cell's highlight.
            var grid = document.getElementById('cal-grid');
            if (grid) {
                grid.querySelectorAll('.eq-cal-day.eq-selected').forEach(function (el) {
                    el.classList.remove('eq-selected');
                });
            }
            cell.classList.add('eq-selected');
            cell.style.outline = '2px solid #33502F';
            cell.style.outlineOffset = '-2px';

            if (!dayBookings.length) {
                panel.innerHTML = '<strong style="color:#2B3A2A;">' + dateLabel + '</strong> — No bookings.';
                return;
            }

            var rows = dayBookings.map(function (b) {
                return '<div style="margin-top:4px;">' +
                    '<span style="font-weight:600;color:#223A20;">' + b.renter_name + '</span>' +
                    ' <span style="color:#5B6B57;">(' + b.equipment_name + ')</span>' +
                    '</div>';
            }).join('');

            panel.innerHTML = '<strong style="color:#2B3A2A;">' + dateLabel + '</strong>' + rows;
        }

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

            fetch(ajaxBase + 'get-month-booking.php?year=' + year + '&month=' + (month + 1))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    var bookings = (data.success && data.bookings) ? data.bookings : [];

                    // Build a map of date -> list of bookings covering that date,
                    // so each day cell only has to look up its own key.
                    var byDate = {};
                    bookings.forEach(function (b) {
                        var start = new Date(b.start_date + 'T00:00:00');
                        var end = new Date(b.end_date + 'T00:00:00');
                        for (var d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                            var key = formatDateKey(d.getFullYear(), d.getMonth(), d.getDate());
                            if (!byDate[key]) byDate[key] = [];
                            byDate[key].push(b);
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
                    grid.innerHTML = '<div class="eq-cal-empty-msg">Could not load schedule.</div>';
                });
        }

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
                var renterVal = document.getElementById('renter-name')?.value.trim();
                var qtyVal = quantityInput ? parseFloat(quantityInput.value) : 0;

                if (!startVal || !endVal || new Date(endVal) < new Date(startVal)) {
                    if (errorBox) {
                        errorBox.textContent = 'Please choose a valid date range.';
                        errorBox.classList.add('eq-show');
                    }
                    return;
                }
                if (!renterVal) {
                    if (errorBox) {
                        errorBox.textContent = 'Please enter the renter name.';
                        errorBox.classList.add('eq-show');
                    }
                    return;
                }
                if (!qtyVal || qtyVal <= 0) {
                    if (errorBox) {
                        errorBox.textContent = 'Please enter a valid quantity.';
                        errorBox.classList.add('eq-show');
                    }
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
                            if (errorBox) {
                                errorBox.textContent = data.message || 'Could not submit booking.';
                                errorBox.classList.add('eq-show');
                            }
                        }
                    })
                    .catch(function () {
                        if (errorBox) {
                            errorBox.textContent = 'Something went wrong. Please try again.';
                            errorBox.classList.add('eq-show');
                        }
                    });
            });
        }

        /* ---------- Toggle equipment status (manager only) ---------- */
        document.querySelectorAll('.eq-status-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.dataset.id;
                var ajaxBase = typeof EQUIPMENT_AJAX_BASE !== 'undefined' ? EQUIPMENT_AJAX_BASE : '';

                fetch(ajaxBase + 'toggle-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'equipment_id=' + encodeURIComponent(id)
                })
                    .then(function (res) { return res.json(); })
                    .then(function () { window.location.reload(); })
                    .catch(function () { console.error('Could not toggle status.'); });
            });
        });

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

        /* ---------- Add equipment form submit (AJAX) ---------- */
        var addForm = document.getElementById('add-equipment-form');
        var addErrorBox = document.getElementById('add-equipment-error');

        if (addForm) {
            addForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (addErrorBox) addErrorBox.classList.remove('eq-show');

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
                            if (addErrorBox) {
                                addErrorBox.textContent = data.message || 'Could not add equipment.';
                                addErrorBox.classList.add('eq-show');
                            } else {
                                alert(data.message || 'Could not add equipment.');
                            }
                        }
                    })
                    .catch(function () {
                        if (addErrorBox) {
                            addErrorBox.textContent = 'Something went wrong. Please try again.';
                            addErrorBox.classList.add('eq-show');
                        } else {
                            alert('Something went wrong. Please try again.');
                        }
                    });
            });
        }

        /* ---------- Edit equipment: open modal pre-filled from the clicked card ---------- */
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

        document.querySelectorAll('.eq-edit-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('.eq-card');
                if (!card) return;

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
            });
        });

        /* ---------- Edit equipment form submit (AJAX) ---------- */
        if (editForm) {
            editForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (editErrorBox) editErrorBox.classList.remove('eq-show');

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
                            if (editErrorBox) {
                                editErrorBox.textContent = data.message || 'Could not update equipment.';
                                editErrorBox.classList.add('eq-show');
                            } else {
                                alert(data.message || 'Could not update equipment.');
                            }
                        }
                    })
                    .catch(function () {
                        if (editErrorBox) {
                            editErrorBox.textContent = 'Something went wrong. Please try again.';
                            editErrorBox.classList.add('eq-show');
                        } else {
                            alert('Something went wrong. Please try again.');
                        }
                    });
            });
        }

        /* ---------- Approve / reject pending bookings (manager only) ---------- */
        function handleBookingAction(action) {
            return function () {
                var btn = this;
                var id = btn.dataset.id;
                var row = btn.closest('tr');
                var ajaxBase = typeof EQUIPMENT_AJAX_BASE !== 'undefined' ? EQUIPMENT_AJAX_BASE : '';

                var allBtns = row ? row.querySelectorAll('button') : [btn];
                allBtns.forEach(function (b) { b.disabled = true; });

                fetch(ajaxBase + 'update-booking-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'booking_id=' + encodeURIComponent(id) + '&action=' + encodeURIComponent(action)
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
            };
        }

        document.querySelectorAll('.eq-approve-btn').forEach(function (btn) {
            btn.addEventListener('click', handleBookingAction('approve'));
        });
        document.querySelectorAll('.eq-reject-btn').forEach(function (btn) {
            btn.addEventListener('click', handleBookingAction('reject'));
        });
    });