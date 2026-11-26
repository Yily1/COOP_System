// Shared Philippine address dropdowns (PSGC) para sa Crops (manager at user).
// Gamit:
//   const addr = PHAddress({ api, region, province, city, barangay, purok, preview, onError });
//   addr.load();  addr.reset();  addr.getValues();  addr.validate();
(function () {
    'use strict';

    const RE_PUROK = /[^\p{L}\p{N} .,#\/\-]/gu; // letters + numbers (Purok 3)

    function PHAddress(o) {
        const regionSel = o.region, provinceSel = o.province, citySel = o.city, brgySel = o.barangay;
        const purokInput = o.purok, preview = o.preview;
        const onError = o.onError || function () {};
        let regionsCache = null;
        let hasProvinces = true;

        async function psgc(params) {
            const res = await fetch(o.api + '?' + new URLSearchParams(params), { credentials: 'same-origin' });
            const text = await res.text();
            let json;
            try { json = JSON.parse(text); } catch (e) { throw new Error('Could not load the address list.'); }
            if (!json.success) throw new Error(json.message || 'Could not load the address list.');
            return json.data;
        }

        function setLoading(sel) {
            sel.innerHTML = '';
            const op = document.createElement('option');
            op.value = ''; op.textContent = 'Loading...';
            sel.appendChild(op);
            sel.disabled = true;
        }

        function fill(sel, items, label) {
            sel.innerHTML = '';
            const first = document.createElement('option');
            first.value = ''; first.textContent = '-- Select ' + label + ' --';
            sel.appendChild(first);
            items.forEach(function (i) {
                const op = document.createElement('option');
                op.value = i.code; op.textContent = i.name;
                sel.appendChild(op);
            });
            sel.disabled = items.length === 0;
        }

        function selName(sel) {
            const op = sel.options[sel.selectedIndex];
            return sel.value && op ? op.textContent : '';
        }

        function builtAddress() {
            const bn = selName(brgySel);
            const brgy = bn ? (/^(brgy|barangay)\b/i.test(bn) ? bn : 'Brgy. ' + bn) : '';
            return [purokInput.value.trim(), brgy, selName(citySel), selName(provinceSel), selName(regionSel)]
                .filter(Boolean).join(', ');
        }

        function updateAddress() {
            if (preview) preview.textContent = builtAddress() || '\u2014';
        }

        async function loadCities() {
            setLoading(citySel);
            const list = hasProvinces
                ? await psgc({ type: 'cities_by_province', code: provinceSel.value })
                : await psgc({ type: 'cities_by_region', code: regionSel.value });
            fill(citySel, list, 'Municipality / City');
        }

        regionSel.addEventListener('change', async function () {
            hasProvinces = true;
            fill(provinceSel, [], 'Province');
            fill(citySel, [], 'Municipality / City');
            fill(brgySel, [], 'Barangay');
            updateAddress();
            if (!regionSel.value) return;
            try {
                setLoading(provinceSel);
                const list = await psgc({ type: 'provinces', code: regionSel.value });
                hasProvinces = list.length > 0;
                fill(provinceSel, list, 'Province');
                if (!hasProvinces) {
                    provinceSel.options[0].textContent = '-- No province for this region --';
                    await loadCities();
                }
            } catch (e) {
                fill(provinceSel, [], 'Province');
                onError(e.message);
            }
            updateAddress();
        });

        provinceSel.addEventListener('change', async function () {
            fill(citySel, [], 'Municipality / City');
            fill(brgySel, [], 'Barangay');
            updateAddress();
            if (!provinceSel.value) return;
            try { await loadCities(); } catch (e) { fill(citySel, [], 'Municipality / City'); onError(e.message); }
            updateAddress();
        });

        citySel.addEventListener('change', async function () {
            fill(brgySel, [], 'Barangay');
            updateAddress();
            if (!citySel.value) return;
            try {
                setLoading(brgySel);
                fill(brgySel, await psgc({ type: 'barangays', code: citySel.value }), 'Barangay');
            } catch (e) {
                fill(brgySel, [], 'Barangay');
                onError(e.message);
            }
            updateAddress();
        });

        brgySel.addEventListener('change', updateAddress);

        purokInput.addEventListener('input', function () {
            const v = purokInput.value.replace(RE_PUROK, '');
            if (v !== purokInput.value) purokInput.value = v;
            updateAddress();
        });

        async function load() {
            try {
                if (!regionsCache) {
                    setLoading(regionSel);
                    regionsCache = await psgc({ type: 'regions' });
                }
                fill(regionSel, regionsCache, 'Region');
            } catch (e) {
                fill(regionSel, [], 'Region');
                onError(e.message);
            }
        }

        function reset() {
            hasProvinces = true;
            purokInput.value = '';
            fill(regionSel, regionsCache || [], 'Region');
            fill(provinceSel, [], 'Province');
            fill(citySel, [], 'Municipality / City');
            fill(brgySel, [], 'Barangay');
            updateAddress();
        }

        function getValues() {
            return {
                region_code: regionSel.value,
                province_code: provinceSel.value,
                city_code: citySel.value,
                barangay_code: brgySel.value,
                region_name: selName(regionSel),
                province_name: selName(provinceSel),
                city_name: selName(citySel),
                barangay_name: selName(brgySel),
                purok: purokInput.value.trim()
            };
        }

        function validate() {
            const errs = [];
            if (!regionSel.value) errs.push('Region is required.');
            if (hasProvinces && !provinceSel.value) errs.push('Province is required.');
            if (!citySel.value) errs.push('Municipality / City is required.');
            if (!brgySel.value) errs.push('Barangay is required.');
            if (!purokInput.value.trim()) errs.push('Purok / Street is required.');
            return errs;
        }

        reset();
        return { load: load, reset: reset, getValues: getValues, validate: validate, updateAddress: updateAddress };
    }

    // Numbers lang, hanggang 4 digits + 2 decimals (para sa Area)
    PHAddress.bindDecimal = function (el) {
        el.addEventListener('input', function () {
            let v = el.value.replace(/[^0-9.]/g, '');
            const parts = v.split('.');
            const whole = parts[0].slice(0, 4);
            v = parts.length > 1 ? whole + '.' + parts.slice(1).join('').slice(0, 2) : whole;
            if (v !== el.value) el.value = v;
        });
    };

    window.PHAddress = PHAddress;
})();