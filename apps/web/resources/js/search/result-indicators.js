function closeIndicators(except = null) {
    document.querySelectorAll('[data-search-result-indicator].is-open').forEach((indicator) => {
        if (indicator !== except) {
            indicator.classList.remove('is-open');
        }
    });
}

export function mountResultIndicators() {
    const indicators = document.querySelectorAll('[data-search-result-indicator]');

    if (! indicators.length) {
        return;
    }

    indicators.forEach((indicator) => {
        indicator.addEventListener('click', (event) => {
            event.stopPropagation();

            const shouldOpen = ! indicator.classList.contains('is-open');
            closeIndicators(indicator);
            indicator.classList.toggle('is-open', shouldOpen);
        });
    });

    document.addEventListener('click', () => closeIndicators());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeIndicators();
        }
    });
}
