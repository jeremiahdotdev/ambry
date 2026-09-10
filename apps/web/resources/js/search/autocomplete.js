const cache = new Map();
const cacheLifetime = 60_000;

export function searchAutocomplete() {
    let timer;
    let request;
    let version = 0;

    return {
        open: false,
        active: -1,
        suggestions: [],
        currentQuery: '',
        resolvedQuery: null,
        init() {
            this.currentQuery = this.$wire.query;
            this.$watch('$wire.type', () => this.refresh());
            this.$watch('$wire.popular', () => this.refresh());
            if (this.currentQuery.trim().length >= 2) this.refresh();
        },
        destroy() {
            clearTimeout(timer);
            request?.abort();
            version++;
        },
        get showSuggestions() {
            return this.open && this.currentQuery === this.resolvedQuery && this.suggestions.length > 0;
        },
        refresh() {
            clearTimeout(timer);
            request?.abort();
            const revision = ++version;
            this.active = -1;
            this.resolvedQuery = null;
            const query = this.currentQuery;
            if (query.trim().length < 2) {
                this.suggestions = [];
                return;
            }
            const url = new URL(this.$root.dataset.url, location.origin);
            url.search = new URLSearchParams({ q: query, type: this.$wire.type, popular: this.$wire.popular });
            const key = url.href;
            const apply = (suggestions) => {
                if (version !== revision) return;
                this.suggestions = suggestions;
                this.resolvedQuery = query;
            };
            const saved = cache.get(key);
            if (saved && Date.now() - saved.time < cacheLifetime) {
                apply(saved.suggestions);
                return;
            }
            timer = setTimeout(async () => {
                const controller = new AbortController();
                request = controller;
                try {
                    const response = await fetch(url, {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) return;
                    const { suggestions } = await response.json();
                    if (controller.signal.aborted) return;
                    if (cache.size >= 50) cache.delete(cache.keys().next().value);
                    cache.set(key, { suggestions, time: Date.now() });
                    apply(suggestions);
                } catch {
                    // A failed suggestion request leaves regular form submission available.
                }
            }, 300);
        },
        input(event) {
            this.currentQuery = event.target.value;
            this.open = !event.isComposing;
            if (!event.isComposing) this.refresh();
        },
        move(direction) {
            if (this.currentQuery !== this.resolvedQuery || !this.suggestions.length) return;
            this.open = true;
            this.active = this.active < 0
                ? (direction > 0 ? 0 : this.suggestions.length - 1)
                : (this.active + direction + this.suggestions.length) % this.suggestions.length;
            this.$nextTick(() => this.$refs.list.querySelectorAll('[role="option"]')[this.active]?.scrollIntoView({ block: 'nearest' }));
        },
        choose(event) {
            if (event.isComposing) return;
            if (this.showSuggestions && this.active >= 0) {
                event.preventDefault();
                window.Livewire.navigate(this.suggestions[this.active].url);
            }
            this.open = false;
            this.active = -1;
        },
    };
}
