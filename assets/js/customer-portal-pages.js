(() => {
    'use strict';

    const body = document.body;
    const prefersReducedMotion = () =>
        body.classList.contains('portal-reduce-motion') ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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

    const closeTransientPopovers = (except = null) => {
        document.querySelectorAll('.portal-select.is-open, .portal-time-picker.is-open').forEach((element) => {
            if (element === except) return;
            element.dispatchEvent(new CustomEvent('portal:close-popover'));
        });
    };

    /* Fixed save/error notification ------------------------------------------------ */
    document.querySelectorAll('[data-flash-toast]').forEach((toast) => {
        const type = toast.getAttribute('data-flash-type') || 'info';
        const duration = type === 'error' ? 7000 : 3800;
        const timer = toast.querySelector('.portal-flash-toast__timer');
        let timeoutId = 0;
        let remaining = duration;
        let startedAt = performance.now();

        const hide = () => {
            window.clearTimeout(timeoutId);
            toast.classList.add('is-leaving');
            window.setTimeout(() => toast.remove(), prefersReducedMotion() ? 0 : 240);
        };

        const start = () => {
            startedAt = performance.now();
            window.clearTimeout(timeoutId);
            timeoutId = window.setTimeout(hide, remaining);
            if (timer) {
                timer.style.setProperty('--toast-duration', `${remaining}ms`);
                timer.classList.remove('is-running');
                void timer.offsetWidth;
                timer.classList.add('is-running');
            }
        };

        const pause = () => {
            remaining = Math.max(0, remaining - (performance.now() - startedAt));
            window.clearTimeout(timeoutId);
            timer?.classList.remove('is-running');
        };

        toast.querySelector('[data-flash-dismiss]')?.addEventListener('click', hide);
        toast.addEventListener('mouseenter', pause);
        toast.addEventListener('mouseleave', start);
        toast.addEventListener('focusin', pause);
        toast.addEventListener('focusout', start);

        requestAnimationFrame(() => toast.classList.add('is-visible'));
        start();
    });

    /* Confirm actions -------------------------------------------------------------- */
    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'Continue?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    /* Avatar preview --------------------------------------------------------------- */
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

    /* Dark, accessible custom select ---------------------------------------------- */
    const selectInstances = new WeakMap();
    let selectSequence = 0;

    const enhanceSelect = (select) => {
        if (!(select instanceof HTMLSelectElement) || select.dataset.enhancedSelect === 'true') return;

        select.dataset.enhancedSelect = 'true';
        selectSequence += 1;

        const wrapper = document.createElement('div');
        wrapper.className = 'portal-select';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'portal-select__trigger';
        button.setAttribute('role', 'combobox');
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');

        const value = document.createElement('span');
        value.className = 'portal-select__value';

        const chevron = document.createElement('i');
        chevron.className = 'fa-solid fa-chevron-down';
        chevron.setAttribute('aria-hidden', 'true');

        const list = document.createElement('div');
        list.className = 'portal-select__menu';
        list.id = `portalSelectMenu${selectSequence}`;
        list.setAttribute('role', 'listbox');
        list.hidden = true;

        button.setAttribute('aria-controls', list.id);
        button.append(value, chevron);

        select.parentNode?.insertBefore(wrapper, select);
        wrapper.append(select, button, list);
        select.classList.add('portal-native-select');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        let typeahead = '';
        let typeaheadTimer = 0;

        const availableOptions = () => Array.from(select.options).filter((option) => !option.hidden);
        const selectableButtons = () => Array.from(list.querySelectorAll('[role="option"]:not([aria-disabled="true"])'));

        const sync = () => {
            const selected = select.selectedOptions[0] || select.options[0];
            value.textContent = selected?.textContent?.trim() || 'Select';
            wrapper.classList.toggle('has-placeholder', !select.value);
            list.querySelectorAll('[role="option"]').forEach((optionButton) => {
                const selectedState = optionButton.dataset.value === select.value;
                optionButton.classList.toggle('is-selected', selectedState);
                optionButton.setAttribute('aria-selected', selectedState ? 'true' : 'false');
            });
        };

        const render = () => {
            list.replaceChildren();

            availableOptions().forEach((option) => {
                const optionButton = document.createElement('button');
                optionButton.type = 'button';
                optionButton.className = 'portal-select__option';
                optionButton.dataset.value = option.value;
                optionButton.setAttribute('role', 'option');
                optionButton.setAttribute('aria-selected', option.selected ? 'true' : 'false');
                optionButton.setAttribute('aria-disabled', option.disabled ? 'true' : 'false');
                optionButton.disabled = option.disabled;

                const text = document.createElement('span');
                text.textContent = option.textContent?.trim() || '';

                const check = document.createElement('i');
                check.className = 'fa-solid fa-check';
                check.setAttribute('aria-hidden', 'true');

                optionButton.append(text, check);
                list.append(optionButton);
            });

            sync();
        };

        const close = (restoreFocus = false) => {
            if (!wrapper.classList.contains('is-open')) return;
            wrapper.classList.remove('is-open', 'opens-upward');
            button.setAttribute('aria-expanded', 'false');
            list.classList.remove('is-visible');
            window.setTimeout(() => {
                if (!wrapper.classList.contains('is-open')) list.hidden = true;
            }, prefersReducedMotion() ? 0 : 160);
            if (restoreFocus) button.focus({ preventScroll: true });
        };

        const open = () => {
            if (select.disabled) return;
            closeTransientPopovers(wrapper);
            list.hidden = false;
            wrapper.classList.add('is-open');
            button.setAttribute('aria-expanded', 'true');

            const rect = wrapper.getBoundingClientRect();
            const estimatedHeight = Math.min(list.scrollHeight || 300, 330);
            const roomBelow = window.innerHeight - rect.bottom;
            const roomAbove = rect.top;
            wrapper.classList.toggle('opens-upward', roomBelow < estimatedHeight + 20 && roomAbove > roomBelow);

            requestAnimationFrame(() => {
                list.classList.add('is-visible');
                const selectedButton = list.querySelector('.is-selected:not(:disabled)') || selectableButtons()[0];
                selectedButton?.scrollIntoView({ block: 'nearest' });
            });
        };

        const choose = (optionButton) => {
            if (!(optionButton instanceof HTMLButtonElement) || optionButton.disabled) return;
            select.value = optionButton.dataset.value || '';
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            close(true);
        };

        const moveFocus = (direction) => {
            const items = selectableButtons();
            if (!items.length) return;
            const current = document.activeElement;
            const index = items.indexOf(current);
            const nextIndex = index < 0
                ? (direction > 0 ? 0 : items.length - 1)
                : Math.min(items.length - 1, Math.max(0, index + direction));
            items[nextIndex]?.focus({ preventScroll: true });
            items[nextIndex]?.scrollIntoView({ block: 'nearest' });
        };

        const handleTypeahead = (key) => {
            if (!/^[a-z0-9 ]$/i.test(key)) return;
            window.clearTimeout(typeaheadTimer);
            typeahead += key.toLowerCase();
            typeaheadTimer = window.setTimeout(() => { typeahead = ''; }, 650);
            const match = selectableButtons().find((item) => item.textContent?.trim().toLowerCase().startsWith(typeahead));
            match?.focus({ preventScroll: true });
            match?.scrollIntoView({ block: 'nearest' });
        };

        button.addEventListener('click', (event) => {
            event.preventDefault();
            wrapper.classList.contains('is-open') ? close() : open();
        });

        button.addEventListener('keydown', (event) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                if (!wrapper.classList.contains('is-open')) open();
                window.setTimeout(() => moveFocus(event.key === 'ArrowUp' ? -1 : 1), 0);
            } else if (event.key === 'Escape') {
                close();
            } else {
                handleTypeahead(event.key);
            }
        });

        list.addEventListener('click', (event) => {
            choose(event.target.closest('[role="option"]'));
        });

        list.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                moveFocus(event.key === 'ArrowDown' ? 1 : -1);
            } else if (event.key === 'Home' || event.key === 'End') {
                event.preventDefault();
                const items = selectableButtons();
                const item = event.key === 'Home' ? items[0] : items.at(-1);
                item?.focus({ preventScroll: true });
                item?.scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                choose(document.activeElement);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                close(true);
            } else if (event.key === 'Tab') {
                close();
            } else {
                handleTypeahead(event.key);
            }
        });

        wrapper.addEventListener('portal:close-popover', () => close());
        select.addEventListener('change', sync);
        select.addEventListener('portal:select-refresh', render);

        selectInstances.set(select, { render, sync, close });
        render();
    };

    document.querySelectorAll('.portal-field select:not([data-native-select])').forEach(enhanceSelect);

    document.addEventListener('pointerdown', (event) => {
        document.querySelectorAll('.portal-select.is-open, .portal-time-picker.is-open').forEach((element) => {
            if (!element.contains(event.target)) {
                element.dispatchEvent(new CustomEvent('portal:close-popover'));
            }
        });
    });

    /* Choice chips and switches ---------------------------------------------------- */
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

    /* Branch -> stylist filtering -------------------------------------------------- */
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
            stylistSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        stylistSelect.dispatchEvent(new CustomEvent('portal:select-refresh'));
    };

    document.querySelectorAll('[data-branch-select]').forEach((branchSelect) => {
        const form = branchSelect.closest('form');
        const stylistSelect = form?.querySelector('[data-stylist-select]');
        if (!(stylistSelect instanceof HTMLSelectElement)) return;

        filterStylists(branchSelect, stylistSelect);
        branchSelect.addEventListener('change', () => filterStylists(branchSelect, stylistSelect));
    });

    /* 12-hour Apple-inspired salon time wheel ------------------------------------- */
    const pad = (value) => String(value).padStart(2, '0');
    const toMinutes = (value) => {
        if (!/^\d{2}:\d{2}$/.test(value || '')) return null;
        const [hours, minutes] = value.split(':').map(Number);
        return hours * 60 + minutes;
    };
    const toValue = (minutes) => `${pad(Math.floor(minutes / 60) % 24)}:${pad(minutes % 60)}`;
    const toTwelveHour = (value) => {
        const minutes = toMinutes(value);
        if (minutes === null) return 'Select time';
        const hours24 = Math.floor(minutes / 60) % 24;
        const minutesPart = minutes % 60;
        const period = hours24 >= 12 ? 'PM' : 'AM';
        const hours12 = hours24 % 12 || 12;
        return `${hours12}:${pad(minutesPart)} ${period}`;
    };

    let timePickerSequence = 0;

    document.querySelectorAll('[data-salon-time-range]').forEach((range) => {
        const inputs = Array.from(range.querySelectorAll('input[type="time"]'));
        if (inputs.length !== 2) return;

        const form = range.closest('form');
        const daySelect = form?.querySelector('[name="preferred_day_of_week"]');
        const openMinutes = toMinutes(range.dataset.openTime || '10:00') ?? 600;
        const closeMinutes = toMinutes(range.dataset.closeTime || '20:00') ?? 1200;
        const closedDay = Number(range.dataset.closedDay || 1);
        const pickerInstances = [];

        const dayIsClosed = () => Number(daySelect?.value || 0) === closedDay;

        const addNote = () => {
            if (range.nextElementSibling?.classList.contains('time-range-note')) return;
            const note = document.createElement('p');
            note.className = 'time-range-note';
            note.innerHTML = '<i class="fa-regular fa-clock" aria-hidden="true"></i><span>Salon hours: Tuesday-Sunday, 10:00 AM-8:00 PM. Monday closed.</span>';
            range.insertAdjacentElement('afterend', note);
        };

        const isAllowed = (minutes, role) => {
            if (dayIsClosed()) return false;
            if (minutes < openMinutes || minutes > closeMinutes) return false;
            if (role === 'from' && minutes >= closeMinutes) return false;
            if (role === 'to' && minutes <= openMinutes) return false;

            const fromValue = toMinutes(inputs[0].value);
            const toValueMinutes = toMinutes(inputs[1].value);
            if (role === 'from' && toValueMinutes !== null && minutes >= toValueMinutes) return false;
            if (role === 'to' && fromValue !== null && minutes <= fromValue) return false;
            return true;
        };

        const ensureCompleteRange = (changedRole, minutes) => {
            const fromInput = inputs[0];
            const toInput = inputs[1];
            const currentFrom = toMinutes(fromInput.value);
            const currentTo = toMinutes(toInput.value);

            if (changedRole === 'from') {
                if (currentTo === null || minutes >= currentTo) {
                    const suggested = Math.min(closeMinutes, minutes + 60);
                    toInput.value = toValue(suggested > minutes ? suggested : minutes + 30);
                    toInput.dispatchEvent(new Event('input', { bubbles: true }));
                    toInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            } else if (currentFrom === null || minutes <= currentFrom) {
                const suggested = Math.max(openMinutes, minutes - 60);
                fromInput.value = toValue(suggested < minutes ? suggested : minutes - 30);
                fromInput.dispatchEvent(new Event('input', { bubbles: true }));
                fromInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };

        const refreshAll = () => {
            pickerInstances.forEach((instance) => instance.refresh());
            const note = range.nextElementSibling;
            if (note?.classList.contains('time-range-note')) {
                note.classList.toggle('is-closed', dayIsClosed());
                const span = note.querySelector('span');
                if (span) {
                    span.textContent = dayIsClosed()
                        ? 'Aarvella is closed on Monday. Choose Tuesday through Sunday.'
                        : 'Salon hours: Tuesday-Sunday, 10:00 AM-8:00 PM. Monday closed.';
                }
            }
        };

        inputs.forEach((input, index) => {
            timePickerSequence += 1;
            const role = input.dataset.timeRole || (index === 0 ? 'from' : 'to');
            const picker = document.createElement('div');
            picker.className = 'portal-time-picker';

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'portal-time-trigger';
            trigger.setAttribute('aria-haspopup', 'dialog');
            trigger.setAttribute('aria-expanded', 'false');

            const triggerCopy = document.createElement('span');
            triggerCopy.className = 'portal-time-trigger__copy';
            const triggerCaption = document.createElement('small');
            triggerCaption.textContent = role === 'from' ? 'From' : 'To';
            const triggerValue = document.createElement('strong');
            const clock = document.createElement('i');
            clock.className = 'fa-regular fa-clock';
            clock.setAttribute('aria-hidden', 'true');
            triggerCopy.append(triggerCaption, triggerValue);
            trigger.append(triggerCopy, clock);

            const popover = document.createElement('div');
            popover.className = 'portal-time-popover';
            popover.id = `portalTimePopover${timePickerSequence}`;
            popover.setAttribute('role', 'dialog');
            popover.setAttribute('aria-label', `${role === 'from' ? 'Start' : 'End'} time`);
            popover.hidden = true;
            trigger.setAttribute('aria-controls', popover.id);

            const header = document.createElement('div');
            header.className = 'portal-time-popover__header';
            header.innerHTML = `<span>${role === 'from' ? 'Start time' : 'End time'}</span><small>30-minute intervals</small>`;

            const wheelShell = document.createElement('div');
            wheelShell.className = 'portal-time-wheel-shell';
            const wheel = document.createElement('div');
            wheel.className = 'portal-time-wheel';
            wheel.tabIndex = 0;
            wheel.setAttribute('role', 'listbox');
            wheel.setAttribute('aria-label', `${role === 'from' ? 'Start' : 'End'} time options`);
            const selectionBand = document.createElement('span');
            selectionBand.className = 'portal-time-wheel__selection';
            selectionBand.setAttribute('aria-hidden', 'true');
            wheelShell.append(wheel, selectionBand);

            const footer = document.createElement('div');
            footer.className = 'portal-time-popover__footer';
            const hours = document.createElement('span');
            hours.textContent = 'Open 10 AM-8 PM';
            const done = document.createElement('button');
            done.type = 'button';
            done.className = 'portal-time-done';
            done.textContent = 'Done';
            footer.append(hours, done);

            popover.append(header, wheelShell, footer);
            input.parentNode?.insertBefore(picker, input);
            picker.append(input, trigger, popover);
            input.classList.add('portal-native-time');
            input.tabIndex = -1;
            input.setAttribute('aria-hidden', 'true');

            let scrollTimer = 0;

            const items = () => Array.from(wheel.querySelectorAll('.portal-time-option'));
            const enabledItems = () => items().filter((item) => item.getAttribute('aria-disabled') !== 'true');

            const syncTrigger = () => {
                triggerValue.textContent = toTwelveHour(input.value);
                picker.classList.toggle('has-value', Boolean(input.value));
                trigger.disabled = dayIsClosed();
                trigger.setAttribute('aria-disabled', dayIsClosed() ? 'true' : 'false');
            };

            const syncSelection = () => {
                items().forEach((item) => {
                    const selected = item.dataset.value === input.value;
                    item.classList.toggle('is-selected', selected);
                    item.setAttribute('aria-selected', selected ? 'true' : 'false');
                });
                syncTrigger();
            };

            const render = () => {
                const previousScroll = wheel.scrollTop;
                wheel.replaceChildren();

                for (let minutes = 0; minutes < 24 * 60; minutes += 30) {
                    const option = document.createElement('button');
                    const value = toValue(minutes);
                    const allowed = isAllowed(minutes, role);
                    option.type = 'button';
                    option.className = 'portal-time-option';
                    option.dataset.value = value;
                    option.dataset.minutes = String(minutes);
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', value === input.value ? 'true' : 'false');
                    option.setAttribute('aria-disabled', allowed ? 'false' : 'true');
                    option.disabled = !allowed;
                    option.innerHTML = `<span>${toTwelveHour(value)}</span>${allowed ? '' : '<small>Closed</small>'}`;
                    wheel.append(option);
                }

                wheel.scrollTop = previousScroll;
                syncSelection();
            };

            const scrollToCurrent = (smooth = false) => {
                const selected = wheel.querySelector('.is-selected:not(:disabled)') || enabledItems()[0];
                selected?.scrollIntoView({
                    block: 'center',
                    behavior: smooth && !prefersReducedMotion() ? 'smooth' : 'auto',
                });
            };

            const close = (restoreFocus = false) => {
                if (!picker.classList.contains('is-open')) return;
                picker.classList.remove('is-open', 'opens-upward');
                popover.classList.remove('is-visible');
                trigger.setAttribute('aria-expanded', 'false');
                window.setTimeout(() => {
                    if (!picker.classList.contains('is-open')) popover.hidden = true;
                }, prefersReducedMotion() ? 0 : 180);
                if (restoreFocus) trigger.focus({ preventScroll: true });
            };

            const open = () => {
                if (dayIsClosed()) return;
                closeTransientPopovers(picker);
                render();
                popover.hidden = false;
                picker.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');

                const rect = picker.getBoundingClientRect();
                const roomBelow = window.innerHeight - rect.bottom;
                picker.classList.toggle('opens-upward', roomBelow < 390 && rect.top > roomBelow);

                requestAnimationFrame(() => {
                    popover.classList.add('is-visible');
                    scrollToCurrent(false);
                    wheel.focus({ preventScroll: true });
                });
            };

            const selectMinutes = (minutes, fromScroll = false) => {
                if (!isAllowed(minutes, role)) return;
                const value = toValue(minutes);
                input.value = value;
                ensureCompleteRange(role, minutes);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                refreshAll();
                if (!fromScroll) {
                    wheel.querySelector(`[data-value="${value}"]`)?.scrollIntoView({
                        block: 'center',
                        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                    });
                }
            };

            const selectNearestToCenter = () => {
                const shellRect = wheel.getBoundingClientRect();
                const center = shellRect.top + shellRect.height / 2;
                const nearest = enabledItems()
                    .map((item) => ({ item, distance: Math.abs(item.getBoundingClientRect().top + item.offsetHeight / 2 - center) }))
                    .sort((a, b) => a.distance - b.distance)[0]?.item;
                if (nearest) selectMinutes(Number(nearest.dataset.minutes), true);
            };

            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                picker.classList.contains('is-open') ? close() : open();
            });

            trigger.addEventListener('keydown', (event) => {
                if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                    event.preventDefault();
                    open();
                } else if (event.key === 'Escape') {
                    close();
                }
            });

            wheel.addEventListener('click', (event) => {
                const option = event.target.closest('.portal-time-option');
                if (!option || option.disabled) return;
                selectMinutes(Number(option.dataset.minutes));
            });

            wheel.addEventListener('scroll', () => {
                window.clearTimeout(scrollTimer);
                scrollTimer = window.setTimeout(selectNearestToCenter, 110);
            }, { passive: true });

            wheel.addEventListener('keydown', (event) => {
                if (!['ArrowDown', 'ArrowUp', 'Home', 'End', 'Escape', 'Enter'].includes(event.key)) return;
                event.preventDefault();

                if (event.key === 'Escape' || event.key === 'Enter') {
                    close(true);
                    return;
                }

                const enabled = enabledItems();
                if (!enabled.length) return;
                const currentMinutes = toMinutes(input.value);
                let index = enabled.findIndex((item) => Number(item.dataset.minutes) === currentMinutes);
                if (event.key === 'Home') index = 0;
                else if (event.key === 'End') index = enabled.length - 1;
                else index = Math.min(enabled.length - 1, Math.max(0, (index < 0 ? 0 : index) + (event.key === 'ArrowDown' ? 1 : -1)));
                const item = enabled[index];
                selectMinutes(Number(item.dataset.minutes));
            });

            done.addEventListener('click', () => close(true));
            picker.addEventListener('portal:close-popover', () => close());
            input.addEventListener('change', syncSelection);

            pickerInstances.push({ refresh: render, close, input, role });
            render();
        });

        addNote();
        daySelect?.addEventListener('change', refreshAll);
        refreshAll();
    });

    /* Live appearance settings ----------------------------------------------------- */
    document.querySelectorAll('[data-appearance-control]').forEach((control) => {
        const className = control.getAttribute('data-appearance-control');
        if (!className) return;

        control.addEventListener('change', () => {
            body.classList.toggle(className, control.checked);
        });
    });

    /* Smooth settings navigation --------------------------------------------------- */
    const settingsLinks = Array.from(document.querySelectorAll('[data-settings-nav] a'));
    const settingsSections = Array.from(document.querySelectorAll('[data-settings-section]'));

    const activateSettingsLink = (id) => {
        settingsLinks.forEach((link) => {
            const active = link.getAttribute('href') === `#${id}`;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
    };

    settingsLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            const hash = link.getAttribute('href') || '';
            const target = hash.startsWith('#') ? document.querySelector(hash) : null;
            if (!(target instanceof HTMLElement)) return;

            event.preventDefault();
            activateSettingsLink(target.id);
            target.scrollIntoView({
                behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                block: 'start',
            });

            target.classList.remove('is-section-arriving');
            void target.offsetWidth;
            target.classList.add('is-section-arriving');
            window.setTimeout(() => target.classList.remove('is-section-arriving'), prefersReducedMotion() ? 0 : 900);

            history.replaceState(null, '', `#${target.id}`);
        });
    });

    if ('IntersectionObserver' in window && settingsLinks.length && settingsSections.length) {
        const observer = new IntersectionObserver((entries) => {
            const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

            if (!visible) return;
            activateSettingsLink(visible.target.id);
        }, {
            rootMargin: '-20% 0px -64% 0px',
            threshold: [0.05, 0.2, 0.45],
        });

        settingsSections.forEach((section) => observer.observe(section));
    }

    if (window.location.hash && settingsSections.length) {
        const target = document.querySelector(window.location.hash);
        if (target?.matches('[data-settings-section]')) {
            window.setTimeout(() => {
                activateSettingsLink(target.id);
                target.scrollIntoView({ behavior: 'auto', block: 'start' });
            }, 0);
        }
    }

    /* Unsaved changes -------------------------------------------------------------- */
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
