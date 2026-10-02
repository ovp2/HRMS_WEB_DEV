/**
 * HRMS - Main Application JavaScript
 * Handles sidebar, dark mode, dropdowns, toasts, and common interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initThemeToggle();
    initDropdowns();
    initAlertAutoDismiss();
    initNotifications();
    initGlobalSearch();
});

/* ----------------------------------------------------------------
   Sidebar Toggle
   ---------------------------------------------------------------- */
function initSidebar() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebarClose = document.getElementById('sidebarClose');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
}

/* ----------------------------------------------------------------
   Dark Mode / Theme Toggle
   ---------------------------------------------------------------- */
function initThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

    // Load saved theme
    const savedTheme = localStorage.getItem('hrms_theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('hrms_theme', next);
            updateThemeIcon(next);
        });
    }

    function updateThemeIcon(theme) {
        if (themeIcon) {
            themeIcon.className = theme === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
        }
    }
}

function setAppTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('hrms_theme', theme);
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) {
        themeIcon.className = theme === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
    }
}


/* ----------------------------------------------------------------
   Dropdown Menus
   ---------------------------------------------------------------- */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.navbar-dropdown');

    dropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('button, .user-menu-btn');
        
        if (trigger) {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                
                // Close other dropdowns
                dropdowns.forEach(d => {
                    if (d !== dropdown) d.classList.remove('open');
                });
                
                dropdown.classList.toggle('open');
            });
        }
    });

    // Close dropdowns on outside click
    document.addEventListener('click', () => {
        dropdowns.forEach(d => d.classList.remove('open'));
    });

    // Prevent dropdown menu clicks from closing
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.addEventListener('click', (e) => e.stopPropagation());
    });
}

/* ----------------------------------------------------------------
   Alert Auto-Dismiss
   ---------------------------------------------------------------- */
function initAlertAutoDismiss() {
    const flash = document.getElementById('flashAlert');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            flash.style.transition = 'all 0.3s ease';
            setTimeout(() => flash.remove(), 300);
        }, 5000);
    }
}

/* ----------------------------------------------------------------
   Toast Notifications
   ---------------------------------------------------------------- */
