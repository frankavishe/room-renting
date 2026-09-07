// assets/js/main.js
// Core application logic: auth state, room loading, and UI helpers.

// ----------------------------------------------------------------
// THEME (light/dark)
// The <head> of every page also runs a tiny inline anti-flash script
// that sets data-theme before first paint — this just keeps the
// toggle button and localStorage in sync after that.
// ----------------------------------------------------------------
const Theme = {
    KEY: 'tz_theme',

    get() {
        return localStorage.getItem(this.KEY)
            || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    },

    apply(theme) {
        document.documentElement.setAttribute('data-theme', theme);
    },

    toggle() {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem(this.KEY, next);
        this.apply(next);
    },

    init() {
        this.apply(this.get());
        const btn = document.getElementById('theme-toggle');
        if (btn) btn.addEventListener('click', () => this.toggle());
    }
};

// ----------------------------------------------------------------
// AUTH HELPERS
// The JWT is stored in localStorage — it persists across browser tabs
// and page refreshes until the user logs out or the token expires.
// ----------------------------------------------------------------

const Auth = {
    getToken() {
        return localStorage.getItem('tz_token');
    },

    getUser() {
        const raw = localStorage.getItem('tz_user');
        return raw ? JSON.parse(raw) : null;
    },

    // Called after a successful login — saves token and user profile.
    save(token, user) {
        localStorage.setItem('tz_token', token);
        localStorage.setItem('tz_user', JSON.stringify(user));
    },

    // Called on logout — wipes everything and redirects to home.
    logout() {
        localStorage.removeItem('tz_token');
        localStorage.removeItem('tz_user');
        window.location.href = 'index.html';
    },

    isLoggedIn() {
        return !!this.getToken();
    }
};

// ----------------------------------------------------------------
// UI HELPERS
// ----------------------------------------------------------------

// Show an alert message inside a container element.
// type: 'success' | 'danger' | 'info'
function showAlert(containerId, message, type = 'danger') {
    const el = document.getElementById(containerId);
    if (!el) return;
    el.className = `alert alert-${type}`;
    el.textContent = message;
    el.classList.remove('hidden');
    // Auto-hide success messages after 4 seconds.
    if (type === 'success') setTimeout(() => el.classList.add('hidden'), 4000);
}

// Format Tanzanian Shillings — e.g. 250000 → "TZS 250,000"
function formatTZS(amount) {
    return 'TZS ' + Number(amount).toLocaleString('en-TZ');
}

// ----------------------------------------------------------------
// NAVBAR: update links based on auth state
// ----------------------------------------------------------------
function updateNavbar() {
    const user = Auth.getUser();
    const navLinks = document.getElementById('nav-links');
    if (!navLinks) return;

    if (user) {
        navLinks.innerHTML = `
            <a href="rooms.html">Browse Rooms</a>
            ${user.role === 'landlord' ? '<a href="post-room.html">Post a Room</a>' : ''}
            ${user.role === 'admin' ? '<a href="admin.html">Admin Dashboard</a>' : ''}
            <a href="my-bookings.html">My Bookings</a>
            <a href="#" onclick="Auth.logout()">Logout (${user.name.split(' ')[0]})</a>
        `;
    } else {
        navLinks.innerHTML = `
            <a href="rooms.html">Browse Rooms</a>
            <a href="login.html">Login</a>
            <a href="register.html" class="btn-nav">Sign Up</a>
        `;
    }
}

// ----------------------------------------------------------------
// ROOM CARD: build HTML from a room object
// ----------------------------------------------------------------
function buildRoomCard(room) {
    const image    = room.images[0] ?? 'assets/img/placeholder.jpg';
    const amenityTags = room.amenities.slice(0, 3)
        .map(a => `<span class="tag">${a}</span>`)
        .join('');

    return `
        <div class="room-card">
            <img src="${image}" alt="${room.title}" loading="lazy"
                 onerror="this.src='assets/img/placeholder.jpg'">
            <div class="room-card-body">
                <h3>${room.title}</h3>
                <p class="location">📍 ${room.location}</p>
                <p class="price">${formatTZS(room.price_per_month)}<small>/month</small></p>
                <div class="amenities">${amenityTags}</div>
                <a href="room-detail.html?id=${room.id}" class="btn btn-primary btn-full">View Details</a>
            </div>
        </div>
    `;
}

