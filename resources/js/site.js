// Interactions for the public website. Dependency-free; Leaflet loads from a CDN only on pages with a map.

const root = document.documentElement;
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content
    || decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) || [])[1] || '');

// ---------- Light / dark switch, remembered on this device ----------
document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const current = root.dataset.theme
            || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        const next = current === 'dark' ? 'light' : 'dark';

        root.dataset.theme = next;

        try {
            localStorage.setItem('hr-theme', next);
        } catch (error) {
            // Storage can be blocked; the switch still works for this page.
        }
    });
});

// ---------- Mobile menu ----------
const menuButton = document.querySelector('[data-menu-toggle]');

const setMenu = (open) => {
    root.classList.toggle('menu-open', open);
    menuButton?.setAttribute('aria-expanded', String(open));
    menuButton?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
};

menuButton?.addEventListener('click', () => setMenu(! root.classList.contains('menu-open')));
document.querySelectorAll('.mobile-menu a').forEach((link) => link.addEventListener('click', () => setMenu(false)));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setMenu(false);
    }
});

// ---------- Save / unsave a home (works inside Livewire-rendered lists too) ----------
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-favorite]');

    if (! button) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    if (button.dataset.busy) {
        return;
    }

    button.dataset.busy = '1';
    const wasSaved = button.getAttribute('aria-pressed') === 'true';
    setFavorite(button.dataset.favorite, ! wasSaved);

    try {
        const response = await fetch(button.dataset.favorite, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (response.status === 401 || response.status === 419) {
            window.location.href = '/login';

            return;
        }

        const data = await response.json();
        setFavorite(button.dataset.favorite, Boolean(data.saved));
    } catch (error) {
        setFavorite(button.dataset.favorite, wasSaved);
    } finally {
        delete button.dataset.busy;
    }
});

function setFavorite(url, saved) {
    document.querySelectorAll(`[data-favorite="${CSS.escape(url)}"]`).forEach((button) => {
        const label = saved ? 'Remove from saved homes' : 'Save this home';
        button.setAttribute('aria-pressed', String(saved));
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.querySelector('[data-favorite-text]')?.replaceChildren(saved ? 'Saved' : 'Save');
    });
}

// ---------- Scroll reveal ----------
const reveals = document.querySelectorAll('.reveal');

if ('IntersectionObserver' in window && ! reduceMotion) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px' });

    reveals.forEach((element) => observer.observe(element));
} else {
    reveals.forEach((element) => element.classList.add('is-visible'));
}

// ---------- Photo gallery with tap-to-enlarge ----------
const lightbox = document.querySelector('[data-lightbox]');

if (lightbox) {
    const photos = JSON.parse(lightbox.dataset.photos || '[]');
    const image = lightbox.querySelector('[data-lightbox-image]');
    const counter = lightbox.querySelector('[data-lightbox-counter]');
    const strip = lightbox.querySelector('[data-lightbox-strip]');
    let index = 0;

    photos.forEach((photo, photoIndex) => {
        const thumb = document.createElement('button');
        thumb.type = 'button';
        thumb.setAttribute('aria-label', `Photo ${photoIndex + 1}`);
        thumb.innerHTML = `<img src="${photo.small}" alt="" loading="lazy">`;
        thumb.addEventListener('click', () => show(photoIndex));
        strip.append(thumb);
    });

    const show = (next) => {
        index = (next + photos.length) % photos.length;
        image.src = photos[index].large;
        image.alt = `${lightbox.dataset.title} – photo ${index + 1}`;
        counter.textContent = `${index + 1} / ${photos.length}`;
        strip.querySelectorAll('button').forEach((button, buttonIndex) => {
            button.setAttribute('aria-current', String(buttonIndex === index));
            if (buttonIndex === index) {
                button.scrollIntoView({ block: 'nearest', inline: 'center' });
            }
        });
        // Load the next photo early so swiping feels instant.
        new Image().src = photos[(index + 1) % photos.length].large;
    };

    document.querySelectorAll('[data-open-photo]').forEach((button) => {
        button.addEventListener('click', () => {
            lightbox.showModal();
            show(Number(button.dataset.openPhoto) || 0);
        });
    });

    lightbox.querySelector('[data-lightbox-prev]').addEventListener('click', () => show(index - 1));
    lightbox.querySelector('[data-lightbox-next]').addEventListener('click', () => show(index + 1));
    lightbox.querySelector('[data-lightbox-close]').addEventListener('click', () => lightbox.close());
    lightbox.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
            show(index - 1);
        } else if (event.key === 'ArrowRight') {
            show(index + 1);
        }
    });

    let touchX = null;
    lightbox.addEventListener('touchstart', (event) => { touchX = event.touches[0].clientX; }, { passive: true });
    lightbox.addEventListener('touchend', (event) => {
        if (touchX === null) {
            return;
        }

        const distance = event.changedTouches[0].clientX - touchX;

        if (Math.abs(distance) > 40) {
            show(index + (distance < 0 ? 1 : -1));
        }

        touchX = null;
    });
}

