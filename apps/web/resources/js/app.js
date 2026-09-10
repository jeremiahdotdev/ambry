import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import '../css/search/autocomplete.css';
import { mountSearchIcons } from './search/icons';

// This entry is loaded once; navigation and morph hooks handle subsequent pages.
Alpine.data('searchAutocomplete', () => ({
    open: false,
    active: -1,
    move(direction) {
        const options = this.$refs.list.children;
        if (!options.length) return;
        this.open = true;
        this.active = this.active < 0
            ? (direction > 0 ? 0 : options.length - 1)
            : (this.active + direction + options.length) % options.length;
        options[this.active].scrollIntoView({ block: 'nearest' });
    },
    choose(event) {
        if (event.isComposing) return;
        const option = this.$refs.list.children[this.active];
        if (this.open && option) {
            event.preventDefault();
            Livewire.navigate(option.dataset.url);
        }
        this.open = false;
        this.active = -1;
    },
}));

document.addEventListener('livewire:navigated', mountSearchIcons);
document.addEventListener('livewire:init', () => {
    Livewire.hook('morphed', () => mountSearchIcons());
});

Livewire.start();
