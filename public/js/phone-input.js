/**
 * Combine country code + local number into hidden no_hp field.
 */
(function () {
    const normalizeLocal = (value) => {
        let digits = String(value || '').replace(/\D+/g, '');
        if (digits.startsWith('0')) {
            digits = digits.replace(/^0+/, '');
        }
        return digits;
    };

    const syncGroup = (group) => {
        const country = group.querySelector('[data-phone-country]');
        const local = group.querySelector('[data-phone-local]');
        const hidden = group.querySelector('[data-phone-hidden]');

        if (!country || !local || !hidden) {
            return;
        }

        const code = country.value || '+62';
        const localDigits = normalizeLocal(local.value);

        hidden.value = localDigits ? `${code}${localDigits}` : '';
    };

    const bindGroup = (group) => {
        const country = group.querySelector('[data-phone-country]');
        const local = group.querySelector('[data-phone-local]');
        const form = group.closest('form');

        [country, local].forEach((el) => {
            if (!el) return;
            el.addEventListener('input', () => syncGroup(group));
            el.addEventListener('change', () => syncGroup(group));
        });

        if (form) {
            form.addEventListener('submit', () => syncGroup(group));
        }

        syncGroup(group);
    };

    document.querySelectorAll('[data-phone-input]').forEach(bindGroup);
})();
