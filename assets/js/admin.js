// assets/js/admin.js
// Logic for admin.html: guards the page to admins only, then wires up the
// tab bar and each tab's data loading + inline actions. Relies on Auth,
// showAlert, and formatTZS from main.js (loaded before this file).

// ----------------------------------------------------------------
// GUARD: only admins may see this page.
// This is defense-in-depth only — every api/admin/*.php endpoint enforces
// the real check server-side via requireRole(['admin']).
// ----------------------------------------------------------------
function guardAdmin() {
    const user = Auth.getUser();
    if (!user || user.role !== 'admin') {
        window.location.href = 'index.html';
        return false;
    }
    return true;
}

// ----------------------------------------------------------------
// FETCH HELPER: attaches the bearer token, and bounces to login if the
// server says the token is missing/expired/not-admin.
// ----------------------------------------------------------------
async function adminFetch(path, opts = {}) {
    const headers = Object.assign(
        { 'Authorization': 'Bearer ' + Auth.getToken() },
        opts.body ? { 'Content-Type': 'application/json' } : {},
        opts.headers || {}
    );
    const res = await fetch(path, Object.assign({}, opts, { headers }));

    if (res.status === 401 || res.status === 403) {
        Auth.logout();
        throw new Error('Not authorized.');
    }
    return res;
}

// ----------------------------------------------------------------
// TABS
// ----------------------------------------------------------------
const loadedTabs = {};

function initTabs() {
    const tabs = document.getElementById('admin-tabs');
    if (!tabs) return;

    tabs.addEventListener('click', e => {
        const btn = e.target.closest('.tab-btn');
        if (!btn) return;

        tabs.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
        const panel = document.getElementById('tab-' + btn.dataset.tab);
        if (panel) panel.classList.remove('hidden');

        // Lazy-load each tab's data the first time it's opened.
        if (!loadedTabs[btn.dataset.tab]) {
            loadedTabs[btn.dataset.tab] = true;
            if (btn.dataset.tab === 'users')    loadUsers();
            if (btn.dataset.tab === 'rooms')    loadRooms();
            if (btn.dataset.tab === 'bookings') loadBookings();
            if (btn.dataset.tab === 'payments') loadPayments();
        }
    });
}

function adminError(message) {
    showAlert('admin-alert', message, 'danger');
}

// ----------------------------------------------------------------
// OVERVIEW / STATS
// ----------------------------------------------------------------
function buildStatCard(label, value) {
    return `
        <div class="stat-card">
            <div class="stat-value">${value}</div>
            <div class="stat-label">${label}</div>
        </div>
    `;
}

async function loadStats() {
    const grid = document.getElementById('stat-grid');
    try {
        const res  = await adminFetch('api/admin/stats.php');
        const data = await res.json();
        if (!res.ok) { adminError(data.message); return; }

        grid.innerHTML = [
            buildStatCard('Total Users', data.users.total),
            buildStatCard('Tenants', data.users.tenants),
            buildStatCard('Landlords', data.users.landlords),
            buildStatCard('Admins', data.users.admins),
            buildStatCard('Total Rooms', data.rooms.total),
            buildStatCard('Available Rooms', data.rooms.available),
            buildStatCard('Occupied Rooms', data.rooms.occupied),
            buildStatCard('Total Bookings', data.bookings.total),
            buildStatCard('Pending Bookings', data.bookings.pending),
            buildStatCard('Confirmed Bookings', data.bookings.confirmed),
            buildStatCard('Total Revenue', formatTZS(data.payments.revenue_total)),
            buildStatCard('Revenue This Month', formatTZS(data.payments.revenue_this_month)),
        ].join('');
    } catch (err) {
        grid.innerHTML = '<p class="text-center" style="color:red">Failed to load stats.</p>';
    }
}

// ----------------------------------------------------------------
// USERS TAB
// ----------------------------------------------------------------
function renderUsersRow(u) {
    const roles = ['tenant', 'landlord', 'admin'];
    const options = roles.map(r =>
        `<option value="${r}" ${r === u.role ? 'selected' : ''}>${r}</option>`
    ).join('');

    return `
        <tr data-user-id="${u.id}">
            <td>${u.name}</td>
            <td>${u.phone}</td>
            <td>${u.email ?? '—'}</td>
            <td><select onchange="updateUserRole(${u.id}, this.value)">${options}</select></td>
            <td>${u.room_count}</td>
            <td>${u.booking_count}</td>
            <td>${new Date(u.created_at).toLocaleDateString()}</td>
            <td class="actions-cell">
                <button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id})">Delete</button>
            </td>
        </tr>
    `;
}

async function loadUsers() {
    const tbody = document.getElementById('users-tbody');
    try {
        const res  = await adminFetch('api/admin/users_list.php');
        const data = await res.json();
        if (!res.ok) { adminError(data.message); return; }

        tbody.innerHTML = data.users.length
            ? data.users.map(renderUsersRow).join('')
            : '<tr><td colspan="8" class="text-center">No users found.</td></tr>';
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center" style="color:red">Failed to load users.</td></tr>';
    }
}

async function updateUserRole(userId, role) {
    try {
        const res  = await adminFetch('api/admin/users_update_role.php', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, role })
        });
        const data = await res.json();
        if (!res.ok) { adminError(data.message); loadUsers(); return; }
        loadUsers();
        loadedTabs.overview = false; // stats changed
        loadStats();
    } catch (err) {
        adminError('Network error updating role.');
    }
}

async function deleteUser(userId) {
    if (!confirm('Delete this user? This cannot be undone.')) return;
    try {
        const res  = await adminFetch('api/admin/users_delete.php', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId })
        });
        const data = await res.json();
        if (!res.ok) { adminError(data.message); return; }
        loadUsers();
        loadStats();
    } catch (err) {
        adminError('Network error deleting user.');
    }
}

