import '../css/search/autocomplete.css';
import { mountSearchIcons } from './search/icons';
import { searchAutocomplete } from './search/autocomplete';

document.addEventListener('livewire:navigated', mountSearchIcons);
document.addEventListener('livewire:init', () => {
    window.Alpine.data('searchAutocomplete', searchAutocomplete);
    window.Livewire.hook('morphed', () => mountSearchIcons());
});
