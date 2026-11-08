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

        // Signed in but the email isn't confirmed yet: go and enter the code.
        if (response.status === 403) {
            window.location.href = '/email/verify';

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

/*
 * ---------------------------------------------------------------------------
 * Affordability and rent-vs-buy calculators
 * ---------------------------------------------------------------------------
 */
const asMoney = (value) => (value < 0 ? '-$' : '$') + Math.abs(Math.round(value)).toLocaleString('en-US');
const asNumber = (input) => Number(String(input?.value ?? '').replace(/[^0-9.]/g, '')) || 0;
const paymentFactor = (yearlyRatePercent, years) => {
    const rate = yearlyRatePercent / 100 / 12;
    const months = years * 12;

    if (months === 0) {
        return 0;
    }

    return rate === 0 ? 1 / months : rate / (1 - Math.pow(1 + rate, -months));
};

function setUpAffordability() {
    document.querySelectorAll('[data-afford]:not([data-ready])').forEach((form) => {
        form.dataset.ready = '1';
        const field = (name) => form.querySelector(`[name="${name}"]`);
        const output = (name) => document.querySelector(`[data-out="${name}"]`);

        const update = () => {
            const monthlyIncome = asNumber(field('income')) / 12;
            const debts = asNumber(field('debts'));
            const down = asNumber(field('down'));
            const taxRate = asNumber(field('tax')) / 100 / 12;
            const fixed = asNumber(field('insurance')) / 12 + asNumber(field('hoa'));
            const factor = paymentFactor(asNumber(field('rate')), asNumber(field('term')));
            const budget = Math.max(0, Math.min(monthlyIncome * 0.28, monthlyIncome * 0.36 - debts));
            const loan = Math.max(0, (budget - fixed - down * taxRate) / (factor + taxRate));
            const price = loan + down;
            const monthly = loan * factor + price * taxRate + fixed;

            output('price').textContent = asMoney(Math.floor(price / 1000) * 1000);
            output('monthly').textContent = asMoney(monthly) + '/mo';
            output('loan').textContent = asMoney(loan);
            output('share').textContent = monthlyIncome > 0 ? Math.round(monthly / monthlyIncome * 100) + '%' : '0%';
            output('note').textContent = monthlyIncome * 0.36 - debts < monthlyIncome * 0.28
                ? 'Your monthly debts are lowering this number. Paying some off would raise it.'
                : 'Lowering the rate or adding to your down payment raises this number.';

            const link = document.querySelector('[data-afford-search]');

            if (link) {
                const url = new URL(link.href, window.location.href);
                url.searchParams.set('max_price', String(Math.floor(price / 10000) * 10000));
                link.href = url.toString();
            }
        };

        form.addEventListener('input', update);
        update();
    });
}

function setUpRentVsBuy() {
    document.querySelectorAll('[data-rentbuy]:not([data-ready])').forEach((form) => {
        form.dataset.ready = '1';
        const field = (name) => form.querySelector(`[name="${name}"]`);
        const output = (name) => document.querySelector(`[data-out="${name}"]`);

        // Net cost of each choice after a number of years.
        const costs = (years) => {
            const price = asNumber(field('price'));
            const down = price * asNumber(field('down')) / 100;
            const loan = price - down;
            const monthlyRate = asNumber(field('rate')) / 100 / 12;
            const payment = loan * paymentFactor(asNumber(field('rate')), 30);
            const growth = asNumber(field('growth')) / 100;
            const invest = asNumber(field('invest')) / 100;
            const buyCosts = price * asNumber(field('buy_costs')) / 100;

            let balance = loan;
            let paid = down + buyCosts;
            let rent = asNumber(field('rent'));
            let rentPaid = 0;

            for (let year = 0; year < years; year++) {
                const value = price * Math.pow(1 + growth, year);
                paid += (asNumber(field('tax')) / 100 + asNumber(field('upkeep')) / 100) * value + asNumber(field('insurance'));

                for (let month = 0; month < 12; month++) {
                    const interest = balance * monthlyRate;
                    balance = Math.max(0, balance - (payment - interest));
                    paid += payment;
                }

                rentPaid += rent * 12;
                rent *= 1 + asNumber(field('rent_growth')) / 100;
            }

            const saleValue = price * Math.pow(1 + growth, years);
            const buy = paid - (saleValue * (1 - asNumber(field('sell_costs')) / 100) - balance);
            const rentCost = rentPaid - (down + buyCosts) * (Math.pow(1 + invest, years) - 1);

            return { buy, rent: rentCost };
        };

        const update = () => {
            const years = asNumber(field('years')) || 1;
            const result = costs(years);
            const difference = Math.abs(result.buy - result.rent);
            let breakEven = null;

            for (let year = 1; year <= 30; year++) {
                const check = costs(year);

                if (check.buy < check.rent) {
                    breakEven = year;
                    break;
                }
            }

            output('years').textContent = `${years} ${years === 1 ? 'year' : 'years'}`;
            output('verdict-lead').textContent = `Over ${years} ${years === 1 ? 'year' : 'years'}`;
            output('verdict').textContent = result.buy < result.rent ? `Buying saves ${asMoney(difference)}` : `Renting saves ${asMoney(difference)}`;
            output('buy').textContent = asMoney(result.buy);
            output('rentcost').textContent = asMoney(result.rent);
            output('breakeven').textContent = breakEven ? `${breakEven} ${breakEven === 1 ? 'year' : 'years'}` : 'more than 30 years';

            const bars = document.querySelector('[data-bars]');

            if (bars) {
                const top = Math.max(Math.abs(result.buy), Math.abs(result.rent), 1);
                bars.innerHTML = `<span style="width:${Math.max(4, Math.abs(result.buy) / top * 100)}%;background:var(--brand)"></span>`
                    + `<span style="width:${Math.max(4, Math.abs(result.rent) / top * 100)}%;background:var(--rent)"></span>`;
            }
        };

        form.addEventListener('input', update);
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

        if (element.hasAttribute('data-draw')) {
            setUpDrawing(L, map, element);
        }


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

// "Draw an area": tap points on the map to outline where you want to live.
function setUpDrawing(L, map, element) {
    const tools = document.createElement('div');
    tools.className = 'draw-tools';
    element.append(tools);
    L.DomEvent.disableClickPropagation(tools);

    let shape = null;
    let points = [];
    let preview = null;
    let drawing = false;

    const showShape = (corners) => {
        shape?.remove();
        shape = corners.length >= 3 ? L.polygon(corners, { color: '#d92228', weight: 2, fillOpacity: 0.08 }).addTo(map) : null;
    };

    const render = () => {
        if (drawing) {
            tools.innerHTML = `<span class="draw-tools__hint">Tap the map to add corners${points.length ? ` (${points.length})` : ''}</span>`
                + `<button type="button" class="btn btn--primary btn--sm" data-draw-finish ${points.length < 3 ? 'disabled' : ''}>Search this area</button>`
                + '<button type="button" class="btn btn--outline btn--sm" data-draw-cancel>Cancel</button>';
        } else {
            tools.innerHTML = `<button type="button" class="btn btn--dark btn--sm" data-draw-start><span aria-hidden="true">✎</span> ${shape ? 'Redraw area' : 'Draw an area'}</button>`;
        }
    };

    const stop = () => {
        drawing = false;
        preview?.remove();
        preview = null;
        points = [];
        element.classList.remove('is-drawing');
        map.doubleClickZoom.enable();
        render();
    };

    map.on('click', (event) => {
        if (! drawing) {
            return;
        }

        points.push([Math.round(event.latlng.lat * 1e5) / 1e5, Math.round(event.latlng.lng * 1e5) / 1e5]);
        preview?.remove();
        preview = (points.length >= 3 ? L.polygon(points, { color: '#d92228', weight: 2, dashArray: '6 6', fillOpacity: 0.05 }) : L.polyline(points, { color: '#d92228', weight: 2, dashArray: '6 6' })).addTo(map);
        render();
    });

    tools.addEventListener('click', (event) => {
        // The buttons are redrawn below, so stop the click here before the map sees it as a new corner.
        event.stopPropagation();

        if (event.target.closest('[data-draw-start]')) {
            drawing = true;
            points = [];
            element.classList.add('is-drawing');
            map.doubleClickZoom.disable();
            render();
        } else if (event.target.closest('[data-draw-cancel]')) {
            stop();
        } else if (event.target.closest('[data-draw-finish]') && points.length >= 3) {
            const corners = points;
            showShape(corners);
            stop();
            window.Livewire?.dispatch('area-drawn', { points: corners });
        }
    });

    const existing = String(element.dataset.area || '').split(';').map((pair) => pair.split(',').map(Number)).filter((pair) => pair.length === 2 && pair.every(Number.isFinite));

    if (existing.length >= 3) {
        showShape(existing);
        map.fitBounds(shape.getBounds(), { padding: [30, 30] });
    }

    window.addEventListener('area-cleared', () => {
        shape?.remove();
        shape = null;
        render();
    });

    render();
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

/*
 * ---------------------------------------------------------------------------
 * Compare homes and "Recently viewed" (kept in this browser only)
 * ---------------------------------------------------------------------------
 */
const COMPARE_KEY = 'hr-compare';
const COMPARE_LIMIT = 4;
const RECENT_KEY = 'hr-recent';
const RECENT_LIMIT = 12;

function readList(key) {
    try {
        const value = JSON.parse(localStorage.getItem(key) || '[]');

        return Array.isArray(value) ? value.map(Number).filter((id) => id > 0) : [];
    } catch (error) {
        return [];
    }
}

function writeList(key, list) {
    try {
        localStorage.setItem(key, JSON.stringify(list));
    } catch (error) {
        // Private mode or storage full: the feature simply won't remember.
    }
}

function syncCompare() {
    const list = readList(COMPARE_KEY);

    document.querySelectorAll('[data-compare]').forEach((button) => {
        const on = list.includes(Number(button.dataset.compare));
        button.setAttribute('aria-pressed', String(on));
        button.querySelector('[data-compare-text]')?.replaceChildren(on ? 'Added to compare' : 'Compare');
    });

    // The plain /compare link (footer, home page) shows the homes picked in this browser.
    if (window.location.pathname === '/compare' && ! new URLSearchParams(window.location.search).has('ids') && list.length > 0) {
        window.location.replace(`/compare?ids=${list.join(',')}`);

        return;
    }

    let bar = document.querySelector('.compare-bar');

    if (list.length === 0 || window.location.pathname === '/compare') {
        bar?.remove();

        return;
    }

    if (! bar) {
        bar = document.createElement('div');
        bar.className = 'compare-bar';
        bar.setAttribute('role', 'region');
        bar.setAttribute('aria-label', 'Homes to compare');
        document.body.append(bar);
    }

    bar.innerHTML = `<span><b>${list.length}</b> of ${COMPARE_LIMIT} homes picked</span>`
        + `<button type="button" class="link" data-compare-clear>Clear</button>`
        + `<a class="btn btn--primary btn--sm" href="/compare?ids=${list.join(',')}">${list.length > 1 ? 'Compare now' : 'Pick one more'}</a>`;
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-compare]');
    const remove = event.target.closest('[data-compare-remove]');
    const clear = event.target.closest('[data-compare-clear]');

    if (! toggle && ! remove && ! clear) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    let list = readList(COMPARE_KEY);

    if (clear) {
        list = [];
    } else if (remove) {
        list = list.filter((id) => id !== Number(remove.dataset.compareRemove));
        writeList(COMPARE_KEY, list);
        window.Livewire?.navigate ? window.Livewire.navigate(`/compare?ids=${list.join(',')}`) : (window.location.href = `/compare?ids=${list.join(',')}`);

        return;
    } else {
        const id = Number(toggle.dataset.compare);

        if (list.includes(id)) {
            list = list.filter((item) => item !== id);
            toast('Removed from compare');
        } else if (list.length >= COMPARE_LIMIT) {
            toast(`You can compare up to ${COMPARE_LIMIT} homes`);

            return;
        } else {
            list.push(id);
            toast(list.length > 1 ? 'Added. Tap "Compare now" below' : 'Added. Pick another home to compare');
        }
    }

    writeList(COMPARE_KEY, list);
    syncCompare();
});

// Remember the home being viewed, newest first.
function trackRecent() {
    const holder = document.querySelector('[data-recent]');

    if (! holder) {
        return;
    }

    const id = Number(holder.dataset.recent);
    writeList(RECENT_KEY, [id, ...readList(RECENT_KEY).filter((item) => item !== id)].slice(0, RECENT_LIMIT));
}

// Fill a "Recently viewed" row with real cards from the server.
async function loadRecent() {
    const box = document.querySelector('[data-recent-list]');

    if (! box) {
        return;
    }

    const skip = Number(box.dataset.skip || 0);
    const ids = readList(RECENT_KEY).filter((id) => id !== skip).slice(0, 8);

    if (ids.length === 0) {
        return;
    }

    try {
        const response = await fetch(`/homes/cards?ids=${ids.join(',')}`, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        const html = (await response.text()).trim();

        if (! response.ok || html === '') {
            return;
        }

        box.querySelector('[data-recent-cards]').innerHTML = html;
        box.hidden = false;
        syncCompare();
    } catch (error) {
        // Leave the section hidden.
    }
}

document.addEventListener('click', (event) => {
    if (! event.target.closest('[data-recent-clear]')) {
        return;
    }

    writeList(RECENT_KEY, []);
    document.querySelector('[data-recent-list]')?.setAttribute('hidden', '');
});

function setUpPage() {
    setMenu(false);
    setUpReveals();
    setUpCounters();
    setUpLightbox();
    setUpCalculators();
    setUpAffordability();
    setUpRentVsBuy();
    setUpMaps();
    setUpParallax();
    trackRecent();
    loadRecent();
    syncCompare();
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
        syncCompare();
    });
};

document.addEventListener('livewire:init', hookLivewire);
hookLivewire();
