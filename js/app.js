// js/app.js
const API_URL = 'api/';

// Elements
const nav = document.getElementById('main-nav');
const alertBox = document.getElementById('alert-container');
const views = document.querySelectorAll('.view');
const navLinks = document.querySelectorAll('.nav-link');

// State
let currentUser = null;

// Routing
function navigate() {
    let hash = window.location.hash.substring(1) || 'login';

    // Auth Guard
    if (!currentUser && hash !== 'login' && hash !== 'register') {
        window.location.hash = 'login';
        return;
    }
    if (currentUser && (hash === 'login' || hash === 'register')) {
        window.location.hash = 'dashboard';
        return;
    }

    // Toggle Navigation visibility
    nav.style.display = currentUser ? 'flex' : 'none';

    // Show View
    views.forEach(v => v.style.display = 'none');
    const activeView = document.getElementById(`view-${hash}`);
    if (activeView) {
        activeView.style.display = 'flex';
        activeView.style.flexDirection = 'column';
        activeView.style.justifyContent = 'center';
        activeView.style.alignItems = 'center';
        activeView.style.minHeight = '80vh';
        activeView.style.width = '100%';
    }

    // Highlight Nav
    navLinks.forEach(l => l.classList.remove('active'));
    document.querySelector(`[data-target="${hash}"]`)?.classList.add('active');

    // Clear alerts
    alertBox.style.display = 'none';

    // Load Data based on view
    if (hash === 'dashboard') loadDashboard();
    if (hash === 'profile') loadProfile();
}

window.addEventListener('hashchange', navigate);

// Utils
function escapeHTML(str) {
    if (!str) return '';
    return str.toString().replace(/[&<>'"]/g,
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag]));
}

function showAlert(message, type = 'error') {
    alertBox.className = `container alert ${type}`;
    alertBox.textContent = message;
    alertBox.style.display = 'block';
    setTimeout(() => alertBox.style.display = 'none', 5000);
}

function getDefaultAvatar(name) {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random`;
}

// Auto Logout Timer
let logoutTimer;
function resetTimer() {
    clearTimeout(logoutTimer);
    if (currentUser) {
        logoutTimer = setTimeout(async () => {
            await fetch(API_URL + 'auth.php?action=logout', { method: 'POST' });
            currentUser = null;
            showAlert('Session expired due to inactivity. Logged out.', 'error');
            navigate();
        }, 10 * 60 * 1000); // 10 minutes
    }
}
window.addEventListener('mousemove', resetTimer);
window.addEventListener('keypress', resetTimer);
window.addEventListener('click', resetTimer);
window.addEventListener('scroll', resetTimer);

// User Session Check
async function initApp() {
    try {
        const res = await fetch(API_URL + 'auth.php?action=check');
        const data = await res.json();
        if (data.status === 'success') {
            currentUser = data.user;
            document.getElementById('nav-username').textContent = currentUser.username;
            resetTimer();
        } else {
            currentUser = null;
        }
    } catch (e) {
        currentUser = null;
    }
    navigate();
}

// Auth Handlers
document.getElementById('form-login').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;

    const payload = {
        username: document.getElementById('login-username').value,
        password: document.getElementById('login-password').value
    };

    try {
        const res = await fetch(API_URL + 'auth.php?action=login', {
            method: 'POST', body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            currentUser = data.user;
            document.getElementById('nav-username').textContent = currentUser.username;

            // Dynamic Welcome Pop-up
            document.getElementById('modal-success-title').textContent = 'Success!';
            document.getElementById('modal-success-msg').textContent = data.message;
            document.getElementById('success-modal').style.display = 'flex';

            resetTimer();
            window.location.hash = 'dashboard';
        } else {
            showAlert(data.message);
        }
    } catch (err) {
        showAlert('Network error');
    }
    btn.disabled = false;
});

document.getElementById('form-register').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');

    const email = document.getElementById('reg-email').value;
    const password = document.getElementById('reg-password').value;

    if (!email.toLowerCase().endsWith('@gmail.com')) {
        showAlert('Registration restricted to @gmail.com addresses only.');
        return;
    }

    if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[^a-zA-Z\d\s:]/.test(password)) {
        showAlert('Password must be at least 8 chars long with 1 uppercase, 1 lowercase, and 1 special symbol.');
        return;
    }

    btn.disabled = true;
    const payload = {
        username: document.getElementById('reg-username').value,
        email: email,
        password: password
    };

    try {
        const res = await fetch(API_URL + 'auth.php?action=register', {
            method: 'POST', body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            document.getElementById('form-register').reset();
            setTimeout(() => window.location.hash = 'login', 2000);
        } else {
            showAlert(data.message);
        }
    } catch (err) {
        showAlert('Network error');
    }
    btn.disabled = false;
});

document.getElementById('logout-btn').addEventListener('click', async (e) => {
    e.preventDefault();
    await fetch(API_URL + 'auth.php?action=logout', { method: 'POST' });
    currentUser = null;
    window.location.hash = 'login';
});

// Dashboard
async function loadDashboard() {
    // Get profile data for balance
    const resProfile = await fetch(API_URL + 'user.php?action=profile');
    const profile = await resProfile.json();
    if (profile.status === 'success') {
        document.getElementById('dash-greeting').textContent = `Welcome, ${profile.data.username}!`;
        document.getElementById('dash-balance').textContent = `Rs. ${parseFloat(profile.data.balance).toFixed(2)}`;
        document.getElementById('transfer-balance').textContent = `Rs. ${parseFloat(profile.data.balance).toFixed(2)}`;
    }

    // Get transactions
    const resTxn = await fetch(API_URL + 'user.php?action=transactions');
    const txns = await resTxn.json();
    if (txns.status === 'success') {
        const tbody = document.querySelector('#table-transactions tbody');
        tbody.innerHTML = '';
        if (txns.data.length === 0) {
            document.getElementById('no-tx-msg').style.display = 'block';
            document.getElementById('table-transactions').style.display = 'none';
        } else {
            document.getElementById('no-tx-msg').style.display = 'none';
            document.getElementById('table-transactions').style.display = 'table';
            txns.data.forEach(t => {
                const isSender = t.sender_id == txns.current_id;
                const type = isSender ? 'Sent' : 'Received';
                const otherUser = escapeHTML(isSender ? t.receiver : t.sender);
                const safeComment = escapeHTML(t.comment);
                const safeDate = escapeHTML(t.created_at);
                const html = `
                    <tr>
                        <td>${safeDate}</td>
                        <td>${type}</td>
                        <td>${otherUser}</td>
                        <td class="${isSender ? 'text-danger' : 'text-success'}">
                            ${isSender ? '-' : '+'} Rs. ${parseFloat(t.amount).toFixed(2)}
                        </td>
                        <td>${safeComment}</td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', html);
            });
        }
    }
}

