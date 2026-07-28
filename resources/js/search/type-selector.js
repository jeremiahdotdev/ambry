function mountTypeSelector(selector, closeHandlers) {
    const button = selector.querySelector('[data-search-type-button]');
    const menu = selector.querySelector('[data-search-type-menu]');
    const input = selector.querySelector('[data-search-type-input]');
    const label = selector.querySelector('[data-search-type-label]');
    const options = selector.querySelectorAll('[data-search-type-option]');
    const form = input?.form;
    const searchInput = form?.querySelector('input[type="search"]');
    const searchLabel = form?.querySelector('label');

    if (!button || !menu || !input || !label) {
        return;
    }

    const updateSearchCopy = (option) => {
        if (!option) {
            return;
        }

        const plural = option.dataset.plural || 'saints';

        if (searchInput) {
            searchInput.placeholder = `Search ${plural}...`;
        }

        if (searchLabel) {
            searchLabel.textContent = `Search ${plural}`;
        }
    };

    const close = () => {
        menu.hidden = true;
        button.setAttribute('aria-expanded', 'false');
    };

    const open = () => {
        menu.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        menu.querySelector('[aria-selected="true"]')?.focus();
    };

    button.addEventListener('click', () => {
        menu.hidden ? open() : close();
    });

    options.forEach((option) => {
        option.addEventListener('click', () => {
            input.value = option.dataset.value || '';
            label.textContent = option.textContent.trim();
            updateSearchCopy(option);

            options.forEach((item) => item.setAttribute('aria-selected', item === option ? 'true' : 'false'));
            close();
            button.focus();
        });
    });

    closeHandlers.set(selector, close);

    selector.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            button.focus();
        }
    });
}

export function mountTypeSelectors() {
    const closeHandlers = new Map();

    document.querySelectorAll('[data-search-type-selector]').forEach((selector) => {
        mountTypeSelector(selector, closeHandlers);
    });

    document.addEventListener('click', (event) => {
        closeHandlers.forEach((close, selector) => {
            if (!selector.contains(event.target)) {
                close();
            }
        });
    });
}
