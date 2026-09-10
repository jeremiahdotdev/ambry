import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import '../css/search/autocomplete.css';
import { mountSearchIcons } from './search/icons';

import { searchAutocomplete } from './search/autocomplete';

Alpine.data('searchAutocomplete', searchAutocomplete);

document.addEventListener('livewire:navigated', mountSearchIcons);
document.addEventListener('livewire:init', () => {
    Livewire.hook('morphed', () => mountSearchIcons());
});

Livewire.start();
