// Interactions and motion for the public website.
// Pages change through Livewire (no full reloads), so page features set themselves up again
// on every "livewire:navigated" event, while document-wide listeners are added only once.
// Leaflet loads from a CDN only on pages with a map.

const root = document.documentElement;
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

/*
 * ---------------------------------------------------------------------------
 * Document-wide behaviour (added once)
 * ---------------------------------------------------------------------------
 */

// Open website links through Livewire so the page swaps in place instead of reloading.
// Pages with a different look (dashboard, sign-in) load normally.
const fullReloadPaths = /^\/(dashboard|settings|logout|login|register|forgot-password|reset-password|two-factor|email|user|sitemap\.xml|robots\.txt|storage|build)(\/|$)/;

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const link = event.target.closest('a[href]');

    if (! link || link.target || link.hasAttribute('download') || link.hasAttribute('data-no-navigate') || [...link.attributes].some((attribute) => attribute.name.startsWith('wire:navigate'))) {
        return;
    }

    const url = new URL(link.href, window.location.href);

    if (url.origin !== window.location.origin || fullReloadPaths.test(url.pathname) || ! window.Livewire?.navigate) {
        return;
    }

    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
        return;
    }

    event.preventDefault();
    window.Livewire.navigate(url.href);
});

// Light / dark switch, remembered on this device.
document.addEventListener('click', (event) => {
    if (! event.target.closest('[data-theme-toggle]')) {
        return;
    }

    const current = root.dataset.theme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const next = current === 'dark' ? 'light' : 'dark';

    root.classList.add('theme-switching');
    root.dataset.theme = next;
    setTimeout(() => root.classList.remove('theme-switching'), 400);

    try {
        localStorage.setItem('hr-theme', next);
    } catch (error) {
        // Storage can be blocked; the switch still works for this page.
    }
});

// Mobile menu.
const setMenu = (open) => {
    root.classList.toggle('menu-open', open);
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });
};

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-menu-toggle]')) {
        setMenu(! root.classList.contains('menu-open'));
    } else if (event.target.closest('.mobile-menu a')) {
        setMenu(false);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setMenu(false);
    }
});

// The header gets a soft shadow once the page scrolls.
let ticking = false;
window.addEventListener('scroll', () => {
    if (ticking) {
        return;
    }

    ticking = true;
    window.requestAnimationFrame(() => {
        root.classList.toggle('is-scrolled', window.scrollY > 8);
        ticking = false;
    });
}, { passive: true });

// Save / unsave a home (works inside Livewire-rendered lists too).
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
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (response.status === 401 || response.status === 419) {
            window.location.href = '/login';

            return;
        }

        const data = await response.json();
        setFavorite(button.dataset.favorite, Boolean(data.saved));
        toast(data.saved ? 'Saved to your homes' : 'Removed from saved homes');
    } catch (error) {
        setFavorite(button.dataset.favorite, wasSaved);
        toast('Could not save. Check your connection.');
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

        if (saved && ! reduceMotion) {
            button.classList.remove('is-popping');
            void button.offsetWidth;
            button.classList.add('is-popping');
        }
    });
}

// A small message at the bottom of the screen.
function toast(message) {
    let box = document.querySelector('.toast');

    if (! box) {
        box = document.createElement('div');
        box.className = 'toast';
        box.setAttribute('role', 'status');
        document.body.append(box);
    }

    box.textContent = message;
    box.classList.add('is-shown');
    clearTimeout(box.hideTimer);
    box.hideTimer = setTimeout(() => box.classList.remove('is-shown'), 2200);
}

// Share button: the phone's share sheet, or copy the link.
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-share]');

    if (! button) {
        return;
    }

    try {
        if (navigator.share) {
            await navigator.share({ title: document.title, url: window.location.href });
        } else {
            await navigator.clipboard.writeText(window.location.href);
            toast('Link copied');
        }
    } catch (error) {
        // The visitor closed the share sheet.
    }
});

// Livewire sends new map points from the search page, and a map centre for ZIP searches.
// Keep the newest values on the element even if the map is still loading.
window.addEventListener('map-points', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;

    document.querySelectorAll('[data-map][data-listen]').forEach((element) => {
        element.dataset.points = JSON.stringify(detail?.points ?? []);
        element.redraw?.();
    });
});

window.addEventListener('map-center', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;

    document.querySelectorAll('[data-map][data-listen]').forEach((element) => {
        element.dataset.lat = detail.lat;
        element.dataset.lng = detail.lng;
        element.dataset.zoom = 13;

        if (JSON.parse(element.dataset.points || '[]').length === 0) {
            element.redraw?.();
        }
    });
});

/*
 * ---------------------------------------------------------------------------
 * Page features (set up after every page change)
 * ---------------------------------------------------------------------------
 */

const revealObserver = 'IntersectionObserver' in window && ! reduceMotion
    ? new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
        }
    }), { rootMargin: '0px 0px -8% 0px' })
    : null;

