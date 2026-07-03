(() => {
    'use strict';

    const body = document.body;

    const showToast = (message) => {
        const toast = document.querySelector('[data-portal-toast]');
        if (!toast || !message) return;

        toast.textContent = message;
        toast.hidden = false;
        toast.classList.add('is-visible');

        window.setTimeout(() => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => {
                toast.hidden = true;
            }, 180);
        }, 2600);
    };

    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'Continue?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-avatar-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;

            const form = input.closest('form');
            const filename = form?.querySelector('[data-avatar-filename]');
            const preview = document.querySelector('[data-avatar-preview]');

            if (filename) filename.textContent = file.name;

            if (preview && file.type.startsWith('image/')) {
                const oldImage = preview.querySelector('img');
                const image = oldImage || document.createElement('img');
                image.alt = 'Selected profile photo preview';
                image.src = URL.createObjectURL(file);
                if (!oldImage) preview.prepend(image);
            }

            if (form && file.size <= 4 * 1024 * 1024) {
                const submitButton = document.createElement('button');
                submitButton.type = 'submit';
                submitButton.className = 'av-btn av-btn--primary av-btn--sm';
                submitButton.innerHTML = '<span class="av-btn__label"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i> Upload photo</span>';

                const existing = form.querySelector('button[type="submit"]');
                if (existing) existing.remove();
                form.append(submitButton);
            } else if (file.size > 4 * 1024 * 1024) {
                showToast('Choose a photo smaller than 4 MB.');
            }
        });
    });

    document.querySelectorAll('.portal-choice-chip input').forEach((input) => {
        const update = () => {
            input.closest('.portal-choice-chip')?.classList.toggle('is-selected', input.checked);
        };
        update();
        input.addEventListener('change', update);
    });

    document.querySelectorAll('[data-toggle-target]').forEach((toggle) => {
        const target = document.querySelector(toggle.getAttribute('data-toggle-target'));
        const label = toggle.closest('.portal-switch')?.querySelector('em');

        const update = () => {
            if (target instanceof HTMLFieldSetElement) {
                target.disabled = !toggle.checked;
            }
            if (label) label.textContent = toggle.checked ? 'On' : 'Off';
        };

        update();
        toggle.addEventListener('change', update);
    });

    document.querySelectorAll('.portal-choice-fieldset').forEach((fieldset) => {
        const checkboxes = Array.from(fieldset.querySelectorAll('input[type="checkbox"]'));
        const none = checkboxes.find((input) => ['None', 'None known'].includes(input.value));
        if (!none) return;

        checkboxes.forEach((input) => {
            input.addEventListener('change', () => {
                if (input === none && input.checked) {
                    checkboxes.filter((item) => item !== none).forEach((item) => {
                        item.checked = false;
                        item.dispatchEvent(new Event('change', { bubbles: false }));
                    });
                } else if (input !== none && input.checked && none.checked) {
                    none.checked = false;
                    none.dispatchEvent(new Event('change', { bubbles: false }));
                }
            });
        });
    });

    const filterStylists = (branchSelect, stylistSelect) => {
        const branchId = branchSelect.value;
        let currentIsVisible = false;

        Array.from(stylistSelect.options).forEach((option, index) => {
            if (index === 0) return;
            const branchIds = (option.dataset.branchIds || '')
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean);
            const visible = !branchId || branchIds.length === 0 || branchIds.includes(branchId);
            option.hidden = !visible;
            option.disabled = !visible;
            if (option.selected && visible) currentIsVisible = true;
        });

        if (!currentIsVisible && stylistSelect.selectedIndex > 0) {
            stylistSelect.value = '';
        }
    };

    document.querySelectorAll('[data-branch-select]').forEach((branchSelect) => {
        const form = branchSelect.closest('form');
        const stylistSelect = form?.querySelector('[data-stylist-select]');
        if (!(stylistSelect instanceof HTMLSelectElement)) return;

        filterStylists(branchSelect, stylistSelect);
        branchSelect.addEventListener('change', () => filterStylists(branchSelect, stylistSelect));
    });

    document.querySelectorAll('[data-appearance-control]').forEach((control) => {
        const className = control.getAttribute('data-appearance-control');
        if (!className) return;

        control.addEventListener('change', () => {
            body.classList.toggle(className, control.checked);
        });
    });

    const settingsLinks = Array.from(document.querySelectorAll('[data-settings-nav] a'));
    const settingsSections = Array.from(document.querySelectorAll('[data-settings-section]'));

    if ('IntersectionObserver' in window && settingsLinks.length && settingsSections.length) {
        const observer = new IntersectionObserver((entries) => {
            const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

            if (!visible) return;

            settingsLinks.forEach((link) => {
                link.classList.toggle('is-active', link.getAttribute('href') === `#${visible.target.id}`);
            });
        }, {
            rootMargin: '-22% 0px -62% 0px',
            threshold: [0.05, 0.25, 0.5],
        });

        settingsSections.forEach((section) => observer.observe(section));
    }

    document.querySelectorAll('[data-dirty-form]').forEach((form) => {
        form.addEventListener('submit', () => form.classList.remove('has-unsaved-changes'));

        const initial = new FormData(form);
        const initialSignature = new URLSearchParams(Array.from(initial.entries()).map(([key, value]) => [key, String(value)])).toString();

        const mark = () => {
            const current = new FormData(form);
            const signature = new URLSearchParams(Array.from(current.entries()).map(([key, value]) => [key, String(value)])).toString();
            form.classList.toggle('has-unsaved-changes', signature !== initialSignature);
        };

        form.addEventListener('input', mark);
        form.addEventListener('change', mark);
    });

    window.addEventListener('beforeunload', (event) => {
        if (!document.querySelector('form.has-unsaved-changes')) return;
        event.preventDefault();
        event.returnValue = '';
    });
})();
