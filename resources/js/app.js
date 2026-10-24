// Dashboard helpers. Leaflet loads from a CDN only when a page has a map picker.

let leafletPromise = null;

function loadLeaflet() {
    if (window.L) {
        return Promise.resolve(window.L);
    }

    leafletPromise ??= new Promise((resolve, reject) => {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
        css.integrity = 'sha384-c6Rcwz4e4CITMbu/NBmnNS8yN2sC3cUElMEMfP3vqqKFp7GOYaaBBCqmaWBjmkjb';
        css.crossOrigin = 'anonymous';
        document.head.append(css);

        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
        script.integrity = 'sha384-NElt3Op+9NBMCYaef5HxeJmU4Xeard/Lku8ek6hoPTvYkQPh3zLIrJP7KiRocsxO';
        script.crossOrigin = 'anonymous';
        script.onload = () => resolve(window.L);
        script.onerror = reject;
        document.head.append(script);
    });

    return leafletPromise;
}

/**
 * Alpine component for picking a home's position on a map.
 * Used inside wire:ignore, so it writes back to Livewire through $wire.
 */
window.locationPicker = ({ lat, lng, cityLat, cityLng }) => ({
    map: null,
    marker: null,
    lat: lat ? Number(lat) : null,
    lng: lng ? Number(lng) : null,

    get hasPin() {
        return this.lat !== null && this.lng !== null;
    },

    get label() {
        return this.hasPin ? `${this.lat.toFixed(6)}, ${this.lng.toFixed(6)}` : 'No pin yet';
    },

    init() {
        loadLeaflet().then((L) => {
            const start = this.hasPin ? [this.lat, this.lng] : [Number(cityLat) || 39.5, Number(cityLng) || -98.35];
            this.map = L.map(this.$refs.map).setView(start, this.hasPin ? 16 : (cityLat ? 12 : 4));
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            }).addTo(this.map);

            if (this.hasPin) {
                this.place(this.lat, this.lng);
            }

            this.map.on('click', (event) => this.place(event.latlng.lat, event.latlng.lng));
            new ResizeObserver(() => this.map.invalidateSize()).observe(this.$refs.map);
        });
    },

    place(newLat, newLng) {
        this.lat = Math.round(newLat * 1e7) / 1e7;
        this.lng = Math.round(newLng * 1e7) / 1e7;

        if (this.marker) {
            this.marker.setLatLng([this.lat, this.lng]);
        } else {
            this.marker = window.L.marker([this.lat, this.lng], { draggable: true }).addTo(this.map);
            this.marker.on('dragend', () => {
                const position = this.marker.getLatLng();
                this.place(position.lat, position.lng);
            });
        }

        this.$wire.set('latitude', String(this.lat), false);
        this.$wire.set('longitude', String(this.lng), false);
    },

    clear() {
        this.marker?.remove();
        this.marker = null;
        this.lat = null;
        this.lng = null;
        this.$wire.set('latitude', '', false);
        this.$wire.set('longitude', '', false);
    },

    center(detail) {
        const data = Array.isArray(detail) ? detail[0] : detail;

        if (this.map && data && ! this.hasPin) {
            this.map.setView([data.lat, data.lng], 12);
        }
    },
});
