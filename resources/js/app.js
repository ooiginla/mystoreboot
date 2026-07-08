import TomSelect from 'tom-select';

/**
 * Admin UI progressive enhancements.
 * These only *decorate* existing controls — field names, values and form
 * submission are never changed, so no server-side behaviour is affected.
 */
document.addEventListener('DOMContentLoaded', () => {
    const main = document.querySelector('.main');
    if (!main) return; // admin pages only

    // ---------------------------------------------------------------
    // 1. Searchable dropdowns (Tom Select) for plain <select> elements.
    //    We deliberately SKIP selects that are driven by custom JS
    //    (inline onchange, data-* attributes on the select or its
    //    options, multiple, or option.hidden filtering) so we never
    //    break existing behaviour.
    // ---------------------------------------------------------------
    const hasDataAttrs = (el) => el && el.dataset && Object.keys(el.dataset).length > 0;

    const shouldEnhance = (select) => {
        if (select.multiple) return false;
        if (select.hasAttribute('onchange')) return false;
        if (select.dataset.noSearch !== undefined) return false;
        if (hasDataAttrs(select)) return false;
        if (select.closest('[data-no-search]')) return false;
        if (Array.from(select.options).some((opt) => hasDataAttrs(opt))) return false;
        // Very short lists don't need a search box but still benefit from the
        // nicer control; enable for everything that passes the safety checks.
        return true;
    };

    main.querySelectorAll('select').forEach((select) => {
        if (!shouldEnhance(select)) return;
        if (select.tomselect) return;

        try {
            new TomSelect(select, {
                allowEmptyOption: true,
                maxOptions: 500,
                controlInput: select.options.length > 6 ? undefined : null, // hide search for tiny lists
                render: {
                    no_results: () => '<div class="no-results" style="padding:9px 12px;color:#64748b">No matches found</div>',
                },
            });
        } catch (e) {
            /* fall back silently to the native select */
        }
    });

    // ---------------------------------------------------------------
    // 2. Switch toggles for standalone boolean checkboxes.
    //    Multi-select groups use name="foo[]" — those are left as
    //    normal checkboxes. Single booleans become on/off switches.
    // ---------------------------------------------------------------
    main.querySelectorAll('input[type="checkbox"]').forEach((box) => {
        const name = box.getAttribute('name') || '';
        if (name.endsWith('[]')) return;            // multi-select group
        if (box.closest('.ts-wrapper')) return;      // inside a tom-select control
        box.classList.add('switch-input');
    });
});
