const SIDEBAR_STORAGE_KEY = 'vigg-nexus-sidebar-collapsed';
const SIDEBAR_MINI_STORAGE_KEY = 'vigg-nexus-sidebar-mini-collapsed';
const THEME_STORAGE_KEY = 'vigg-nexus-theme';

const body = document.body;
const rootElement = document.documentElement;
const copyToast = document.querySelector('[data-copy-toast]');
let tooltipElement = null;
let activeTooltipTarget = null;

document.querySelectorAll('.flash-success').forEach((flash) => {
    window.setTimeout(() => {
        flash.classList.add('is-dismissing');

        window.setTimeout(() => {
            flash.remove();
        }, 180);
    }, 2000);
});

function applySidebarState(collapsed) {
    body.classList.toggle('sidebar-collapsed', collapsed);

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.textContent = collapsed ? 'Mostrar menu' : 'Ocultar menu';
        button.setAttribute('aria-expanded', String(!collapsed));
    });
}

function applySidebarMiniState(collapsed) {
    body.classList.toggle('sidebar-mini-collapsed', collapsed);

    document.querySelectorAll('[data-sidebar-mini-toggle]').forEach((button) => {
        button.classList.toggle('is-collapsed', collapsed);
        button.setAttribute('aria-expanded', String(!collapsed));
        button.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Recolher menu');
        setElementTooltip(button, collapsed ? 'Expandir menu' : 'Recolher menu');
    });
}

function applyThemeState(isDarkTheme) {
    rootElement.classList.toggle('theme-dark', isDarkTheme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.classList.toggle('is-dark', isDarkTheme);
        button.setAttribute('aria-checked', String(isDarkTheme));
        button.setAttribute('aria-label', isDarkTheme ? 'Ativar tema claro' : 'Ativar tema escuro');
        setElementTooltip(button, isDarkTheme ? 'Ativar tema claro' : 'Ativar tema escuro');
    });
}

function showCopyToast(message, anchor) {
    if (!copyToast) {
        return;
    }

    copyToast.textContent = message;
    copyToast.classList.add('is-visible');

    if (anchor) {
        const rect = anchor.getBoundingClientRect();
        const top = rect.top > 56 ? rect.top - 42 : rect.bottom + 10;

        copyToast.style.top = `${top}px`;
        copyToast.style.left = `${Math.min(rect.left, window.innerWidth - 180)}px`;
    }

    window.clearTimeout(showCopyToast.timeoutId);
    showCopyToast.timeoutId = window.setTimeout(() => {
        copyToast.classList.remove('is-visible');
    }, 1400);
}

showCopyToast.timeoutId = 0;

function ensureTooltipElement() {
    if (tooltipElement instanceof HTMLDivElement && document.body.contains(tooltipElement)) {
        return tooltipElement;
    }

    tooltipElement = document.createElement('div');
    tooltipElement.className = 'ui-tooltip';
    tooltipElement.hidden = true;
    tooltipElement.setAttribute('role', 'tooltip');
    document.body.appendChild(tooltipElement);

    return tooltipElement;
}

function setElementTooltip(element, text) {
    if (!(element instanceof HTMLElement)) {
        return;
    }

    const tooltipText = String(text || '').trim();
    element.removeAttribute('title');

    if (tooltipText === '') {
        element.removeAttribute('data-ui-tooltip');

        if (activeTooltipTarget === element) {
            hideUiTooltip();
        }

        return;
    }

    element.setAttribute('data-ui-tooltip', tooltipText);

    if (activeTooltipTarget === element) {
        showUiTooltip(element);
    }
}

function syncCustomTooltips(root = document) {
    if (!root || typeof root.querySelectorAll !== 'function') {
        return;
    }

    root.querySelectorAll('[title]').forEach((element) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        const title = String(element.getAttribute('title') || '').trim();

        if (title !== '' && !element.hasAttribute('data-ui-tooltip')) {
            element.setAttribute('data-ui-tooltip', title);
        }

        element.removeAttribute('title');
    });
}

function tooltipTargetFromNode(node) {
    return node instanceof Element ? node.closest('[data-ui-tooltip]') : null;
}

function hideUiTooltip() {
    if (!(tooltipElement instanceof HTMLDivElement)) {
        activeTooltipTarget = null;
        return;
    }

    tooltipElement.classList.remove('is-visible');
    tooltipElement.hidden = true;
    tooltipElement.textContent = '';
    tooltipElement.removeAttribute('data-position');
    activeTooltipTarget = null;
}

function positionUiTooltip(target) {
    if (!(target instanceof HTMLElement) || !target.isConnected) {
        hideUiTooltip();
        return;
    }

    const tooltip = ensureTooltipElement();
    const targetRect = target.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    const margin = 10;
    const offset = 12;
    let top = targetRect.top - tooltipRect.height - offset;
    let position = 'top';

    if (top < margin) {
        top = targetRect.bottom + offset;
        position = 'bottom';
    }

    let left = targetRect.left + ((targetRect.width - tooltipRect.width) / 2);
    left = Math.max(margin, Math.min(left, window.innerWidth - tooltipRect.width - margin));

    tooltip.dataset.position = position;
    tooltip.style.top = `${Math.round(top)}px`;
    tooltip.style.left = `${Math.round(left)}px`;
}

function showUiTooltip(target) {
    if (!(target instanceof HTMLElement)) {
        return;
    }

    const tooltipText = String(target.getAttribute('data-ui-tooltip') || '').trim();

    if (tooltipText === '') {
        hideUiTooltip();
        return;
    }

    const tooltip = ensureTooltipElement();
    tooltip.textContent = tooltipText;
    tooltip.hidden = false;
    tooltip.classList.add('is-visible');
    activeTooltipTarget = target;
    positionUiTooltip(target);
}

function setupCustomTooltips() {
    syncCustomTooltips();

    document.addEventListener('mouseover', (event) => {
        const target = tooltipTargetFromNode(event.target);
        const previousTarget = tooltipTargetFromNode(event.relatedTarget);

        if (!(target instanceof HTMLElement) || target === previousTarget) {
            return;
        }

        showUiTooltip(target);
    });

    document.addEventListener('mouseout', (event) => {
        const target = tooltipTargetFromNode(event.target);
        const nextTarget = tooltipTargetFromNode(event.relatedTarget);

        if (!(target instanceof HTMLElement) || target === nextTarget) {
            return;
        }

        if (activeTooltipTarget === target) {
            hideUiTooltip();
        }
    });

    document.addEventListener('focusin', (event) => {
        const target = tooltipTargetFromNode(event.target);

        if (target instanceof HTMLElement) {
            showUiTooltip(target);
        }
    });

    document.addEventListener('focusout', (event) => {
        const target = tooltipTargetFromNode(event.target);

        if (target instanceof HTMLElement && activeTooltipTarget === target) {
            hideUiTooltip();
        }
    });

    document.addEventListener('click', () => {
        hideUiTooltip();
    }, true);

    window.addEventListener('resize', () => {
        if (activeTooltipTarget instanceof HTMLElement) {
            positionUiTooltip(activeTooltipTarget);
        }
    });

    window.addEventListener('scroll', () => {
        if (activeTooltipTarget instanceof HTMLElement) {
            positionUiTooltip(activeTooltipTarget);
        }
    }, true);
}

function setupFileInputFields() {
    document.querySelectorAll('[data-file-input]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const field = input.closest('[data-file-field]');
        const control = field instanceof HTMLElement ? field.querySelector('[data-file-control]') : null;
        const nameLabel = field instanceof HTMLElement ? field.querySelector('[data-file-name]') : null;
        const emptyLabel = String(input.getAttribute('data-empty-label') || 'Nenhum arquivo escolhido').trim();

        if (!(field instanceof HTMLElement) || !(control instanceof HTMLElement) || !(nameLabel instanceof HTMLElement)) {
            return;
        }

        function updateFieldLabel() {
            const files = Array.from(input.files || []);
            const nextLabel = files.length > 0
                ? files.map((file) => file.name).join(', ')
                : emptyLabel;

            nameLabel.textContent = nextLabel;
            field.dataset.hasFile = files.length > 0 ? '1' : '0';
            setElementTooltip(control, nextLabel);
        }

        input.addEventListener('change', updateFieldLabel);
        updateFieldLabel();
    });
}

function parseIsoDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);
    const parsedDate = new Date(year, month - 1, day);

    if (
        parsedDate.getFullYear() !== year
        || parsedDate.getMonth() !== month - 1
        || parsedDate.getDate() !== day
    ) {
        return null;
    }

    return parsedDate;
}

function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function parseBrDate(value) {
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(value || '')) {
        return null;
    }

    const [day, month, year] = value.split('/').map(Number);
    const parsedDate = new Date(year, month - 1, day);

    if (
        parsedDate.getFullYear() !== year
        || parsedDate.getMonth() !== month - 1
        || parsedDate.getDate() !== day
    ) {
        return null;
    }

    return parsedDate;
}

function formatBrDateInput(value) {
    const trimmedValue = String(value || '').trim();

    if (trimmedValue === '') {
        return '';
    }

    if (/^\d{2}\/\d{2}\/\d{4}$/.test(trimmedValue)) {
        return trimmedValue;
    }

    const parsedDate = parseIsoDate(trimmedValue);

    if (!parsedDate) {
        return trimmedValue;
    }

    const day = String(parsedDate.getDate()).padStart(2, '0');
    const month = String(parsedDate.getMonth() + 1).padStart(2, '0');
    const year = parsedDate.getFullYear();

    return `${day}/${month}/${year}`;
}

function normalizeDateTextInput(value) {
    const digits = String(value || '').replace(/\D+/g, '').slice(0, 8);

    if (digits.length <= 2) {
        return digits;
    }

    if (digits.length <= 4) {
        return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    }

    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
}

function normalizeSummaryDateValue(value) {
    const trimmedValue = String(value || '').trim();

    if (trimmedValue === '') {
        return '';
    }

    const parsedBrDate = parseBrDate(trimmedValue);

    if (parsedBrDate) {
        return toIsoDate(parsedBrDate);
    }

    const parsedIsoDate = parseIsoDate(trimmedValue);

    if (parsedIsoDate) {
        return toIsoDate(parsedIsoDate);
    }

    return null;
}

function isSameDate(firstDate, secondDate) {
    return firstDate instanceof Date
        && secondDate instanceof Date
        && firstDate.getFullYear() === secondDate.getFullYear()
        && firstDate.getMonth() === secondDate.getMonth()
        && firstDate.getDate() === secondDate.getDate();
}

function formatDateLabel(date) {
    return new Intl.DateTimeFormat('pt-BR').format(date);
}

function formatDateRangeLabel(startDate, endDate) {
    if (!startDate && !endDate) {
        return 'Selecionar data ou intervalo';
    }

    if (startDate && !endDate) {
        return formatDateLabel(startDate);
    }

    if (!startDate && endDate) {
        return formatDateLabel(endDate);
    }

    if (isSameDate(startDate, endDate)) {
        return formatDateLabel(startDate);
    }

    return `${formatDateLabel(startDate)} ate ${formatDateLabel(endDate)}`;
}

function clampDayDate(date) {
    const normalizedDate = new Date(date);
    normalizedDate.setHours(0, 0, 0, 0);

    return normalizedDate;
}