// ----------------------------------------------------------------
// ROOMS TAB
// ----------------------------------------------------------------
function renderRoomsRow(r) {
    const statusBadge = r.is_available
        ? '<span class="badge badge-available">available</span>'
        : '<span class="badge badge-occupied">occupied</span>';

    const deleteBtn = r.booking_count === 0
        ? `<button class="btn btn-danger btn-sm" onclick="deleteRoom(${r.id})">Delete</button>`
        : '';

    return `
        <tr data-room-id="${r.id}">
            <td>${r.title}</td>
            <td>${r.owner_name}</td>
            <td>${r.location}</td>
            <td>${formatTZS(r.price_per_month)}</td>
            <td>${statusBadge}</td>
            <td>${r.booking_count}</td>
            <td class="actions-cell">
                <button class="btn btn-outline btn-sm" onclick="toggleRoom(${r.id})">
                    ${r.is_available ? 'Mark Occupied' : 'Mark Available'}
                </button>
                ${deleteBtn}
            </td>
        </tr>
    `;
}

async function loadRooms() {
    const tbody = document.getElementById('rooms-tbody');
    try {
        const res  = await adminFetch('api/admin/rooms_list.php');
        const data = await res.json();
        if (!res.ok) { adminError(data.message); return; }

        tbody.innerHTML = data.rooms.length
            ? data.rooms.map(renderRoomsRow).join('')
            : '<tr><td colspan="7" class="text-center">No rooms found.</td></tr>';
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="color:red">Failed to load rooms.</td></tr>';
    }
}

async function toggleRoom(roomId) {
    try {
        const res  = await adminFetch('api/admin/rooms_toggle.php', {
            method: 'POST',
            body: JSON.stringify({ room_id: roomId })
        });
        const data = await res.json();
        if (!res.ok) { adminError(data.message); return; }
        loadRooms();
        loadStats();
    } catch (err) {
        adminError('Network error toggling room.');
    }
}

async function deleteRoom(roomId) {
    if (!confirm('Delete this room listing? This cannot be undone.')) return;
    try {
        const res  = await adminFetch('api/admin/rooms_delete.php', {
            method: 'POST',
            body: JSON.stringify({ room_id: roomId })
        });
        const data = await res.json();
        if (!res.ok) { adminError(data.message); return; }
        loadRooms();
        loadStats();
    } catch (err) {
        adminError('Network error deleting room.');
    }
}

// ----------------------------------------------------------------
// BOOKINGS TAB
// ----------------------------------------------------------------
function renderBookingsRow(b) {
    const statuses = ['pending', 'confirmed', 'cancelled'];
    const options = statuses.map(s =>
        `<option value="${s}" ${s === b.status ? 'selected' : ''}>${s}</option>`
    ).join('');

    const payment = b.payment_status
        ? `<span class="badge badge-${b.payment_status}">${b.payment_status}</span>`
        : '<span style="color:var(--text-muted)">no payment</span>';

    return `
        <tr data-booking-id="${b.id}">
            <td>${b.room_title}</td>
            <td>${b.tenant_name}</td>
            <td>${b.start_date} → ${b.end_date}</td>
            <td><select onchange="updateBookingStatus(${b.id}, this.value)">${options}</select></td>
            <td>${payment}</td>
            <td class="actions-cell"></td>
        </tr>
    `;
}

async function loadBookings() {
    const tbody = document.getElementById('bookings-tbody');
    try {
        const res  = await adminFetch('api/admin/bookings_list.php');
        const data = await res.json();
        if (!res.ok) { adminError(data.message); return; }

        tbody.innerHTML = data.bookings.length
            ? data.bookings.map(renderBookingsRow).join('')
            : '<tr><td colspan="6" class="text-center">No bookings found.</td></tr>';
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="color:red">Failed to load bookings.</td></tr>';
    }
}

async function updateBookingStatus(bookingId, status) {
    try {
        const res  = await adminFetch('api/admin/bookings_update_status.php', {
            method: 'POST',
            body: JSON.stringify({ booking_id: bookingId, status })
        });
        const data = await res.json();
        if (!res.ok) { adminError(data.message); loadBookings(); return; }
        loadBookings();
        loadedTabs.rooms = false; // room availability may have changed
        loadStats();
    } catch (err) {
        adminError('Network error updating booking.');
    }
}

// ----------------------------------------------------------------
// PAYMENTS TAB (read-only)
// ----------------------------------------------------------------
function renderPaymentsRow(p) {
    return `
        <tr>
            <td>${p.reference}</td>
            <td>${p.provider}</td>
            <td>${formatTZS(p.amount)}</td>
            <td><span class="badge badge-${p.status}">${p.status}</span></td>
            <td>${p.tenant_name}</td>
            <td>${p.room_title}</td>
            <td>${new Date(p.updated_at).toLocaleString()}</td>
        </tr>
    `;
}

async function loadPayments() {
    const tbody = document.getElementById('payments-tbody');
    try {
        const res  = await adminFetch('api/admin/payments_list.php');
        const data = await res.json();
        if (!res.ok) { adminError(data.message); return; }

        tbody.innerHTML = data.payments.length
            ? data.payments.map(renderPaymentsRow).join('')
            : '<tr><td colspan="7" class="text-center">No payments found.</td></tr>';
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="color:red">Failed to load payments.</td></tr>';
    }
}

// ----------------------------------------------------------------
// INIT
// main.js's own DOMContentLoaded listener already runs Theme.init()
// and updateNavbar() (this script tag loads after main.js's), so this
// listener only needs to guard the page and load the Overview tab.
// ----------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    if (!guardAdmin()) return;
    initTabs();
    loadStats();
});