// Transfer
document.getElementById('form-transfer').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;

    const payload = {
        receiver: document.getElementById('txn-receiver').value,
        amount: document.getElementById('txn-amount').value,
        comment: document.getElementById('txn-comment').value
    };

    try {
        const res = await fetch(API_URL + 'transfer.php?action=send', {
            method: 'POST', body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById('modal-success-title').textContent = 'Transfer Successful';
            document.getElementById('modal-success-msg').textContent = data.message;
            document.getElementById('success-modal').style.display = 'flex';
            document.getElementById('form-transfer').reset();
            loadDashboard(); // Refresh background balance state
        } else {
            showAlert(data.message);
        }
    } catch (err) {
        showAlert('Transfer Error');
    }
    btn.disabled = false;
});

// Search
document.getElementById('form-search').addEventListener('submit', async (e) => {
    e.preventDefault();
    const q = document.getElementById('search-query').value;

    try {
        const res = await fetch(API_URL + `transfer.php?action=search&q=${encodeURIComponent(q)}`);
        const data = await res.json();

        const container = document.getElementById('search-results-container');
        const list = document.getElementById('search-results-list');
        container.style.display = 'block';
        list.innerHTML = '';

        if (data.status === 'success' && data.data.length > 0) {
            data.data.forEach(user => {
                const safeUsername = escapeHTML(user.username);
                const avatar = user.profile_image ? escapeHTML(user.profile_image) : getDefaultAvatar(user.username);
                const html = `
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; align-items: center;">
                            <img src="${avatar}" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 1rem; object-fit: cover;">
                            <div>
                                <strong>${safeUsername}</strong><br>
                                <small class="text-muted">ID: ${escapeHTML(user.id)}</small>
                            </div>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <button onclick="viewPublicProfile(${user.id})" class="btn" style="padding: 0.5rem 1rem;">Profile</button>
                            ${user.id == currentUser.id
                        ? `<button disabled class="btn" style="padding: 0.5rem 1rem; background-color: var(--text-muted); cursor: not-allowed; opacity: 0.6;">You</button>`
                        : `<button onclick="prepTransfer(this.getAttribute('data-uname'))" data-uname="${safeUsername}" class="btn" style="padding: 0.5rem 1rem; background-color: var(--success);">Send</button>`
                    }
                        </div>
                    </div>
                `;
                list.insertAdjacentHTML('beforeend', html);
            });
        } else {
            list.innerHTML = '<p style="padding: 1rem;">No users found.</p>';
        }

    } catch (err) {
        showAlert('Search error');
    }
});

function prepTransfer(username) {
    document.getElementById('txn-receiver').value = username;
    window.location.hash = 'transfer';
}

async function viewPublicProfile(id) {
    window.location.hash = 'public';
    try {
        const res = await fetch(API_URL + `user.php?action=public_profile&id=${id}`);
        const data = await res.json();
        if (data.status === 'success') {
            const u = data.data;
            document.getElementById('public-username').textContent = u.username;
            document.getElementById('public-bio').textContent = u.bio || "No biography provided.";
            document.getElementById('public-img').src = u.profile_image ? u.profile_image : getDefaultAvatar(u.username);
            document.getElementById('public-send-btn').onclick = () => prepTransfer(u.username);
        }
    } catch (e) {
        showAlert('Failed to load profile');
    }
}

// Profile
async function loadProfile() {
    const res = await fetch(API_URL + 'user.php?action=profile');
    const data = await res.json();
    if (data.status === 'success') {
        const p = data.data;
        document.getElementById('prof-username').value = p.username;
        document.getElementById('prof-email').value = p.email;
        document.getElementById('prof-bio').value = p.bio || '';
        document.getElementById('profile-img-preview').src = p.profile_image ? p.profile_image : getDefaultAvatar(p.username);
    }
}

document.getElementById('form-profile').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;

    const formData = new FormData();
    formData.append('email', document.getElementById('prof-email').value);
    formData.append('bio', document.getElementById('prof-bio').value);

    const fileInput = document.getElementById('profile-image-input');
    if (fileInput.files[0]) {
        formData.append('profile_image', fileInput.files[0]);
    }

    try {
        const res = await fetch(API_URL + 'user.php?action=update', {
            method: 'POST', body: formData
        });
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById('modal-success-title').textContent = 'Updated Successful';
            document.getElementById('modal-success-msg').textContent = 'Your profile has been updated.';
            document.getElementById('success-modal').style.display = 'flex';
            loadProfile(); // reload
        } else {
            showAlert(data.message);
        }
    } catch (err) {
        showAlert('Error updating profile');
    }
    btn.disabled = false;
});

// Boot
initApp();