function setupDateRangePickers() {
    document.querySelectorAll('[data-date-range]').forEach((root) => {
        const trigger = root.querySelector('[data-date-range-trigger]');
        const picker = root.querySelector('[data-date-range-picker]');
        const label = root.querySelector('[data-date-range-label]');
        const summary = root.querySelector('[data-date-range-summary]');
        const monthLabel = root.querySelector('[data-date-range-month]');
        const grid = root.querySelector('[data-date-range-grid]');
        const previousButton = root.querySelector('[data-date-range-prev]');
        const nextButton = root.querySelector('[data-date-range-next]');
        const clearButton = root.querySelector('[data-date-range-clear]');
        const closeButton = root.querySelector('[data-date-range-close]');
        const startInput = root.querySelector('[data-date-range-start]');
        const endInput = root.querySelector('[data-date-range-end]');

        if (
            !(trigger instanceof HTMLButtonElement)
            || !(picker instanceof HTMLDivElement)
            || !(label instanceof HTMLElement)
            || !(summary instanceof HTMLElement)
            || !(monthLabel instanceof HTMLElement)
            || !(grid instanceof HTMLDivElement)
            || !(previousButton instanceof HTMLButtonElement)
            || !(nextButton instanceof HTMLButtonElement)
            || !(clearButton instanceof HTMLButtonElement)
            || !(closeButton instanceof HTMLButtonElement)
            || !(startInput instanceof HTMLInputElement)
            || !(endInput instanceof HTMLInputElement)
        ) {
            return;
        }

        let startDate = parseIsoDate(startInput.value || root.getAttribute('data-initial-start') || '');
        let endDate = parseIsoDate(endInput.value || root.getAttribute('data-initial-end') || '');
        let viewDate = clampDayDate(startDate || endDate || new Date());
        viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);

        function syncInputs() {
            startInput.value = startDate ? toIsoDate(startDate) : '';
            endInput.value = endDate ? toIsoDate(endDate) : '';

            const text = formatDateRangeLabel(startDate, endDate);
            label.textContent = text;
            summary.textContent = text;
            label.classList.toggle('date-range-label-empty', !startDate && !endDate);
        }

        function renderCalendar() {
            monthLabel.textContent = new Intl.DateTimeFormat('pt-BR', {
                month: 'long',
                year: 'numeric',
            }).format(viewDate);

            const monthStart = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
            const calendarStart = new Date(monthStart);
            calendarStart.setDate(monthStart.getDate() - monthStart.getDay());

            grid.innerHTML = '';

            for (let index = 0; index < 42; index += 1) {
                const dayDate = new Date(calendarStart);
                dayDate.setDate(calendarStart.getDate() + index);

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'date-range-day';
                button.textContent = String(dayDate.getDate());
                button.dataset.dateValue = toIsoDate(dayDate);

                if (dayDate.getMonth() !== viewDate.getMonth()) {
                    button.classList.add('is-outside');
                }

                if (startDate && isSameDate(dayDate, startDate) && !endDate) {
                    button.classList.add('is-selected');
                }

                if (startDate && endDate) {
                    const dayTime = dayDate.getTime();
                    const startTime = startDate.getTime();
                    const endTime = endDate.getTime();

                    if (dayTime > startTime && dayTime < endTime) {
                        button.classList.add('is-in-range');
                    }

                    if (isSameDate(dayDate, startDate)) {
                        button.classList.add('is-range-start');
                    }

                    if (isSameDate(dayDate, endDate)) {
                        button.classList.add('is-range-end');
                    }
                }

                grid.appendChild(button);
            }
        }

        function closePicker() {
            picker.hidden = true;
            root.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        function openPicker() {
            picker.hidden = false;
            root.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
        }

        trigger.addEventListener('click', () => {
            if (picker.hidden) {
                openPicker();
                return;
            }

            closePicker();
        });

        previousButton.addEventListener('click', () => {
            viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
            renderCalendar();
        });

        nextButton.addEventListener('click', () => {
            viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
            renderCalendar();
        });

        clearButton.addEventListener('click', () => {
            startDate = null;
            endDate = null;
            syncInputs();
            renderCalendar();
        });

        closeButton.addEventListener('click', () => {
            closePicker();
        });

        grid.addEventListener('click', (event) => {
            const dayButton = event.target.closest('[data-date-value]');

            if (!(dayButton instanceof HTMLButtonElement)) {
                return;
            }

            const selectedDate = parseIsoDate(dayButton.dataset.dateValue || '');

            if (!(selectedDate instanceof Date)) {
                return;
            }

            if (!startDate || (startDate && endDate)) {
                startDate = selectedDate;
                endDate = null;
            } else if (selectedDate.getTime() < startDate.getTime()) {
                startDate = selectedDate;
                endDate = null;
            } else if (isSameDate(selectedDate, startDate)) {
                endDate = null;
            } else {
                endDate = selectedDate;
            }

            viewDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
            syncInputs();
            renderCalendar();

            if (startDate && endDate) {
                closePicker();
            }
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) {
                closePicker();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePicker();
            }
        });

        syncInputs();
        renderCalendar();
    });
}

function setupCollapsiblePanels() {
    document.querySelectorAll('[data-collapsible-panel]').forEach((panel) => {
        const toggle = panel.querySelector('[data-collapsible-toggle]');
        const body = panel.querySelector('[data-collapsible-body]');
        const icon = panel.querySelector('[data-collapsible-icon]');

        if (
            !(toggle instanceof HTMLButtonElement)
            || !(body instanceof HTMLElement)
            || !(icon instanceof HTMLElement)
        ) {
            return;
        }

        function setOpen(isOpen) {
            panel.classList.toggle('is-open', isOpen);
            body.hidden = !isOpen;
            toggle.setAttribute('aria-expanded', String(isOpen));
            icon.textContent = isOpen ? '\u2212' : '+';
        }

        setOpen(toggle.getAttribute('aria-expanded') === 'true' && !body.hasAttribute('hidden'));

        toggle.addEventListener('click', () => {
            setOpen(body.hidden);
        });
    });
}

function setupOperationsManager() {
    document.querySelectorAll('[data-operations-list]').forEach((root) => {
        const items = Array.from(root.querySelectorAll('[data-operation-item]'));

        if (items.length === 0) {
            return;
        }

        function setItemOpen(item, shouldOpen) {
            const toggle = item.querySelector('[data-operation-toggle]');
            const body = item.querySelector('[data-operation-body]');
            const chevron = item.querySelector('.operation-item-chevron');

            if (
                !(toggle instanceof HTMLButtonElement)
                || !(body instanceof HTMLElement)
                || !(chevron instanceof HTMLElement)
            ) {
                return;
            }

            item.classList.toggle('is-open', shouldOpen);
            body.hidden = !shouldOpen;
            toggle.setAttribute('aria-expanded', String(shouldOpen));
            chevron.textContent = shouldOpen ? '\u2212' : '+';
        }

        items.forEach((item) => {
            const toggle = item.querySelector('[data-operation-toggle]');
            const body = item.querySelector('[data-operation-body]');

            if (!(toggle instanceof HTMLButtonElement) || !(body instanceof HTMLElement)) {
                return;
            }

            setItemOpen(item, toggle.getAttribute('aria-expanded') === 'true' && !body.hidden);

            toggle.addEventListener('click', () => {
                const shouldOpen = body.hidden;

                items.forEach((currentItem) => {
                    if (currentItem !== item) {
                        setItemOpen(currentItem, false);
                    }
                });

                setItemOpen(item, shouldOpen);
            });
        });
    });
}

function setupHierarchyExportModal() {
    const modal = document.querySelector('[data-hierarchy-export-modal]');

    if (!(modal instanceof HTMLElement)) {
        return;
    }

    function openModal() {
        modal.hidden = false;
        body.classList.add('modal-open');
    }

    function closeModal() {
        modal.hidden = true;
        body.classList.remove('modal-open');
    }

    if (!modal.hidden) {
        body.classList.add('modal-open');
    }

    document.querySelectorAll('[data-open-hierarchy-export-modal]').forEach((button) => {
        button.addEventListener('click', openModal);
    });

    modal.querySelectorAll('[data-close-hierarchy-export-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
}

function setupHierarchyDeleteModal() {
    const modal = document.querySelector('[data-hierarchy-delete-modal]');

    if (!(modal instanceof HTMLElement)) {
        return;
    }

    const title = modal.querySelector('[data-hierarchy-delete-title]');
    const description = modal.querySelector('[data-hierarchy-delete-description]');
    const subject = modal.querySelector('[data-hierarchy-delete-subject]');
    const countdownPanel = modal.querySelector('[data-hierarchy-delete-countdown-panel]');
    const countdown = modal.querySelector('[data-hierarchy-delete-countdown]');
    const progressBar = modal.querySelector('[data-hierarchy-delete-progress]');
    const confirmButton = modal.querySelector('[data-hierarchy-delete-confirm]');

    if (
        !(title instanceof HTMLElement)
        || !(description instanceof HTMLElement)
        || !(subject instanceof HTMLElement)
        || !(countdownPanel instanceof HTMLElement)
        || !(countdown instanceof HTMLElement)
        || !(progressBar instanceof HTMLElement)
        || !(confirmButton instanceof HTMLButtonElement)
    ) {
        return;
    }

    let activeForm = null;
    let countdownTimer = null;
    let secondsLeft = 10;

    function syncBodyModalState() {
        const hasOpenModal = Array.from(document.querySelectorAll('.modal-shell')).some((shell) => {
            return shell instanceof HTMLElement && !shell.hidden;
        });

        body.classList.toggle('modal-open', hasOpenModal);
    }

    function resetCountdownState() {
        if (countdownTimer !== null) {
            window.clearInterval(countdownTimer);
            countdownTimer = null;
        }

        secondsLeft = 10;
        confirmButton.disabled = false;
        confirmButton.textContent = 'Confirmar exclusão';
        countdown.textContent = 'Exclusão em 10s';
        countdownPanel.hidden = true;
        progressBar.style.width = '0%';
        modal.classList.remove('is-counting');
    }

    function closeModal() {
        resetCountdownState();
        activeForm = null;
        modal.hidden = true;
        syncBodyModalState();
    }

    function openModal(form) {
        const deleteLabel = form.dataset.deleteLabel || 'item';
        const deleteName = form.dataset.deleteName || 'Não informado';

        activeForm = form;
        title.textContent = `Excluir ${deleteLabel}`;
        description.textContent = `Confirme se deseja excluir ${deleteLabel}. Você ainda poderá cancelar antes do envio definitivo.`;
        subject.textContent = deleteName;

        resetCountdownState();
        modal.hidden = false;
        syncBodyModalState();
    }

    function updateCountdownView() {
        countdown.textContent = `Exclusão em ${secondsLeft}s`;
        progressBar.style.width = `${((10 - secondsLeft) / 10) * 100}%`;
    }

    function startCountdown() {
        if (!(activeForm instanceof HTMLFormElement)) {
            return;
        }

        resetCountdownState();
        modal.classList.add('is-counting');
        countdownPanel.hidden = false;
        confirmButton.disabled = true;
        confirmButton.textContent = 'Exclusão agendada';
        updateCountdownView();

        countdownTimer = window.setInterval(() => {
            secondsLeft -= 1;

            if (secondsLeft <= 0) {
                const formToSubmit = activeForm;
                closeModal();

                if (formToSubmit instanceof HTMLFormElement) {
                    formToSubmit.submit();
                }

                return;
            }

            updateCountdownView();
        }, 1000);
    }

    document.querySelectorAll('[data-hierarchy-delete-form]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            openModal(form);
        });
    });

    confirmButton.addEventListener('click', startCountdown);

    modal.querySelectorAll('[data-close-hierarchy-delete-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
}

