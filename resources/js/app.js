import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

// ===== Leaflet via Vite =====
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

window.L = L;

import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

import 'leaflet-polylinedecorator';

// ===== Script Anda =====
import './tab-manager';
import './upload-initializer';
import './permission-handler';
import './hierarchy-handler';
import './settings-handler';
import './mapping-handler';
import './matrix-handler';

// ===== Alpine =====
Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();