// ----------------------------------------------------------------
// LOAD ROOMS: fetch from API and render cards
// ----------------------------------------------------------------
async function loadRooms(filters = {}) {
    const grid = document.getElementById('rooms-grid');
    if (!grid) return;

    grid.innerHTML = '<p class="text-center" style="padding:2rem">Loading rooms...</p>';

    // Build the query string from the filters object.
    const params = new URLSearchParams(filters).toString();
    const url    = `api/rooms/read.php${params ? '?' + params : ''}`;

    try {
        const res  = await fetch(url);
        const data = await res.json();

        if (!data.rooms || data.rooms.length === 0) {
            grid.innerHTML = '<p class="text-center" style="padding:2rem;color:#888">No rooms found. Try different filters.</p>';
            return;
        }

        grid.innerHTML = data.rooms.map(buildRoomCard).join('');

    } catch (err) {
        grid.innerHTML = '<p class="text-center" style="padding:2rem;color:red">Failed to load rooms. Check your connection.</p>';
        console.error('loadRooms error:', err);
    }
}

// ----------------------------------------------------------------
// REGISTER FORM handler
// ----------------------------------------------------------------
async function handleRegister(e) {
    e.preventDefault();
    const form = e.target;
    const btn  = form.querySelector('button[type=submit]');

    btn.disabled    = true;
    btn.textContent = 'Registering...';

    const payload = {
        name:     form.name.value,
        phone:    form.phone.value,
        email:    form.email.value || undefined,
        password: form.password.value,
        role:     form.role.value
    };

    try {
        const res  = await fetch('api/auth/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (res.ok) {
            showAlert('form-alert', 'Account created! Redirecting to login...', 'success');
            setTimeout(() => window.location.href = 'login.html', 1500);
        } else {
            showAlert('form-alert', data.message);
        }
    } catch (err) {
        showAlert('form-alert', 'Network error. Please try again.');
    }

    btn.disabled    = false;
    btn.textContent = 'Create Account';
}

// ----------------------------------------------------------------
// LOGIN FORM handler
// ----------------------------------------------------------------
async function handleLogin(e) {
    e.preventDefault();
    const form = e.target;
    const btn  = form.querySelector('button[type=submit]');

    btn.disabled    = true;
    btn.textContent = 'Logging in...';

    const payload = {
        phone:    form.phone.value,
        password: form.password.value
    };

    try {
        const res  = await fetch('api/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (res.ok) {
            Auth.save(data.token, data.user);
            // Redirect landlords to their dashboard, admins to the admin panel,
            // and everyone else (tenants) to room listings.
            const dest = data.user.role === 'landlord' ? 'post-room.html'
                       : data.user.role === 'admin'    ? 'admin.html'
                       : 'rooms.html';
            window.location.href = dest;
        } else {
            showAlert('form-alert', data.message);
        }
    } catch (err) {
        showAlert('form-alert', 'Network error. Please try again.');
    }

    btn.disabled    = false;
    btn.textContent = 'Login';
}

// ----------------------------------------------------------------
// On DOM ready: wire up forms and navbar
// ----------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    Theme.init();
    updateNavbar();

    const registerForm = document.getElementById('register-form');
    const loginForm    = document.getElementById('login-form');

    if (registerForm) registerForm.addEventListener('submit', handleRegister);
    if (loginForm)    loginForm.addEventListener('submit', handleLogin);

    // Search form on rooms page
    const searchForm = document.getElementById('search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', e => {
            e.preventDefault();
            const filters = {};
            const loc   = searchForm.querySelector('[name=location]').value.trim();
            const maxP  = searchForm.querySelector('[name=max_price]').value.trim();
            const minP  = searchForm.querySelector('[name=min_price]').value.trim();
            if (loc)  filters.location  = loc;
            if (maxP) filters.max_price = maxP;
            if (minP) filters.min_price = minP;
            loadRooms(filters);
        });
    }

    // index.html calls loadRooms() via its own inline script.
    // rooms.html handles loading in its own DOMContentLoaded.
});
