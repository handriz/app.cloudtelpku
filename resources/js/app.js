import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

import './tab-manager';
import './upload-initializer.js';
import './permission-handler';
import './matrix-handler';
import './hierarchy-handler';
import './settings-handler';
import './mapping-handler';

Alpine.plugin(collapse);
window.Alpine = Alpine;



Alpine.start();