function setUpReveals() {
    // Children of a [data-stagger] group appear one after another.
    document.querySelectorAll('[data-stagger]').forEach((group) => {
        [...group.children].forEach((child, index) => {
            child.classList.add('reveal');
            child.style.setProperty('--delay', `${Math.min(index, 8) * 70}ms`);
        });
    });

    document.querySelectorAll('.reveal:not(.is-visible)').forEach((element) => {
        if (revealObserver) {
            revealObserver.observe(element);
        } else {
            element.classList.add('is-visible');
        }
    });
}

// Numbers that count up when they come into view, e.g. <b data-count="52">52</b>.
const countObserver = 'IntersectionObserver' in window && ! reduceMotion
    ? new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (! entry.isIntersecting) {
            return;
        }

        countObserver.unobserve(entry.target);
        const element = entry.target;
        const target = Number(element.dataset.count);
        const prefix = element.dataset.prefix || '';
        const suffix = element.dataset.suffix || '';
        const started = performance.now();
        const duration = 1100;

        const step = (now) => {
            const progress = Math.min(1, (now - started) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = prefix + Math.round(target * eased).toLocaleString('en-US') + suffix;

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    }), { threshold: 0.6 })
    : null;

function setUpCounters() {
    document.querySelectorAll('[data-count]:not([data-counted])').forEach((element) => {
        element.dataset.counted = '1';
        countObserver?.observe(element);
    });
}

// Photo gallery with tap-to-enlarge.
function setUpLightbox() {
    const lightbox = document.querySelector('[data-lightbox]:not([data-ready])');

    if (! lightbox) {
        return;
    }

    lightbox.dataset.ready = '1';
    const photos = JSON.parse(lightbox.dataset.photos || '[]');
    const image = lightbox.querySelector('[data-lightbox-image]');
    const counter = lightbox.querySelector('[data-lightbox-counter]');
    const strip = lightbox.querySelector('[data-lightbox-strip]');
    let index = 0;

    const show = (next) => {
        index = (next + photos.length) % photos.length;
        image.classList.remove('is-in');
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

    image.addEventListener('load', () => image.classList.add('is-in'));

    photos.forEach((photo, photoIndex) => {
        const thumb = document.createElement('button');
        thumb.type = 'button';
        thumb.setAttribute('aria-label', `Photo ${photoIndex + 1}`);
        thumb.innerHTML = `<img src="${escapeHtml(photo.small)}" alt="" loading="lazy">`;
        thumb.addEventListener('click', () => show(photoIndex));
        strip.append(thumb);
    });

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

// Mortgage / monthly payment calculator.
function setUpCalculators() {
    document.querySelectorAll('[data-calculator]:not([data-ready])').forEach((calculator) => {
        calculator.dataset.ready = '1';
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
}

// Maps (Leaflet + OpenStreetMap).
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
        css.dataset.navigateOnce = '';
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

            points.forEach((point, pointIndex) => {
                const delay = reduceMotion ? 0 : Math.min(pointIndex, 30) * 25;
                const icon = L.divIcon({ className: 'map-pin-wrap', html: `<span class="map-pin" style="animation-delay:${delay}ms">${escapeHtml(point.price)}</span>`, iconSize: null });
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
        element.redraw = () => draw(JSON.parse(element.dataset.points || '[]'));

        // Highlight the marker of the card under the pointer.
        element.closest('main')?.addEventListener('mouseover', (event) => {
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

function setUpMaps() {
    document.querySelectorAll('[data-map]:not([data-ready])').forEach((element) => {
        if (mapObserver) {
            mapObserver.observe(element);
        } else {
            initMap(element);
        }
    });
}

// Gentle parallax on big header photos.
function setUpParallax() {
    const images = document.querySelectorAll('[data-parallax]');

    if (reduceMotion || images.length === 0) {
        return;
    }

    const move = () => images.forEach((image) => {
        const rect = image.parentElement.getBoundingClientRect();

        if (rect.bottom > 0) {
            image.style.transform = `translate3d(0, ${Math.round(-rect.top * 0.18)}px, 0) scale(1.06)`;
        }
    });

    move();
    window.addEventListener('scroll', move, { passive: true });
    document.addEventListener('livewire:navigating', () => window.removeEventListener('scroll', move), { once: true });
}

function setUpPage() {
    setMenu(false);
    setUpReveals();
    setUpCounters();
    setUpLightbox();
    setUpCalculators();
    setUpMaps();
    setUpParallax();
}

// "livewire:navigated" runs after the first load and after every page change.
// The DOMContentLoaded fallback covers pages where Livewire is not on the page.
let pageReady = false;
document.addEventListener('livewire:navigated', () => {
    pageReady = true;
    setUpPage();
});
document.addEventListener('DOMContentLoaded', () => setTimeout(() => {
    if (! pageReady) {
        setUpPage();
    }
}, 0));

// Newly rendered Livewire content (search results, ZIP panel) gets the same treatment.
let hooked = false;
const hookLivewire = () => {
    if (hooked || ! window.Livewire?.hook) {
        return;
    }

    hooked = true;
    window.Livewire.hook('morphed', () => {
        setUpReveals();
        setUpCounters();
        setUpMaps();
    });
};

document.addEventListener('livewire:init', hookLivewire);
hookLivewire();