function setupProductDuplicateModal() {
    const modal = document.querySelector('[data-product-duplicate-modal]');

    if (!(modal instanceof HTMLElement)) {
        return;
    }

    function openModal() {
        modal.hidden = false;
        body.classList.add('modal-open');
    }

    function closeModal() {
        modal.hidden = true;
        body.classList.remove('modal-open');
    }

    if (!modal.hidden) {
        body.classList.add('modal-open');
    }

    document.querySelectorAll('[data-open-product-duplicate-modal]').forEach((button) => {
        button.addEventListener('click', openModal);
    });

    modal.querySelectorAll('[data-close-product-duplicate-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
}

function setupQueuePrioritizeModal() {
    const modal = document.querySelector('[data-queue-prioritize-modal]');

    if (!(modal instanceof HTMLElement)) {
        return;
    }

    const prioritizeButton = modal.querySelector('[data-queue-prioritize-action]');

    function closeModal() {
        modal.hidden = true;
        body.classList.remove('modal-open');
    }

    if (!modal.hidden) {
        body.classList.add('modal-open');
    }

    modal.querySelectorAll('[data-close-queue-prioritize-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    if (prioritizeButton instanceof HTMLElement) {
        prioritizeButton.addEventListener('click', () => {
            const targetUrl = prioritizeButton.getAttribute('data-queue-prioritize-url');
            closeModal();

            if (targetUrl) {
                window.location.href = targetUrl;
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            event.preventDefault();
        }
    });
}

function toggleProductRecalculationPanel(shouldOpen) {
    const panel = document.querySelector('[data-product-recalc-panel]');

    if (!(panel instanceof HTMLElement)) {
        return;
    }

    panel.hidden = !shouldOpen;
    panel.classList.toggle('is-open', shouldOpen);

    if (shouldOpen) {
        const dateTrigger = panel.querySelector('[data-date-range-trigger]');

        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        if (dateTrigger instanceof HTMLButtonElement) {
            window.setTimeout(() => {
                dateTrigger.click();
            }, 60);
        }
    }
}

function setupQueueLiveRefresh() {
    const liveRoot = document.querySelector('[data-queue-live]');
    const summaryRoot = document.querySelector('[data-queue-live-summary]');
    const listRoot = document.querySelector('[data-queue-live-list]');

    if (!(liveRoot instanceof HTMLElement) || !(summaryRoot instanceof HTMLElement) || !(listRoot instanceof HTMLElement)) {
        return;
    }

    const intervalMs = Number(liveRoot.getAttribute('data-queue-live-interval')) || 4000;
    let isFetching = false;

    async function refreshQueue() {
        if (isFetching || document.hidden) {
            return;
        }

        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('route', 'queue/live');

        isFetching = true;

        try {
            const response = await fetch(currentUrl.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache',
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const summaryHtml = typeof payload.summary_html === 'string' ? payload.summary_html.trim() : '';
            const listHtml = typeof payload.list_html === 'string' ? payload.list_html.trim() : '';

            if (summaryHtml !== '' && summaryHtml !== summaryRoot.innerHTML.trim()) {
                summaryRoot.innerHTML = summaryHtml;
                syncCustomTooltips(summaryRoot);
            }

            if (listHtml !== '' && listHtml !== listRoot.innerHTML.trim()) {
                listRoot.innerHTML = listHtml;
                syncCustomTooltips(listRoot);
            }
        } catch (error) {
            // Keep the page usable even if a polling request fails temporarily.
        } finally {
            isFetching = false;
        }
    }

    const intervalId = window.setInterval(refreshQueue, intervalMs);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshQueue();
        }
    });

    window.addEventListener('beforeunload', () => {
        window.clearInterval(intervalId);
    }, { once: true });
}

function setupDashboardExecutiveLive() {
    const liveRoot = document.querySelector('[data-dashboard-live]');

    if (!(liveRoot instanceof HTMLElement)) {
        return;
    }

    const liveUrl = liveRoot.getAttribute('data-dashboard-live-url') || '';
    const intervalMs = Number(liveRoot.getAttribute('data-dashboard-live-interval')) || 8000;

    if (liveUrl === '') {
        return;
    }

    let isFetching = false;

    async function refreshDashboard() {
        if (isFetching || document.hidden) {
            return;
        }

        isFetching = true;

        try {
            const response = await fetch(liveUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache',
                },
            });

            if (!response.ok) {
                return;
            }

            const html = (await response.text()).trim();

            if (html !== '' && html !== liveRoot.innerHTML.trim()) {
                liveRoot.innerHTML = html;
                syncCustomTooltips(liveRoot);
            }
        } catch (error) {
            // Keep the dashboard visible if one polling request fails.
        } finally {
            isFetching = false;
        }
    }

    const intervalId = window.setInterval(refreshDashboard, intervalMs);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshDashboard();
        }
    });

    window.addEventListener('beforeunload', () => {
        window.clearInterval(intervalId);
    }, { once: true });
}

function setupQueueSearchToggle() {
    const field = document.querySelector('[data-queue-search-field]');

    if (!(field instanceof HTMLElement)) {
        return;
    }

    const form = field.closest('form');
    const toggle = field.querySelector('[data-queue-search-toggle]');
    const input = field.querySelector('[data-queue-search-input]');

    if (!(toggle instanceof HTMLButtonElement) || !(input instanceof HTMLInputElement)) {
        return;
    }

    function setOpen(isOpen) {
        field.classList.toggle('is-open', isOpen);
        if (form instanceof HTMLElement) {
            form.classList.toggle('is-search-open', isOpen);
        }
        toggle.setAttribute('aria-expanded', String(isOpen));
        input.hidden = !isOpen;

        if (isOpen) {
            window.setTimeout(() => {
                input.focus();
                input.select();
            }, 10);
        } else {
            input.value = '';
        }
    }

    toggle.addEventListener('click', () => {
        setOpen(!field.classList.contains('is-open'));
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && input.value.trim() === '') {
            setOpen(false);
        }
    });

    if (input.value.trim() !== '') {
        setOpen(true);
    }
}