// ---------- Mortgage / monthly payment calculator ----------
document.querySelectorAll('[data-calculator]').forEach((calculator) => {
    const field = (name) => calculator.querySelector(`[name="${name}"]`);
    const output = (name) => calculator.querySelector(`[data-out="${name}"]`);
    const money = (value) => '$' + Math.round(value).toLocaleString('en-US');
    const number = (input) => Number(String(input.value).replace(/[^0-9.]/g, '')) || 0;
    let lastChanged = 'percent';

    const update = () => {
        const price = number(field('price'));

        if (lastChanged === 'percent') {
            field('down').value = Math.round(price * number(field('down_percent')) / 100).toLocaleString('en-US');
        } else if (lastChanged === 'down' && price > 0) {
            field('down_percent').value = Math.min(100, Math.round(number(field('down')) / price * 1000) / 10);
        }

        const loan = Math.max(0, price - number(field('down')));
        const monthlyRate = number(field('rate')) / 100 / 12;
        const months = number(field('term')) * 12;
        const principal = months === 0 ? 0 : (monthlyRate === 0 ? loan / months : loan * monthlyRate / (1 - Math.pow(1 + monthlyRate, -months)));
        const tax = number(field('tax')) / 12;
        const insurance = number(field('insurance')) / 12;
        const hoa = number(field('hoa'));
        const total = principal + tax + insurance + hoa;

        output('total').textContent = money(total);
        output('principal').textContent = money(principal);
        output('tax').textContent = money(tax);
        output('insurance').textContent = money(insurance);
        output('hoa').textContent = money(hoa);
        output('loan').textContent = money(loan);

        const parts = [principal, tax, insurance, hoa];
        const colors = ['var(--brand)', 'var(--rent)', 'var(--pending)', 'var(--sold)'];
        let start = 0;
        const stops = parts.map((part, partIndex) => {
            const share = total > 0 ? part / total * 100 : 0;
            const stop = `${colors[partIndex]} ${start}% ${start + share}%`;
            start += share;

            return stop;
        });

        calculator.querySelector('[data-donut]').style.background = total > 0 ? `conic-gradient(${stops.join(', ')})` : 'var(--surface-2)';
    };

    calculator.addEventListener('input', (event) => {
        if (event.target.name === 'down') {
            lastChanged = 'down';
        } else if (event.target.name === 'down_percent' || event.target.name === 'price') {
            lastChanged = 'percent';
        } else {
            lastChanged = null;
        }

        update();
    });

    update();
});

// ---------- Maps (Leaflet + OpenStreetMap, loaded only when needed) ----------
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

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

