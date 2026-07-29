function selectedValueFromUrl() {
    return new URLSearchParams(window.location.search).get('popular') || '';
}

function setUrlValue(value) {
    const url = new URL(window.location.href);

    if (value) {
        url.searchParams.set('popular', value);
    } else {
        url.searchParams.delete('popular');
    }

    window.history.pushState({}, '', url);
}

function syncButtons(container, selectedValue) {
    container.querySelectorAll('[data-popular-filter]').forEach((button) => {
        const isActive = button.dataset.value === selectedValue;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

function syncInput(input, selectedValue) {
    if (!input) {
        return;
    }

    input.value = selectedValue;
    input.disabled = selectedValue === '';
}

export function mountPopularFilters() {
    const container = document.querySelector('[data-popular-filters]');
    const input = document.querySelector('[data-popular-filter-input]');

    if (!container) {
        return;
    }

    const sync = (selectedValue) => {
        syncButtons(container, selectedValue);
        syncInput(input, selectedValue);
    };

    sync(selectedValueFromUrl());

    container.querySelectorAll('[data-popular-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            const value = button.dataset.value || '';
            const selectedValue = button.getAttribute('aria-pressed') === 'true' ? '' : value;

            setUrlValue(selectedValue);
            sync(selectedValue);
        });
    });

    window.addEventListener('popstate', () => {
        sync(selectedValueFromUrl());
    });
}