function setupQueueStatusFilter() {
    const root = document.querySelector('[data-queue-status-filter]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const trigger = root.querySelector('[data-queue-status-trigger]');
    const dropdown = root.querySelector('[data-queue-status-dropdown]');
    const summary = root.querySelector('[data-queue-status-summary]');
    const checkboxes = Array.from(root.querySelectorAll('[data-queue-status-checkbox]')).filter((checkbox) => checkbox instanceof HTMLInputElement);

    if (!(trigger instanceof HTMLButtonElement) || !(dropdown instanceof HTMLElement) || !(summary instanceof HTMLElement)) {
        return;
    }

    const labelMap = {
        'PENDENTE INPUT': 'PENDENTE',
        AUDITANDO: 'AUDITANDO',
        FINALIZADA: 'FINALIZADA',
    };

    function updateSummary() {
        const selectedLabels = checkboxes
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => labelMap[checkbox.value] || checkbox.value);

        if (selectedLabels.length === 0) {
            summary.textContent = 'Todos';
            setElementTooltip(trigger, 'Todos');
            return;
        }

        summary.textContent = selectedLabels.length === 1
            ? selectedLabels[0]
            : `${selectedLabels.length} selecionados`;
        setElementTooltip(trigger, selectedLabels.join(', '));
    }

    function setOpen(isOpen) {
        root.classList.toggle('is-open', isOpen);
        dropdown.hidden = !isOpen;
        trigger.setAttribute('aria-expanded', String(isOpen));
    }

    trigger.addEventListener('click', () => {
        setOpen(dropdown.hidden);
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSummary);
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    updateSummary();
}

function setupQueueCustomerTypeFilter() {
    const root = document.querySelector('[data-queue-customer-type-filter]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const trigger = root.querySelector('[data-queue-customer-type-trigger]');
    const dropdown = root.querySelector('[data-queue-customer-type-dropdown]');
    const summary = root.querySelector('[data-queue-customer-type-summary]');
    const radios = Array.from(root.querySelectorAll('[data-queue-customer-type-radio]')).filter((radio) => radio instanceof HTMLInputElement);

    if (!(trigger instanceof HTMLButtonElement) || !(dropdown instanceof HTMLElement) || !(summary instanceof HTMLElement)) {
        return;
    }

    function updateSummary() {
        const selectedRadio = radios.find((radio) => radio.checked);
        const selectedLabel = selectedRadio instanceof HTMLInputElement && selectedRadio.value !== ''
            ? selectedRadio.value
            : 'Todos';

        summary.textContent = selectedLabel;
        setElementTooltip(trigger, selectedLabel);
    }

    function setOpen(isOpen) {
        root.classList.toggle('is-open', isOpen);
        dropdown.hidden = !isOpen;
        trigger.setAttribute('aria-expanded', String(isOpen));
    }

    trigger.addEventListener('click', () => {
        setOpen(dropdown.hidden);
    });

    radios.forEach((radio) => {
        radio.addEventListener('change', () => {
            updateSummary();
            setOpen(false);
        });
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    updateSummary();
}

function setupQueueModalityFilter() {
    const root = document.querySelector('[data-queue-modality-filter]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const trigger = root.querySelector('[data-queue-modality-trigger]');
    const dropdown = root.querySelector('[data-queue-modality-dropdown]');
    const summary = root.querySelector('[data-queue-modality-summary]');
    const checkboxes = Array.from(root.querySelectorAll('[data-queue-modality-checkbox]')).filter((checkbox) => checkbox instanceof HTMLInputElement);

    if (!(trigger instanceof HTMLButtonElement) || !(dropdown instanceof HTMLElement) || !(summary instanceof HTMLElement)) {
        return;
    }

    function updateSummary() {
        const selectedLabels = checkboxes
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.value);

        if (selectedLabels.length === 0) {
            summary.textContent = 'Todas';
            setElementTooltip(trigger, 'Todas');
            return;
        }

        summary.textContent = selectedLabels.length === 1
            ? selectedLabels[0]
            : `${selectedLabels.length} selecionadas`;
        setElementTooltip(trigger, selectedLabels.join(', '));
    }

    function setOpen(isOpen) {
        root.classList.toggle('is-open', isOpen);
        dropdown.hidden = !isOpen;
        trigger.setAttribute('aria-expanded', String(isOpen));
    }

    trigger.addEventListener('click', () => {
        setOpen(dropdown.hidden);
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSummary);
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    updateSummary();
}

function setupQueueOperationFilter() {
    const root = document.querySelector('[data-queue-operation-filter]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const trigger = root.querySelector('[data-queue-operation-trigger]');
    const dropdown = root.querySelector('[data-queue-operation-dropdown]');
    const summary = root.querySelector('[data-queue-operation-summary]');
    const checkboxes = Array.from(root.querySelectorAll('[data-queue-operation-checkbox]')).filter((checkbox) => checkbox instanceof HTMLInputElement);

    if (!(trigger instanceof HTMLButtonElement) || !(dropdown instanceof HTMLElement) || !(summary instanceof HTMLElement)) {
        return;
    }

    function updateSummary() {
        const selectedLabels = checkboxes
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.value);

        if (selectedLabels.length === 0) {
            summary.textContent = 'Todas';
            setElementTooltip(trigger, 'Todas');
            return;
        }

        summary.textContent = selectedLabels.length === 1
            ? selectedLabels[0]
            : `${selectedLabels.length} selecionadas`;
        setElementTooltip(trigger, selectedLabels.join(', '));
    }

    function setOpen(isOpen) {
        root.classList.toggle('is-open', isOpen);
        dropdown.hidden = !isOpen;
        trigger.setAttribute('aria-expanded', String(isOpen));
    }

    trigger.addEventListener('click', () => {
        setOpen(dropdown.hidden);
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSummary);
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    updateSummary();
}

function setupHierarchyBaseFilter() {
    const root = document.querySelector('[data-hierarchy-base-filter]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const trigger = root.querySelector('[data-hierarchy-base-trigger]');
    const dropdown = root.querySelector('[data-hierarchy-base-dropdown]');
    const summary = root.querySelector('[data-hierarchy-base-summary]');
    const checkboxes = Array.from(root.querySelectorAll('[data-hierarchy-base-checkbox]')).filter((checkbox) => checkbox instanceof HTMLInputElement);

    if (!(trigger instanceof HTMLButtonElement) || !(dropdown instanceof HTMLElement) || !(summary instanceof HTMLElement)) {
        return;
    }

    function updateSummary() {
        const selectedLabels = checkboxes
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => {
                const label = checkbox.closest('label');
                const text = label?.querySelector('span');
                return text instanceof HTMLElement ? text.textContent.trim() : checkbox.value;
            })
            .filter((label) => label !== '');

        if (selectedLabels.length === 0) {
            summary.textContent = 'Todas';
            setElementTooltip(trigger, 'Todas');
            return;
        }

        summary.textContent = selectedLabels.length === 1
            ? selectedLabels[0]
            : `${selectedLabels.length} selecionadas`;
        setElementTooltip(trigger, selectedLabels.join(', '));
    }

    function setOpen(isOpen) {
        root.classList.toggle('is-open', isOpen);
        dropdown.hidden = !isOpen;
        trigger.setAttribute('aria-expanded', String(isOpen));
    }

    trigger.addEventListener('click', () => {
        setOpen(dropdown.hidden);
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSummary);
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    updateSummary();
}

function setupHierarchyRoleFilter() {
    const root = document.querySelector('[data-hierarchy-role-filter]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const trigger = root.querySelector('[data-hierarchy-role-trigger]');
    const dropdown = root.querySelector('[data-hierarchy-role-dropdown]');
    const summary = root.querySelector('[data-hierarchy-role-summary]');
    const checkboxes = Array.from(root.querySelectorAll('[data-hierarchy-role-checkbox]')).filter((checkbox) => checkbox instanceof HTMLInputElement);

    if (!(trigger instanceof HTMLButtonElement) || !(dropdown instanceof HTMLElement) || !(summary instanceof HTMLElement)) {
        return;
    }

    function updateSummary() {
        const selectedLabels = checkboxes
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.value);

        if (selectedLabels.length === 0) {
            summary.textContent = 'Todos';
            setElementTooltip(trigger, 'Todos');
            return;
        }

        summary.textContent = selectedLabels.length === 1
            ? selectedLabels[0]
            : `${selectedLabels.length} selecionados`;
        setElementTooltip(trigger, selectedLabels.join(', '));
    }

    function setOpen(isOpen) {
        root.classList.toggle('is-open', isOpen);
        dropdown.hidden = !isOpen;
        trigger.setAttribute('aria-expanded', String(isOpen));
    }

    trigger.addEventListener('click', () => {
        setOpen(dropdown.hidden);
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSummary);
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    updateSummary();
}

function setupReportsChecklistFilters() {
    document.querySelectorAll('[data-reports-checklist-filter]').forEach((root) => {
        if (!(root instanceof HTMLElement)) {
            return;
        }

        const trigger = root.querySelector('[data-reports-checklist-trigger]');
        const dropdown = root.querySelector('[data-reports-checklist-dropdown]');
        const summary = root.querySelector('[data-reports-checklist-summary]');
        const checkboxes = Array.from(root.querySelectorAll('[data-reports-checklist-checkbox]')).filter((checkbox) => checkbox instanceof HTMLInputElement);
        const defaultLabel = String(root.getAttribute('data-default-label') || 'Todos');

        if (!(trigger instanceof HTMLButtonElement) || !(dropdown instanceof HTMLElement) || !(summary instanceof HTMLElement)) {
            return;
        }

        function updateSummary() {
            const selectedLabels = checkboxes
                .filter((checkbox) => checkbox.checked)
                .map((checkbox) => checkbox.getAttribute('data-label') || checkbox.value)
                .filter((label) => label !== '');

            if (selectedLabels.length === 0) {
                summary.textContent = defaultLabel;
                setElementTooltip(trigger, defaultLabel);
                return;
            }

            summary.textContent = selectedLabels.length === 1
                ? selectedLabels[0]
                : `${selectedLabels.length} selecionados`;
            setElementTooltip(trigger, selectedLabels.join(', '));
        }

        function autoSelectSingleOption() {
            if (checkboxes.length !== 1) {
                return;
            }

            const [singleOption] = checkboxes;
            if (singleOption.checked) {
                return;
            }

            singleOption.checked = true;
            singleOption.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function setOpen(isOpen) {
            root.classList.toggle('is-open', isOpen);
            dropdown.hidden = !isOpen;
            trigger.setAttribute('aria-expanded', String(isOpen));
        }

        trigger.addEventListener('click', () => {
            setOpen(dropdown.hidden);
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', updateSummary);
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });

        updateSummary();
        autoSelectSingleOption();
    });
}

function setupReportsCalendarState() {
    document.querySelectorAll('.reports-calendar-group').forEach((group) => {
        if (!(group instanceof HTMLElement)) {
            return;
        }

        const radios = Array.from(group.querySelectorAll('.reports-calendar-option input[type="radio"]')).filter((radio) => radio instanceof HTMLInputElement);
        function syncRadioState() {
            Array.from(group.querySelectorAll('.reports-calendar-option')).forEach((option) => {
                if (!(option instanceof HTMLElement)) {
                    return;
                }

                const input = option.querySelector('input[type="radio"]');
                option.classList.toggle('is-active', input instanceof HTMLInputElement && input.checked);
            });
        }

        radios.forEach((radio) => {
            radio.addEventListener('change', syncRadioState);
        });

        syncRadioState();
    });
}

function setupReportsTableExports() {
    document.querySelectorAll('[data-table-export-button]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.addEventListener('click', () => {
            const tableSelector = button.getAttribute('data-export-table') || '';
            const filenameBase = (button.getAttribute('data-export-filename') || 'relatorio').trim();
            const table = document.querySelector(tableSelector);

            if (!(table instanceof HTMLTableElement)) {
                return;
            }

            const rows = Array.from(table.querySelectorAll('tr'))
                .map((row) => Array.from(row.querySelectorAll('th, td'))
                    .map((cell) => String(cell.textContent || '').replace(/\s+/g, ' ').trim())
                    .filter((value) => value !== '')
                )
                .filter((rowValues) => rowValues.length > 0);

            if (rows.length === 0) {
                return;
            }

            const csvContent = rows
                .map((rowValues) => rowValues
                    .map((value) => `"${value.replace(/"/g, '""')}"`)
                    .join(';'))
                .join('\r\n');

            const blob = new Blob([`\uFEFF${csvContent}`], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = `${filenameBase}.csv`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        });
    });
}

function setupReportsAutoSubmit() {
    document.querySelectorAll('[data-auto-submit-form]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        let submitTimeout = null;
        let lastSerialized = new URLSearchParams(new FormData(form)).toString();

        function scheduleSubmit(delay = 350) {
            window.clearTimeout(submitTimeout);
            submitTimeout = window.setTimeout(() => {
                const serialized = new URLSearchParams(new FormData(form)).toString();
                if (serialized === lastSerialized) {
                    return;
                }
                lastSerialized = serialized;
                form.submit();
            }, delay);
        }

        form.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement)) {
                return;
            }
            if (target.type === 'text') {
                return;
            }
            scheduleSubmit(250);
        });

        form.querySelectorAll('input[type="text"]').forEach((input) => {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            input.addEventListener('input', () => {
                scheduleSubmit(500);
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    scheduleSubmit(0);
                }
            });

            input.addEventListener('blur', () => {
                scheduleSubmit(0);
            });
        });
    });
}

function setupHierarchySellerPanelPersistence() {
    const panel = document.querySelector('[data-hierarchy-seller-panel]');

    if (!(panel instanceof HTMLElement)) {
        return;
    }

    const storageKey = 'vigg-hierarchy-seller-panel-scroll';
    const form = document.querySelector('[data-hierarchy-seller-filters-form]');

    if (form instanceof HTMLFormElement) {
        form.addEventListener('submit', () => {
            try {
                window.sessionStorage.setItem(storageKey, '1');
            } catch (error) {
                // Ignore storage failures and keep the default navigation.
            }
        });
    }

    document.querySelectorAll('[data-hierarchy-seller-panel-link]').forEach((link) => {
        link.addEventListener('click', () => {
            try {
                window.sessionStorage.setItem(storageKey, '1');
            } catch (error) {
                // Ignore storage failures and keep the default navigation.
            }
        });
    });

    try {
        if (window.sessionStorage.getItem(storageKey) === '1') {
            window.sessionStorage.removeItem(storageKey);
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    panel.scrollIntoView({ block: 'start' });
                });
            });
        }
    } catch (error) {
        // Ignore storage failures and keep the page at its natural position.
    }
}