function initMap(element) {
    if (element.dataset.ready) {
        return;
    }

    element.dataset.ready = '1';

    loadLeaflet().then((L) => {
        element.querySelector('.map-note')?.remove();

        const map = L.map(element, { scrollWheelZoom: element.dataset.scrollZoom === 'true', zoomControl: true });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        const layer = L.layerGroup().addTo(map);
        const markers = new Map();

        const draw = (points, fit = true) => {
            layer.clearLayers();
            markers.clear();

            points.forEach((point) => {
                const icon = L.divIcon({ className: 'map-pin-wrap', html: `<span class="map-pin">${escapeHtml(point.price)}</span>`, iconSize: null });
                const marker = L.marker([point.lat, point.lng], { icon, title: point.title, riseOnHover: true }).addTo(layer);

                if (point.url) {
                    marker.bindPopup(`<a class="map-popup" href="${escapeHtml(point.url)}">${point.photo ? `<img src="${escapeHtml(point.photo)}" alt="">` : ''}<strong>${escapeHtml(point.price)}</strong><span>${escapeHtml(point.facts)}</span><span>${escapeHtml(point.title)}</span></a>`, { closeButton: false, minWidth: 220 });
                }

                markers.set(point.id, marker);
            });

            if (! fit) {
                return;
            }

            if (points.length === 1) {
                map.setView([points[0].lat, points[0].lng], Number(element.dataset.zoom) || 14);
            } else if (points.length > 1) {
                map.fitBounds(L.latLngBounds(points.map((point) => [point.lat, point.lng])), { padding: [40, 40], maxZoom: 15 });
            } else {
                map.setView([Number(element.dataset.lat) || 39.5, Number(element.dataset.lng) || -98.35], Number(element.dataset.zoom) || 4);
            }
        };

        draw(JSON.parse(element.dataset.points || '[]'));

        // The search page sends new points whenever the filters change (see the listener below).
        element.redraw = () => draw(JSON.parse(element.dataset.points || '[]'));

        // Highlight the marker of the card under the pointer.
        document.addEventListener('mouseover', (event) => {
            const card = event.target.closest('[data-listing]');
            markers.forEach((marker, id) => marker.getElement()?.querySelector('.map-pin')?.classList.toggle('is-active', card !== null && String(id) === card.dataset.listing));
        });

        // Maps inside hidden containers need a nudge when they become visible.
        new ResizeObserver(() => map.invalidateSize()).observe(element);
    }).catch(() => {
        element.querySelector('.map-note')?.replaceChildren('The map could not be loaded. Check your connection and refresh the page.');
    });
}

const mapObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) {
            initMap(entry.target);
            mapObserver.unobserve(entry.target);
        }
    }), { rootMargin: '200px' })
    : null;

const watchMaps = () => document.querySelectorAll('[data-map]:not([data-ready])').forEach((element) => {
    if (mapObserver) {
        mapObserver.observe(element);
    } else {
        initMap(element);
    }
});

watchMaps();

// Livewire dispatches "map-points" from the search page; keep the newest points even if the map is still loading.
window.addEventListener('map-points', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;

    document.querySelectorAll('[data-map][data-listen]').forEach((element) => {
        element.dataset.points = JSON.stringify(detail?.points ?? []);
        element.redraw?.();
    });
});

// ---------- Search filters on phones ----------
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-filters-toggle]');

    if (toggle) {
        const bar = toggle.closest('.search-bar');
        bar.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(bar.classList.contains('is-open')));
    }
});

// ---------- Share button ----------
document.querySelectorAll('[data-share]').forEach((button) => {
    button.addEventListener('click', async () => {
        const data = { title: document.title, url: window.location.href };

        try {
            if (navigator.share) {
                await navigator.share(data);
            } else {
                await navigator.clipboard.writeText(data.url);
                const label = button.querySelector('span');
                const original = label.textContent;
                label.textContent = 'Link copied';
                setTimeout(() => { label.textContent = original; }, 2000);
            }
        } catch (error) {
            // The visitor closed the share sheet.
        }
    });
});
