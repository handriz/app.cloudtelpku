import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

import './tab-manager';
import './upload-initializer.js';

Alpine.plugin(collapse);
window.Alpine = Alpine;



Alpine.start();