function setupHierarchyRegionalFilter() {
    const root = document.querySelector('[data-hierarchy-regional-filter]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const trigger = root.querySelector('[data-hierarchy-regional-trigger]');
    const dropdown = root.querySelector('[data-hierarchy-regional-dropdown]');
    const summary = root.querySelector('[data-hierarchy-regional-summary]');
    const radios = Array.from(root.querySelectorAll('[data-hierarchy-regional-radio]')).filter((radio) => radio instanceof HTMLInputElement);

    if (!(trigger instanceof HTMLButtonElement) || !(dropdown instanceof HTMLElement) || !(summary instanceof HTMLElement)) {
        return;
    }

    function updateSummary() {
        const selectedRadio = radios.find((radio) => radio.checked);
        const selectedLabel = selectedRadio instanceof HTMLInputElement && selectedRadio.value !== ''
            ? selectedRadio.value
            : 'I';

        summary.textContent = selectedLabel;
        setElementTooltip(trigger, selectedLabel);
    }

    function setOpen(isOpen) {
        root.classList.toggle('is-open', isOpen);
        dropdown.hidden = !isOpen;
        trigger.setAttribute('aria-expanded', String(isOpen));
    }

    trigger.addEventListener('click', () => {
        setOpen(dropdown.hidden);
    });

    radios.forEach((radio) => {
        radio.addEventListener('change', () => {
            updateSummary();
            setOpen(false);
        });
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    updateSummary();
}

function setupConsultantPicker() {
    const modal = document.querySelector('[data-consultant-modal]');

    if (!(modal instanceof HTMLElement)) {
        return;
    }

    const searchInput = modal.querySelector('[data-consultant-search-input]');
    const resultsContainer = modal.querySelector('[data-consultant-search-results]');
    const status = modal.querySelector('[data-consultant-search-status]');
    const fieldElements = {
        seller_name: document.querySelector('[data-consultant-field="seller_name"]'),
        seller_cpf: document.querySelector('[data-consultant-field="seller_cpf"]'),
        role: document.querySelector('[data-consultant-field="role"]'),
        base_name: document.querySelector('[data-consultant-field="base_name"]'),
        base_group_name: document.querySelector('[data-consultant-field="base_group_name"]'),
        supervisor_name: document.querySelector('[data-consultant-field="supervisor_name"]'),
        coordinator_name: document.querySelector('[data-consultant-field="coordinator_name"]'),
        manager_name: document.querySelector('[data-consultant-field="manager_name"]'),
    };

    if (
        !(searchInput instanceof HTMLInputElement)
        || !(resultsContainer instanceof HTMLDivElement)
        || !(status instanceof HTMLElement)
    ) {
        return;
    }

    let searchTimeoutId = 0;
    let activeRequestId = 0;
    let currentResults = [];

    function openModal() {
        modal.hidden = false;
        body.classList.add('modal-open');
        searchInput.focus();
        searchInput.select();
    }

    function closeModal() {
        modal.hidden = true;
        body.classList.remove('modal-open');
        searchInput.value = '';
        resultsContainer.innerHTML = '';
        status.textContent = 'Digite para pesquisar por nome ou CPF.';
    }

    function renderResults(items) {
        currentResults = items;
        resultsContainer.innerHTML = '';

        if (items.length === 0) {
            status.textContent = 'Nenhum consultor encontrado.';
            return;
        }

        status.textContent = 'Selecione o consultor responsável pela venda.';

        items.forEach((item, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'consultant-result-button';
            button.dataset.consultantIndex = String(index);

            const title = document.createElement('strong');
            title.textContent = item.seller_name;

            const details = document.createElement('small');
            details.textContent = `CPF: ${item.seller_cpf} | ${item.role} | Base: ${item.base_name} | Grupo: ${item.base_group_name}`;

            const hierarchy = document.createElement('small');
            hierarchy.textContent = `Supervisor: ${item.supervisor_name} | Coordenador: ${item.coordinator_name} | Gerente: ${item.manager_name}`;

            button.appendChild(title);
            button.appendChild(details);
            button.appendChild(hierarchy);
            resultsContainer.appendChild(button);
        });
    }

    async function searchConsultants(term) {
        const trimmedTerm = term.trim();

        if (trimmedTerm.length < 2) {
            currentResults = [];
            resultsContainer.innerHTML = '';
            status.textContent = 'Digite ao menos 2 caracteres para pesquisar.';
            return;
        }

        const requestId = ++activeRequestId;
        status.textContent = 'Pesquisando consultores...';

        try {
            const endpoint = new URL(searchInput.getAttribute('data-search-url') || '', window.location.href);
            endpoint.searchParams.set('term', trimmedTerm);
            const periodHeadcount = (searchInput.getAttribute('data-search-period') || '').trim();

            if (/^\d{6}$/.test(periodHeadcount)) {
                endpoint.searchParams.set('period', periodHeadcount);
            }

            const response = await fetch(endpoint.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache',
                },
            });

            if (!response.ok) {
                throw new Error('Falha ao buscar consultores.');
            }

            const payload = await response.json();

            if (requestId !== activeRequestId) {
                return;
            }

            renderResults(Array.isArray(payload.items) ? payload.items : []);
        } catch (error) {
            currentResults = [];
            resultsContainer.innerHTML = '';
            status.textContent = 'Não foi possível pesquisar consultores agora.';
        }
    }

    document.querySelectorAll('[data-open-consultant-modal]').forEach((button) => {
        button.addEventListener('click', openModal);
    });

    modal.querySelectorAll('[data-close-consultant-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    searchInput.addEventListener('input', () => {
        window.clearTimeout(searchTimeoutId);
        searchTimeoutId = window.setTimeout(() => {
            searchConsultants(searchInput.value);
        }, 220);
    });

    resultsContainer.addEventListener('click', (event) => {
        const resultButton = event.target.closest('[data-consultant-index]');

        if (!(resultButton instanceof HTMLButtonElement)) {
            return;
        }

        const selectedConsultant = currentResults[Number(resultButton.dataset.consultantIndex)];

        if (!selectedConsultant) {
            return;
        }

        const fieldMap = {
            seller_name: selectedConsultant.seller_name || '',
            seller_cpf: selectedConsultant.seller_cpf || '',
            role: selectedConsultant.role || '',
            base_name: selectedConsultant.base_name || '',
            base_group_name: selectedConsultant.base_group_name || '',
            consultant_base_regional: selectedConsultant.consultant_base_regional || '',
            consultant_sector_name: selectedConsultant.consultant_sector_name || '',
            consultant_sector_type: selectedConsultant.consultant_sector_type || '',
            territory_manager_name: selectedConsultant.territory_manager_name || '',
            supervisor_name: selectedConsultant.supervisor_name || '',
            coordinator_name: selectedConsultant.coordinator_name || '',
            manager_name: selectedConsultant.manager_name || '',
        };

        Object.entries(fieldMap).forEach(([fieldName, fieldValue]) => {
            const element = fieldElements[fieldName];

            if (element instanceof HTMLInputElement) {
                element.value = fieldValue;
                element.dispatchEvent(new Event('input', { bubbles: true }));
                element.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
}

function setupSummaryCardModal() {
    const modal = document.querySelector('[data-summary-modal]');

    if (!(modal instanceof HTMLElement)) {
        return;
    }

    const title = modal.querySelector('[data-summary-modal-title]');
    const help = modal.querySelector('[data-summary-modal-help]');
    const inputHost = modal.querySelector('[data-summary-modal-input-host]');
    const copyButton = modal.querySelector('[data-copy-summary-modal]');
    const applyButton = modal.querySelector('[data-apply-summary-modal]');

    if (
        !(title instanceof HTMLElement)
        || !(help instanceof HTMLElement)
        || !(inputHost instanceof HTMLElement)
        || !(copyButton instanceof HTMLButtonElement)
        || !(applyButton instanceof HTMLButtonElement)
    ) {
        return;
    }

    let activeCard = null;
    let activeInput = null;

    function formatSummaryDisplay(card, value) {
        const trimmedValue = String(value || '').trim();

        if (trimmedValue === '') {
            return 'Não informado';
        }

        if ((card.dataset.summaryInputType || 'text') === 'date') {
            const parsedDate = parseIsoDate(trimmedValue);

            if (parsedDate) {
                return parsedDate.toLocaleDateString('pt-BR');
            }
        }

        return trimmedValue;
    }

    function closeModal() {
        modal.hidden = true;
        body.classList.remove('modal-open');
        inputHost.innerHTML = '';
        activeCard = null;
        activeInput = null;
    }

    function buildReadonlyView(card) {
        const box = document.createElement('div');
        box.className = 'summary-modal-readonly';
        box.textContent = card.dataset.summaryDisplay || 'Não informado';
        inputHost.appendChild(box);
        applyButton.hidden = true;
    }

    function buildEditableView(card) {
        const label = document.createElement('label');
        const labelText = document.createElement('span');
        labelText.textContent = card.dataset.summaryLabel || 'Campo';

        const input = document.createElement('input');
        const configuredInputType = card.dataset.summaryInputType || 'text';

        if (configuredInputType === 'date') {
            input.type = 'text';
            input.value = formatBrDateInput(card.dataset.summaryValue || '');
            input.placeholder = 'dd/mm/aaaa';
            input.inputMode = 'numeric';
            input.maxLength = 10;
            input.setAttribute('data-date-text', '');
        } else {
            input.type = configuredInputType;
            input.value = card.dataset.summaryValue || '';
        }

        input.autocomplete = 'off';

        const inputMode = card.dataset.summaryInputMode || '';
        if (configuredInputType !== 'date' && inputMode !== '') {
            input.setAttribute('inputmode', inputMode);
        }

        const maxLength = Number(card.dataset.summaryMaxlength || 0);
        if (configuredInputType !== 'date' && maxLength > 0) {
            input.maxLength = maxLength;
        }

        if (card.dataset.summaryDigits === '1') {
            input.setAttribute('data-only-digits', '');
        }

        if (card.dataset.summaryUppercase === '1') {
            input.setAttribute('data-uppercase', '');
        }

        label.appendChild(labelText);
        label.appendChild(input);
        inputHost.appendChild(label);
        activeInput = input;
        applyButton.hidden = false;

        input.addEventListener('input', () => {
            input.setCustomValidity('');
        });

        window.setTimeout(() => {
            input.focus();
            if (input.type !== 'date') {
                input.select();
            }
        }, 10);
    }

    function openModal(card) {
        activeCard = card;
        title.textContent = card.dataset.summaryLabel || 'Campo da venda';
        help.textContent = card.dataset.summaryEditable === '1'
            ? 'Edite o valor ou copie o conteúdo deste card.'
            : 'Este campo está em modo de consulta. Você pode copiar o valor.';
        inputHost.innerHTML = '';
        activeInput = null;

        if (card.dataset.summaryEditable === '1') {
            buildEditableView(card);
        } else {
            buildReadonlyView(card);
        }

        modal.hidden = false;
        body.classList.add('modal-open');
    }

    function updateCardValue(card, nextValue) {
        const displayValue = formatSummaryDisplay(card, nextValue);
        const valueElement = card.querySelector('[data-summary-card-value]');
        const hasAttention = card.dataset.summaryAttention === '1';
        const isFilled = String(nextValue || '').trim() !== '';

        card.dataset.summaryValue = nextValue;
        card.dataset.summaryDisplay = displayValue;
        card.dataset.summaryCopy = displayValue === 'Não informado' ? '' : displayValue;
        card.classList.toggle('is-pulsing', hasAttention && !isFilled);

        if (valueElement instanceof HTMLElement) {
            valueElement.textContent = displayValue;
        }
    }

    document.querySelectorAll('[data-summary-card]').forEach((card) => {
        card.addEventListener('click', () => openModal(card));
    });

    modal.querySelectorAll('[data-close-summary-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    copyButton.addEventListener('click', async () => {
        if (!(activeCard instanceof HTMLElement)) {
            return;
        }

        const copyValue = activeCard.dataset.summaryCopy || activeCard.dataset.summaryValue || '';

        if (copyValue.trim() === '') {
            showCopyToast('Nenhum valor para copiar', copyButton);
            return;
        }

        try {
            await copyText(copyValue);
            showCopyToast('Valor copiado', copyButton);
        } catch (error) {
            showCopyToast('Não foi possível copiar', copyButton);
        }
    });

    applyButton.addEventListener('click', () => {
        if (!(activeCard instanceof HTMLElement) || !(activeInput instanceof HTMLInputElement)) {
            return;
        }

        const fieldName = activeCard.dataset.summaryField || '';
        const hiddenInput = document.querySelector(`[data-summary-form-input="${fieldName}"]`);

        if (!(hiddenInput instanceof HTMLInputElement)) {
            closeModal();
            return;
        }

        let nextValue = activeInput.value;

        if ((activeCard.dataset.summaryInputType || 'text') === 'date') {
            const normalizedDateValue = normalizeSummaryDateValue(nextValue);

            if (normalizedDateValue === null) {
                activeInput.setCustomValidity('Informe uma data válida no formato dd/mm/aaaa.');
                activeInput.reportValidity();
                return;
            }

            activeInput.setCustomValidity('');
            nextValue = normalizedDateValue;
        }

        if (activeCard.dataset.summaryDigits === '1') {
            nextValue = nextValue.replace(/\D+/g, '');
        }

        if (activeCard.dataset.summaryUppercase === '1') {
            nextValue = nextValue.toLocaleUpperCase('pt-BR');
        }

        hiddenInput.value = nextValue;
        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        updateCardValue(activeCard, nextValue);
        closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
}

function setupInputMasks() {
    document.addEventListener('input', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        if (target.hasAttribute('data-only-digits')) {
            const maxLength = Number(target.getAttribute('maxlength')) || Infinity;
            target.value = target.value.replace(/\D+/g, '').slice(0, maxLength);
        }

        if (target.hasAttribute('data-uppercase')) {
            target.value = target.value.toLocaleUpperCase('pt-BR');
        }

        if (target.hasAttribute('data-date-text')) {
            target.value = normalizeDateTextInput(target.value);
        }
    });
}

function setupFirstAccessPasswordForm() {
    const form = document.querySelector('[data-first-access-form]');

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const passwordInput = form.querySelector('input[name="new_password"]');
    const confirmationInput = form.querySelector('input[name="new_password_confirmation"]');

    if (!(passwordInput instanceof HTMLInputElement) || !(confirmationInput instanceof HTMLInputElement)) {
        return;
    }

    const ruleElements = {
        length: form.querySelector('[data-password-rule="length"]'),
        uppercase: form.querySelector('[data-password-rule="uppercase"]'),
        special: form.querySelector('[data-password-rule="special"]'),
        match: form.querySelector('[data-password-rule="match"]'),
    };

    function getChecks() {
        const password = passwordInput.value;
        const confirmation = confirmationInput.value;

        return {
            length: password.length >= 6,
            uppercase: /[\p{Lu}]/u.test(password),
            special: /[^\p{L}\p{N}]/u.test(password),
            match: password !== '' && confirmation !== '' && password === confirmation,
        };
    }

    function applyRuleState() {
        const checks = getChecks();

        Object.entries(ruleElements).forEach(([ruleName, element]) => {
            if (!(element instanceof HTMLElement)) {
                return;
            }

            element.classList.toggle('is-valid', Boolean(checks[ruleName]));
        });

        const passwordIsValid = checks.length && checks.uppercase && checks.special;
        passwordInput.setCustomValidity(passwordIsValid ? '' : 'Escolha uma senha forte para continuar.');

        const confirmationIsValid = confirmationInput.value === '' || checks.match;
        confirmationInput.setCustomValidity(confirmationIsValid ? '' : 'A confirmacao da senha nao confere.');
    }

    passwordInput.addEventListener('input', applyRuleState);
    confirmationInput.addEventListener('input', applyRuleState);

    form.addEventListener('submit', (event) => {
        applyRuleState();

        if (passwordInput.checkValidity() && confirmationInput.checkValidity()) {
            return;
        }

        event.preventDefault();

        if (!passwordInput.checkValidity()) {
            passwordInput.reportValidity();
            passwordInput.focus();
            return;
        }

        confirmationInput.reportValidity();
        confirmationInput.focus();
    });

    applyRuleState();
}

function setupHierarchyBaseGroupSelect() {
    const dataScript = document.querySelector('#hierarchy-groups-data');
    const baseSelect = document.querySelector('[data-hierarchy-base-select]');
    const groupSelect = document.querySelector('[data-hierarchy-group-select]');

    if (
        !(dataScript instanceof HTMLScriptElement)
        || !(baseSelect instanceof HTMLSelectElement)
        || !(groupSelect instanceof HTMLSelectElement)
    ) {
        return;
    }

    let groupsByBase = {};

    try {
        groupsByBase = JSON.parse(dataScript.textContent || '{}');
    } catch (error) {
        groupsByBase = {};
    }

    let preferredGroupId = groupSelect.getAttribute('data-selected-group-id') || '';

    function renderGroupsForBase() {
        const baseId = baseSelect.value;
        const groups = Array.isArray(groupsByBase[baseId]) ? groupsByBase[baseId] : [];
        const currentValue = groupSelect.value || preferredGroupId;

        groupSelect.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = !baseId
            ? 'Selecione uma base primeiro'
            : (groups.length === 0 ? 'Nenhum base grupo vinculado' : 'Selecione');
        groupSelect.appendChild(placeholder);

        groups.forEach((group) => {
            const option = document.createElement('option');
            option.value = String(group.id);
            option.textContent = group.name;
            groupSelect.appendChild(option);
        });

        const hasCurrentValue = groups.some((group) => String(group.id) === String(currentValue));
        groupSelect.value = hasCurrentValue ? String(currentValue) : '';
        groupSelect.disabled = !baseId || groups.length === 0;
        preferredGroupId = groupSelect.value;
    }

    baseSelect.addEventListener('change', () => {
        preferredGroupId = '';
        renderGroupsForBase();
    });

    renderGroupsForBase();
}

function setupUserRegionalViewField() {
    const roleSelect = document.querySelector('[data-user-role-select]');
    const field = document.querySelector('[data-user-regional-field]');
    const scopeField = document.querySelector('[data-user-scope-field]');

    if (!(roleSelect instanceof HTMLSelectElement) || !(field instanceof HTMLElement)) {
        return;
    }

    const regionalSelect = field.querySelector('select[name="regional_view"]');
    const scopeSearch = scopeField instanceof HTMLElement ? scopeField.querySelector('[data-user-scope-search]') : null;
    const scopeSummary = scopeField instanceof HTMLElement ? scopeField.querySelector('[data-user-scope-summary]') : null;
    const scopeError = scopeField instanceof HTMLElement ? scopeField.querySelector('[data-user-scope-error]') : null;
    const scopeCheckboxes = scopeField instanceof HTMLElement
        ? Array.from(scopeField.querySelectorAll('[data-user-scope-checkbox]')).filter((checkbox) => checkbox instanceof HTMLInputElement)
        : [];
    const scopeOptions = scopeField instanceof HTMLElement
        ? Array.from(scopeField.querySelectorAll('[data-user-scope-option]')).filter((option) => option instanceof HTMLElement)
        : [];
    const form = roleSelect.closest('form');

    if (!(regionalSelect instanceof HTMLSelectElement)) {
        return;
    }

    function normalizeScopeSearchText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function hasScopedRole() {
        return roleSelect.value === 'BACKOFFICE' || roleSelect.value === 'BACKOFFICE SUPERVISOR';
    }

    function isPersonalizedView() {
        return hasScopedRole() && regionalSelect.value === 'PERSONALIZADO';
    }

    function checkedScopeCount() {
        return scopeCheckboxes.filter((checkbox) => checkbox.checked).length;
    }

    function updateScopeSummary() {
        if (!(scopeSummary instanceof HTMLElement)) {
            return;
        }

        const selectedCount = checkedScopeCount();
        scopeSummary.textContent = `${selectedCount} selecionado(s)`;
    }

    function updateScopeValidity() {
        const shouldValidate = isPersonalizedView();
        const hasSelection = checkedScopeCount() > 0;

        if (scopeField instanceof HTMLElement) {
            scopeField.classList.toggle('is-invalid', shouldValidate && !hasSelection);
        }

        if (scopeError instanceof HTMLElement) {
            scopeError.hidden = !shouldValidate || hasSelection;
        }

        scopeCheckboxes.forEach((checkbox, index) => {
            checkbox.setCustomValidity(shouldValidate && !hasSelection && index === 0
                ? 'Selecione ao menos um base grupo.'
                : ''
            );
        });

        return !shouldValidate || hasSelection;
    }

    function filterScopeOptions() {
        if (!(scopeSearch instanceof HTMLInputElement)) {
            return;
        }

        const query = normalizeScopeSearchText(scopeSearch.value);

        scopeOptions.forEach((option) => {
            const optionText = normalizeScopeSearchText(option.getAttribute('data-search-text') || option.textContent || '');
            option.hidden = query !== '' && !optionText.includes(query);
        });
    }

    function refreshRegionalField() {
        const shouldShow = hasScopedRole();

        field.hidden = !shouldShow;
        regionalSelect.required = shouldShow;

        if (!shouldShow) {
            regionalSelect.value = 'FULL';
        }

        if (scopeField instanceof HTMLElement) {
            scopeField.hidden = !isPersonalizedView();
        }

        if (!isPersonalizedView() && scopeSearch instanceof HTMLInputElement) {
            scopeSearch.value = '';
            filterScopeOptions();
        }

        updateScopeSummary();
        updateScopeValidity();
    }

    if (scopeSearch instanceof HTMLInputElement) {
        scopeSearch.addEventListener('input', filterScopeOptions);
        scopeSearch.addEventListener('keyup', filterScopeOptions);
        scopeSearch.addEventListener('search', filterScopeOptions);
    }

    scopeCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            updateScopeSummary();
            updateScopeValidity();
        });
    });

    roleSelect.addEventListener('change', refreshRegionalField);
    regionalSelect.addEventListener('change', refreshRegionalField);

    if (form instanceof HTMLFormElement) {
        form.addEventListener('submit', (event) => {
            refreshRegionalField();

            if (!updateScopeValidity()) {
                event.preventDefault();

                const firstVisibleCheckbox = scopeCheckboxes.find((checkbox) => {
                    const option = checkbox.closest('[data-user-scope-option]');

                    return !(option instanceof HTMLElement) || !option.hidden;
                });

                if (firstVisibleCheckbox instanceof HTMLInputElement) {
                    firstVisibleCheckbox.reportValidity();
                    firstVisibleCheckbox.focus();
                }
            }
        });
    }

    filterScopeOptions();
    refreshRegionalField();
}

function isValidCpf(value) {
    const cpf = String(value || '').replace(/\D+/g, '');

    if (cpf.length !== 11) {
        return false;
    }

    if (/^(\d)\1{10}$/.test(cpf)) {
        return false;
    }

    for (let digitIndex = 9; digitIndex < 11; digitIndex += 1) {
        let sum = 0;

        for (let position = 0; position < digitIndex; position += 1) {
            sum += Number(cpf[position]) * ((digitIndex + 1) - position);
        }

        const remainder = (sum * 10) % 11;
        const checkDigit = remainder === 10 ? 0 : remainder;

        if (checkDigit !== Number(cpf[digitIndex])) {
            return false;
        }
    }

    return true;
}

function setupCpfValidation() {
    document.querySelectorAll('[data-cpf-input]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const error = input.parentElement ? input.parentElement.querySelector('[data-cpf-error]') : null;
        const form = input.closest('form');

        function updateCpfState(showIncomplete = false) {
            const cpf = input.value.replace(/\D+/g, '');
            const isComplete = cpf.length === 11;
            const hasError = cpf !== '' && ((isComplete && !isValidCpf(cpf)) || (!isComplete && showIncomplete));

            input.classList.toggle('input-invalid', hasError);

            if (error instanceof HTMLElement) {
                error.hidden = !hasError;
            }

            input.setCustomValidity(hasError ? 'CPF inválido' : '');
        }

        input.addEventListener('input', () => {
            updateCpfState(false);
        });

        input.addEventListener('blur', () => {
            updateCpfState(input.value !== '');
        });

        if (form instanceof HTMLFormElement) {
            form.addEventListener('submit', () => {
                updateCpfState(input.value !== '');
            });
        }

        updateCpfState(false);
    });
}

