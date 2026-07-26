/**
 * Admin UI progressive enhancements.
 * These only *decorate* existing controls — field names, values and form
 * submission are never changed, so no server-side behaviour is affected.
 */
document.addEventListener('DOMContentLoaded', () => {
    const main = document.querySelector('.main');
    if (!main) return; // admin pages only

    // ---------------------------------------------------------------
    // Switch toggles for standalone boolean checkboxes.
    //    Multi-select groups use name="foo[]" — those are left as
    //    normal checkboxes. Single booleans become on/off switches.
    // ---------------------------------------------------------------
    main.querySelectorAll('input[type="checkbox"]').forEach((box) => {
        const name = box.getAttribute('name') || '';
        if (name.endsWith('[]')) return;            // multi-select group
        if (box.closest('.check-card')) return;      // styled selectable card, keep custom check
        if (box.closest('.payment-method-card')) return;
        box.classList.add('switch-input');
    });
});