function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = {
        success: 'bx-check-circle',
        danger: 'bx-error-circle',
        warning: 'bx-error',
        info: 'bx-info-circle'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class='bx ${icons[type] || icons.info} toast-icon'></i>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="dismissToast(this)">
            <i class='bx bx-x'></i>
        </button>
    `;

    container.appendChild(toast);

    // Auto dismiss
    setTimeout(() => dismissToast(toast.querySelector('.toast-close')), duration);
}

function dismissToast(closeBtn) {
    const toast = closeBtn.closest('.toast');
    if (!toast || toast.classList.contains('removing')) return;
    
    toast.classList.add('removing');
    setTimeout(() => toast.remove(), 300);
}

/* ----------------------------------------------------------------
   Notifications (Navbar Dropdown)
   ---------------------------------------------------------------- */
function initNotifications() {
    const notifList = document.getElementById('notificationList');
    if (!notifList) return;

    // Load notifications via AJAX
    loadNotifications();
}

function loadNotifications() {
    const notifList = document.getElementById('notificationList');
    if (!notifList) return;

    const baseUrl = document.querySelector('link[rel="stylesheet"]')?.href?.split('/assets/')[0] || '';
    
    fetch(baseUrl + '/includes/ajax/notifications.php?action=recent')
        .then(res => res.json())
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                notifList.innerHTML = data.notifications.map(n => `
                    <a href="${n.link || '#'}" class="dropdown-item notification-item ${n.is_read ? '' : 'unread'}">
                        <div class="notif-icon notif-${n.type}">
                            <i class='bx ${getNotifIcon(n.type)}'></i>
                        </div>
                        <div class="notif-content">
                            <strong>${escapeHtml(n.title)}</strong>
                            <p>${escapeHtml(n.message)}</p>
                            <small>${n.time_ago}</small>
                        </div>
                    </a>
                `).join('');
            }
        })
        .catch(() => {
            // Silently fail - notifications will show default empty state
        });
}

function getNotifIcon(type) {
    const icons = {
        info: 'bx-info-circle',
        success: 'bx-check-circle',
        warning: 'bx-error',
        danger: 'bx-error-circle'
    };
    return icons[type] || icons.info;
}

/* ----------------------------------------------------------------
   Modal Helpers
   ---------------------------------------------------------------- */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
        });
        document.body.style.overflow = '';
    }
});

/* ----------------------------------------------------------------
   Confirm Dialog
   ---------------------------------------------------------------- */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/* ----------------------------------------------------------------
   Utility Functions
   ---------------------------------------------------------------- */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

/* ----------------------------------------------------------------
   Form Validation Helper
   ---------------------------------------------------------------- */
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    
    // Clear previous errors
    form.querySelectorAll('.form-error').forEach(e => e.remove());
    form.querySelectorAll('.form-control.error').forEach(e => e.classList.remove('error'));
    
    // Check required fields
    form.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('error');
            const error = document.createElement('div');
            error.className = 'form-error';
            error.textContent = 'This field is required';
            field.parentNode.appendChild(error);
        }
    });
    
    return isValid;
}

/* ----------------------------------------------------------------
   Delete Confirmation
   ---------------------------------------------------------------- */
function confirmDelete(url, itemName = 'this item') {
    // If called with numeric ID on a page with custom delete handler, delegate
    if (typeof confirmDeleteEmployee === 'function' && (typeof url === 'number' || (!isNaN(url) && !String(url).includes('/')))) {
        confirmDeleteEmployee(url, 0, itemName);
        return;
    }
    const empForm = document.getElementById('deleteEmpForm');
    if (empForm && (typeof url === 'number' || (!isNaN(url) && !String(url).includes('/')))) {
        if (confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
            document.getElementById('deleteEmpId').value = url;
            empForm.submit();
        }
        return;
    }
    if (confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
        window.location.href = url;
    }
}

/* ----------------------------------------------------------------
   Password Toggle (Login Page)
   ---------------------------------------------------------------- */
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bx bx-show';
    } else {
        input.type = 'password';
        icon.className = 'bx bx-hide';
    }
}

/* ----------------------------------------------------------------
   Demo Login Quick Fill
   ---------------------------------------------------------------- */
function fillDemo(username, password) {
    const userField = document.getElementById('username');
    const passField = document.getElementById('password');
    if (userField) userField.value = username;
    if (passField) passField.value = password;
}

/* ----------------------------------------------------------------
   Table Search Filter
   ---------------------------------------------------------------- */
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    const filter = input.value.toLowerCase();
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

/* ----------------------------------------------------------------
   Print Helper
   ---------------------------------------------------------------- */
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print - ${document.title}</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                body { font-family: 'Inter', sans-serif; padding: 20px; color: #1e293b; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 8px 12px; border: 1px solid #e2e8f0; text-align: left; }
                th { background: #f8fafc; font-weight: 600; }
                .badge { padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; }
                h1, h2, h3 { margin-bottom: 10px; }
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body>${element.innerHTML}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.onload = () => {
        printWindow.print();
        printWindow.close();
    };
}

/* ----------------------------------------------------------------
   Global Search in Top Navbar with Live Interactive Dropdown
   ---------------------------------------------------------------- */
function initGlobalSearch() {
    const globalSearch = document.getElementById('globalSearch');
    const searchContainer = document.getElementById('navbarSearch');
    if (!globalSearch || !searchContainer) return;

    // Create dropdown container if not exists
    let dropdown = document.getElementById('globalSearchDropdown');
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.id = 'globalSearchDropdown';
        dropdown.className = 'global-search-results';
        dropdown.style.cssText = `
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            width: 320px;
            max-width: 90vw;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            z-index: 1100;
            display: none;
            overflow: hidden;
        `;
        searchContainer.style.position = 'relative';
        searchContainer.appendChild(dropdown);
    }

    let debounceTimer = null;

    globalSearch.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(debounceTimer);

        if (query.length < 2) {
            dropdown.style.display = 'none';
            dropdown.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => {
            const baseUrl = document.querySelector('link[rel="stylesheet"]')?.href?.split('/assets/')[0] || '';
            fetch(baseUrl + '/includes/ajax/search.php?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    if (data.results && data.results.length > 0) {
                        dropdown.innerHTML = data.results.map(r => `
                            <a href="${r.link}" style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color); font-size: 0.88rem; transition: background 0.15s ease;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background='transparent'">
                                <div style="width: 30px; height: 30px; border-radius: 6px; background: var(--primary-50); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class='bx ${r.icon || 'bx-search'}' style="font-size: 1.1rem;"></i>
                                </div>
                                <div style="flex: 1; overflow: hidden;">
                                    <div style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(r.title)}</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(r.subtitle || r.type)}</div>
                                </div>
                            </a>
                        `).join('');
                        dropdown.style.display = 'block';
                    } else {
                        dropdown.innerHTML = `
                            <div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                                No matches found for "<strong>${escapeHtml(query)}</strong>"
                            </div>
                        `;
                        dropdown.style.display = 'block';
                    }
                })
                .catch(() => {
                    dropdown.style.display = 'none';
                });
        }, 220);
    });

    globalSearch.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const firstLink = dropdown.querySelector('a');
            if (firstLink) {
                window.location.href = firstLink.href;
            }
        }
        if (e.key === 'Escape') {
            dropdown.style.display = 'none';
        }
    });

    document.addEventListener('click', function(e) {
        if (!searchContainer.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