function setupCepLookup() {
    const cepInput = document.querySelector('[data-cep-input]');
    const status = document.querySelector('[data-cep-status]');
    const addressFields = {
        logradouro: document.querySelector('[data-cep-field="logradouro"]'),
        bairro: document.querySelector('[data-cep-field="bairro"]'),
        cidade: document.querySelector('[data-cep-field="cidade"]'),
        uf: document.querySelector('[data-cep-field="uf"]'),
    };

    if (!(cepInput instanceof HTMLInputElement) || !(status instanceof HTMLElement)) {
        return;
    }

    let lastFetchedCep = '';

    function setStatus(message, type = '') {
        status.textContent = message;
        status.classList.remove('is-error', 'is-success');

        if (type !== '') {
            status.classList.add(type);
        }
    }

    async function fetchCep(forceLookup = false) {
        const cep = cepInput.value.replace(/\D+/g, '');

        if (cep.length === 0) {
            setStatus('Digite o CEP com 8 números para preencher o endereço.');
            lastFetchedCep = '';
            return;
        }

        if (cep.length !== 8) {
            setStatus('O CEP precisa ter 8 números.', 'is-error');
            return;
        }

        if (!forceLookup && cep === lastFetchedCep) {
            return;
        }

            setStatus('Buscando endereço pelo CEP...');

        try {
            const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`, {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Falha ao consultar o CEP.');
            }

            const payload = await response.json();

            if (payload.erro) {
                setStatus('CEP não encontrado. Preencha o endereço manualmente.', 'is-error');
                return;
            }

            if (addressFields.logradouro instanceof HTMLInputElement) {
                addressFields.logradouro.value = String(payload.logradouro || '').toLocaleUpperCase('pt-BR');
                addressFields.logradouro.dispatchEvent(new Event('input', { bubbles: true }));
                addressFields.logradouro.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (addressFields.bairro instanceof HTMLInputElement) {
                addressFields.bairro.value = String(payload.bairro || '').toLocaleUpperCase('pt-BR');
                addressFields.bairro.dispatchEvent(new Event('input', { bubbles: true }));
                addressFields.bairro.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (addressFields.cidade instanceof HTMLInputElement) {
                addressFields.cidade.value = String(payload.localidade || '').toLocaleUpperCase('pt-BR');
                addressFields.cidade.dispatchEvent(new Event('input', { bubbles: true }));
                addressFields.cidade.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (addressFields.uf instanceof HTMLInputElement) {
                addressFields.uf.value = String(payload.uf || '').toLocaleUpperCase('pt-BR');
                addressFields.uf.dispatchEvent(new Event('input', { bubbles: true }));
                addressFields.uf.dispatchEvent(new Event('change', { bubbles: true }));
            }

            lastFetchedCep = cep;
            setStatus('Endereço preenchido via CEP. Se precisar, ajuste manualmente.', 'is-success');
        } catch (error) {
            setStatus('Não foi possível consultar o CEP agora. Você pode preencher manualmente.', 'is-error');
        }
    }

    cepInput.addEventListener('blur', () => {
        fetchCep(true);
    });

    cepInput.addEventListener('change', () => {
        fetchCep(true);
    });

    const shouldLookupOnLoad = cepInput.value.replace(/\D+/g, '').length === 8
        && Object.values(addressFields).some((field) => field instanceof HTMLInputElement && field.value.trim() === '');

    if (shouldLookupOnLoad) {
        fetchCep(false);
    }
}

function setupAuditFlowSteps() {
    const root = document.querySelector('[data-audit-steps-root]');

    if (!(root instanceof HTMLFormElement)) {
        return;
    }

    const stepElements = Array.from(root.querySelectorAll('[data-audit-step]'));

    if (stepElements.length === 0) {
        return;
    }

    const steps = stepElements.map((element) => ({
        root: element,
        kind: element.getAttribute('data-step-kind') || '',
        toggle: element.querySelector('[data-step-toggle]'),
        body: element.querySelector('[data-step-body]'),
        state: element.querySelector('[data-step-state]'),
        icon: element.querySelector('[data-step-icon]'),
    })).filter((step) => (
        step.toggle instanceof HTMLButtonElement
        && step.body instanceof HTMLElement
        && step.state instanceof HTMLElement
        && step.icon instanceof HTMLElement
    ));

    if (steps.length === 0) {
        return;
    }

    const summaryRequiredFields = [
        root.querySelector('[data-summary-form-input="customer_birth_date"]'),
        root.querySelector('[data-summary-form-input="customer_mother_name"]'),
    ].filter((field) => field instanceof HTMLInputElement);

    const summaryCardsByField = new Map(
        Array.from(document.querySelectorAll('[data-summary-card][data-summary-field]')).map((card) => [
            card.getAttribute('data-summary-field') || '',
            card,
        ])
    );

    function requiredFields(step) {
        return Array.from(step.root.querySelectorAll('[data-step-required]')).filter((field) => (
            field instanceof HTMLInputElement
            || field instanceof HTMLSelectElement
            || field instanceof HTMLTextAreaElement
        ));
    }

    function productSelects(step) {
        return Array.from(step.root.querySelectorAll('[data-product-select]')).filter((field) => (
            field instanceof HTMLSelectElement
        ));
    }

    function additionalProductSelects(step) {
        return productSelects(step).filter((field) => field.dataset.productGroup === 'additional');
    }

    function productSelectByGroup(step, groupName) {
        return productSelects(step).find((field) => field.dataset.productGroup === groupName) || null;
    }

    function normalizeProductMatchName(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s*\([^)]*\)/g, '')
            .toUpperCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function resolveExpectedMobileProductName(fixedProductName) {
        return normalizeProductMatchName(fixedProductName).replace(/\bFIXA\b/g, 'MOVEL');
    }

    function updateProductTotalSummary(step) {
        if (step.kind !== 'products') {
            return;
        }

        const totalValueElement = step.root.querySelector('[data-product-total-value]');

        if (!(totalValueElement instanceof HTMLElement)) {
            return;
        }

        const total = productSelects(step).reduce((carry, select) => {
            const selectedOption = select.selectedOptions[0];

            if (!(selectedOption instanceof HTMLOptionElement) || selectedOption.value.trim() === '') {
                return carry;
            }

            const optionValue = Number.parseFloat(selectedOption.dataset.managerialValue || '0');

            return carry + (Number.isFinite(optionValue) ? optionValue : 0);
        }, 0);

        totalValueElement.textContent = new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        }).format(total);
    }

    function updateAdditionalProductsMeta(step) {
        if (step.kind !== 'products') {
            return;
        }

        const selectedCountElement = step.root.querySelector('[data-additional-products-count]');
        const addButton = step.root.querySelector('[data-add-product-row]');
        const additionalCount = additionalProductSelects(step).length;
        const selectedCount = additionalProductSelects(step).filter((field) => field.value.trim() !== '').length;

        if (selectedCountElement instanceof HTMLElement) {
            selectedCountElement.textContent = `${selectedCount}/10 selecionados`;
        }

        if (addButton instanceof HTMLButtonElement) {
            addButton.disabled = additionalCount >= 10;
        }
    }

    function syncProductSelectState(step) {
        if (step.kind !== 'products') {
            return;
        }

        const selects = productSelects(step);
        const fixedSelect = productSelectByGroup(step, 'fixed-data');
        const mobileSelect = productSelectByGroup(step, 'mobile');
        const selectedValues = selects
            .map((field) => field.value.trim())
            .filter((value) => value !== '');
        const selectedCounts = new Map();
        const fixedSelectedOption = fixedSelect instanceof HTMLSelectElement ? fixedSelect.selectedOptions[0] : null;
        const fixedSelectedName = fixedSelectedOption instanceof HTMLOptionElement
            ? String(fixedSelectedOption.dataset.productName || fixedSelectedOption.textContent || '')
            : '';
        const requiresVivoTotalOnMobile = fixedSelectedOption instanceof HTMLOptionElement
            && fixedSelectedOption.value.trim() !== ''
            && String(fixedSelectedOption.dataset.vivoTotal || '').trim().toUpperCase() === 'TOTAL';
        const expectedMobileProductName = requiresVivoTotalOnMobile
            ? resolveExpectedMobileProductName(fixedSelectedName)
            : '';

        selectedValues.forEach((value) => {
            selectedCounts.set(value, (selectedCounts.get(value) || 0) + 1);
        });

        selects.forEach((select) => {
            Array.from(select.options).forEach((option) => {
                if (option.value === '') {
                    option.disabled = false;
                    return;
                }

                const duplicatedInOtherField = option.value !== select.value && selectedCounts.has(option.value);
                const optionName = normalizeProductMatchName(option.dataset.productName || option.textContent || '');
                const optionIsVivoTotal = String(option.dataset.vivoTotal || '').trim().toUpperCase() === 'TOTAL';
                const blockedByVivoTotalRule = mobileSelect === select
                    && requiresVivoTotalOnMobile
                    && (
                        !optionIsVivoTotal
                        || (expectedMobileProductName !== '' && optionName !== expectedMobileProductName)
                    );

                option.hidden = blockedByVivoTotalRule;
                option.disabled = duplicatedInOtherField || blockedByVivoTotalRule;
            });

            const currentValue = select.value.trim();
            const isDuplicate = currentValue !== '' && (selectedCounts.get(currentValue) || 0) > 1;
                select.setCustomValidity(isDuplicate ? 'Este produto já foi selecionado em outra linha.' : '');
            select.classList.toggle('input-invalid', isDuplicate);
        });

        if (mobileSelect instanceof HTMLSelectElement) {
            const mobileSelectedOption = mobileSelect.selectedOptions[0];
            const mobileIsInvalidForVivoTotal = requiresVivoTotalOnMobile
                && mobileSelectedOption instanceof HTMLOptionElement
                && mobileSelectedOption.value.trim() !== ''
                && (
                    String(mobileSelectedOption.dataset.vivoTotal || '').trim().toUpperCase() !== 'TOTAL'
                    || (
                        expectedMobileProductName !== ''
                        && normalizeProductMatchName(mobileSelectedOption.dataset.productName || mobileSelectedOption.textContent || '') !== expectedMobileProductName
                    )
                );

            if (mobileIsInvalidForVivoTotal) {
                mobileSelect.value = '';
            }
        }

        updateProductTotalSummary(step);
        updateAdditionalProductsMeta(step);
    }

    function duplicateProductSelect(step) {
        return productSelects(step).find((field) => {
            const currentValue = field.value.trim();

            if (currentValue === '') {
                return false;
            }

            return productSelects(step).filter((select) => select.value.trim() === currentValue).length > 1;
        }) || null;
    }

    function isFieldFilled(field) {
        if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
            return true;
        }

        if (field.disabled) {
            return true;
        }

        return field.value.trim() !== '';
    }

    function isProductsComplete(step) {
        return productSelects(step).some((field) => field.value.trim() !== '') && duplicateProductSelect(step) === null;
    }

    function isSummaryComplete() {
        return summaryRequiredFields.every(isFieldFilled);
    }

    function focusSummaryError() {
        const firstMissingField = summaryRequiredFields.find((field) => !isFieldFilled(field));

        if (!(firstMissingField instanceof HTMLInputElement)) {
            return;
        }

        const summaryCard = summaryCardsByField.get(firstMissingField.name);

        if (summaryCard instanceof HTMLElement) {
            summaryCard.click();
            return;
        }

        firstMissingField.reportValidity();
    }

    function isStepComplete(step) {
        if (step.kind === 'products') {
            return isProductsComplete(step);
        }

        return requiredFields(step).every(isFieldFilled);
    }

    function setStepOpen(step, shouldOpen) {
        step.root.classList.toggle('is-open', shouldOpen);
        step.body.hidden = !shouldOpen;
        step.toggle.setAttribute('aria-expanded', String(shouldOpen));
        step.icon.textContent = shouldOpen ? '\u2212' : '+';
    }

    function clearStepCustomValidity(step) {
        requiredFields(step).forEach((field) => {
            field.setCustomValidity('');
        });

        productSelects(step).forEach((field) => {
            field.setCustomValidity('');
        });
    }

    function setActiveStep(index) {
        steps.forEach((step, stepIndex) => {
            setStepOpen(step, stepIndex === index);
        });
    }

    function stepStatusLabel(isUnlocked, isComplete) {
        if (!isUnlocked) {
            return 'Bloqueada';
        }

        if (isComplete) {
            return 'Concluída';
        }

        return 'Pendente';
    }

    function focusStepError(step) {
        const invalidField = requiredFields(step).find((field) => !isFieldFilled(field));

        if (invalidField) {
            if (invalidField instanceof HTMLInputElement && invalidField.type === 'hidden') {
                const fallbackField = step.root.querySelector('input:not([type="hidden"]), select, textarea');

                if (
                    fallbackField instanceof HTMLInputElement
                    || fallbackField instanceof HTMLSelectElement
                    || fallbackField instanceof HTMLTextAreaElement
                ) {
                    fallbackField.setCustomValidity('Complete esta etapa antes de continuar.');
                    fallbackField.reportValidity();
                    fallbackField.focus();
                }

                return;
            }

            invalidField.reportValidity();
            invalidField.focus();
            return;
        }

        if (step.kind === 'products') {
            const duplicateSelect = duplicateProductSelect(step);

            if (duplicateSelect instanceof HTMLSelectElement) {
            duplicateSelect.setCustomValidity('Este produto já foi selecionado em outra linha.');
                duplicateSelect.reportValidity();
                duplicateSelect.focus();
                return;
            }

            const firstSelect = productSelects(step)[0];

            if (firstSelect instanceof HTMLSelectElement) {
                firstSelect.setCustomValidity('Selecione ao menos um produto.');
                firstSelect.reportValidity();
                firstSelect.focus();
            }
        }
    }

    function refreshSteps(preferredOpenIndex = null) {
        const summaryComplete = isSummaryComplete();
        const completionMap = steps.map((step) => isStepComplete(step));
        const unlockMap = steps.map((step, index) => (
            index === 0 ? summaryComplete : summaryComplete && completionMap.slice(0, index).every(Boolean)
        ));

        steps.forEach((step, index) => {
            const isUnlocked = unlockMap[index];
            const isComplete = completionMap[index];

            step.root.classList.toggle('is-locked', !isUnlocked);
            step.root.classList.toggle('is-unlocked', isUnlocked);
            step.root.classList.toggle('is-complete', isComplete);
            step.toggle.disabled = !isUnlocked;
            step.toggle.setAttribute('aria-disabled', String(!isUnlocked));
            step.state.textContent = stepStatusLabel(isUnlocked, isComplete);

            if (!isUnlocked) {
                setStepOpen(step, false);
            }

            clearStepCustomValidity(step);
            syncProductSelectState(step);
        });

        if (!summaryComplete) {
            steps.forEach((step) => setStepOpen(step, false));
            return;
        }

        let openIndex = steps.findIndex((step) => step.root.classList.contains('is-open') && !step.body.hidden);

        if (preferredOpenIndex !== null && unlockMap[preferredOpenIndex]) {
            openIndex = preferredOpenIndex;
        }

        if (openIndex === -1 || !unlockMap[openIndex]) {
            openIndex = steps.findIndex((step, index) => unlockMap[index] && !completionMap[index]);
        }

        if (openIndex === -1) {
            openIndex = steps.map((step, index) => unlockMap[index] ? index : -1).filter((index) => index >= 0).pop() ?? 0;
        }

        setActiveStep(openIndex);
    }

    steps.forEach((step, index) => {
        step.toggle.addEventListener('click', () => {
            if (step.toggle.disabled) {
                return;
            }

            const isOpen = step.root.classList.contains('is-open') && !step.body.hidden;

            if (isOpen) {
                setStepOpen(step, false);
                return;
            }

            refreshSteps(index);
        });
    });

    root.addEventListener('input', () => {
        refreshSteps();
    });

    root.addEventListener('change', () => {
        refreshSteps();
    });

    root.addEventListener('submit', (event) => {
        refreshSteps();

        if (!isSummaryComplete()) {
            event.preventDefault();
            focusSummaryError();
            return;
        }

        const firstIncompleteIndex = steps.findIndex((step) => !isStepComplete(step));

        if (firstIncompleteIndex === -1) {
            return;
        }

        event.preventDefault();
        refreshSteps(firstIncompleteIndex);
        focusStepError(steps[firstIncompleteIndex]);
    });

    document.addEventListener('auditflow:refresh', () => {
        refreshSteps();
    });

    refreshSteps();
}

async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
}

applyThemeState(window.localStorage.getItem(THEME_STORAGE_KEY) === 'dark' || rootElement.classList.contains('theme-dark'));
applySidebarState(window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1');
applySidebarMiniState(window.localStorage.getItem(SIDEBAR_MINI_STORAGE_KEY) === '1');
setupCustomTooltips();
setupFileInputFields();
setupDateRangePickers();
setupCollapsiblePanels();
setupOperationsManager();
setupHierarchyExportModal();
setupHierarchyDeleteModal();
setupProductDuplicateModal();
setupQueuePrioritizeModal();
setupDashboardExecutiveLive();
setupQueueLiveRefresh();
setupQueueSearchToggle();
setupQueueStatusFilter();
setupQueueCustomerTypeFilter();
setupQueueModalityFilter();
setupQueueOperationFilter();
setupHierarchyBaseFilter();
setupHierarchyRoleFilter();
setupReportsChecklistFilters();
setupReportsCalendarState();
setupReportsTableExports();
setupReportsAutoSubmit();
setupHierarchySellerPanelPersistence();
setupHierarchyRegionalFilter();
setupConsultantPicker();
setupSummaryCardModal();
setupInputMasks();
setupHierarchyBaseGroupSelect();
setupUserRegionalViewField();
setupCpfValidation();
setupFirstAccessPasswordForm();
setupCepLookup();
setupAuditFlowSteps();

document.addEventListener('click', async (event) => {
    const themeToggleButton = event.target.closest('[data-theme-toggle]');

    if (themeToggleButton) {
        const isDarkTheme = !rootElement.classList.contains('theme-dark');
        applyThemeState(isDarkTheme);
        window.localStorage.setItem(THEME_STORAGE_KEY, isDarkTheme ? 'dark' : 'light');
        return;
    }

    const toggleButton = event.target.closest('[data-sidebar-toggle]');

    if (toggleButton) {
        const collapsed = !body.classList.contains('sidebar-collapsed');
        applySidebarState(collapsed);
        window.localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? '1' : '0');
        return;
    }

    const miniToggleButton = event.target.closest('[data-sidebar-mini-toggle]');

    if (miniToggleButton) {
        const collapsed = !body.classList.contains('sidebar-mini-collapsed');
        applySidebarMiniState(collapsed);
        window.localStorage.setItem(SIDEBAR_MINI_STORAGE_KEY, collapsed ? '1' : '0');
        return;
    }

    const dashboardFullscreenButton = event.target.closest('[data-dashboard-fullscreen-target]');

    if (dashboardFullscreenButton instanceof HTMLElement) {
        const targetId = dashboardFullscreenButton.getAttribute('data-dashboard-fullscreen-target') || '';
        const target = targetId !== '' ? document.getElementById(targetId) : null;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (document.fullscreenElement === target) {
            document.exitFullscreen?.();
            return;
        }

        target.requestFullscreen?.();
        return;
    }

    const copyButton = event.target.closest('[data-copy-text]');

    if (copyButton) {
        try {
            await copyText(copyButton.getAttribute('data-copy-text') || '');
            showCopyToast('Código copiado', copyButton);
        } catch (error) {
            showCopyToast('Não foi possível copiar', copyButton);
        }
        return;
    }

    const addButton = event.target.closest('[data-add-product-row]');

    if (addButton) {
        const rowsContainer = document.querySelector('[data-product-rows]');
        const template = document.querySelector('#product-row-template');
        const rows = rowsContainer ? rowsContainer.querySelectorAll('.product-row') : [];

        if (rowsContainer && template instanceof HTMLTemplateElement && rows.length < 10) {
            rowsContainer.appendChild(template.content.cloneNode(true));
            document.dispatchEvent(new CustomEvent('auditflow:refresh'));
        }
        return;
    }

    const removeButton = event.target.closest('[data-remove-product-row]');

    if (removeButton) {
        const rowsContainer = document.querySelector('[data-product-rows]');
        const rows = rowsContainer ? rowsContainer.querySelectorAll('.product-row') : [];
        const row = removeButton.closest('.product-row');

        if (row && rows.length > 1) {
            row.remove();
        } else if (row) {
            const select = row.querySelector('[data-product-select]');

            if (select instanceof HTMLSelectElement) {
                select.value = '';
            }
        }

        document.dispatchEvent(new CustomEvent('auditflow:refresh'));

        return;
    }

    const logToggleButton = event.target.closest('[data-toggle-sale-log]');

    if (logToggleButton) {
        const panel = document.querySelector('[data-sale-log-panel]');

        if (!(panel instanceof HTMLElement)) {
            return;
        }

        const isHidden = panel.hasAttribute('hidden');
        panel.toggleAttribute('hidden', !isHidden);
        logToggleButton.setAttribute('aria-expanded', String(isHidden));
        logToggleButton.textContent = isHidden ? 'Ocultar log' : 'Log da venda';
        return;
    }

    const commentToggleButton = event.target.closest('[data-toggle-sale-comment]');

    if (commentToggleButton) {
        const panel = document.querySelector('[data-sale-comment-panel]');

        if (!(panel instanceof HTMLElement)) {
            return;
        }

        const isHidden = panel.hasAttribute('hidden');
        panel.toggleAttribute('hidden', !isHidden);
        commentToggleButton.setAttribute('aria-expanded', String(isHidden));
        commentToggleButton.textContent = isHidden ? 'Ocultar coment\u00E1rio' : 'Adicionar coment\u00E1rio';
        return;
    }

    const recalcOpenButton = event.target.closest('[data-product-recalc-open]');

    if (recalcOpenButton) {
        toggleProductRecalculationPanel(true);
        return;
    }

    const recalcCancelButton = event.target.closest('[data-product-recalc-cancel]');

    if (recalcCancelButton) {
        toggleProductRecalculationPanel(false);
    }
});
