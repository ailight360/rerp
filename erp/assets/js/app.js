/**
 * ERP System - Main JavaScript
 * Core functionality: AJAX, modals, toasts, navigation
 */

// ============================================================
// GLOBAL UTILITIES
// ============================================================

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => document.querySelectorAll(selector);

// ============================================================
// AJAX HELPER
// ============================================================

async function ajax(url, options = {}) {
  const defaultOptions = {
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  };
  
  const mergedOptions = { ...defaultOptions, ...options };
  
  try {
    const response = await fetch(url, mergedOptions);
    const data = await response.json();
    
    if (!response.ok) {
      throw new Error(data.message || 'Request failed');
    }
    
    return data;
  } catch (error) {
    console.error('AJAX error:', error);
    throw error;
  }
}

// GET request helper
async function get(url) {
  return ajax(url, { method: 'GET' });
}

// POST request helper
async function post(url, data) {
  return ajax(url, {
    method: 'POST',
    body: JSON.stringify(data)
  });
}

// PUT request helper
async function put(url, data) {
  return ajax(url, {
    method: 'PUT',
    body: JSON.stringify(data)
  });
}

// DELETE request helper
async function del(url) {
  return ajax(url, { method: 'DELETE' });
}

// ============================================================
// TOAST NOTIFICATIONS
// ============================================================

function showToast(message, type = 'success', duration = 3000) {
  const toast = document.createElement('div');
  toast.className = `alert alert-${type}`;
  toast.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    animation: slideIn 0.3s ease;
  `;
  toast.textContent = message;
  
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.style.animation = 'slideOut 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// Add CSS animations for toast
const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  @keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
  }
`;
document.head.appendChild(style);

// ============================================================
// MODAL HANDLING
// ============================================================

function openModal(modalId) {
  const modal = $(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = $(modalId);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function closeAllModals() {
  $$('.modal-overlay.active').forEach(modal => {
    modal.classList.remove('active');
  });
  document.body.style.overflow = '';
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
    document.body.style.overflow = '';
  }
});

// Close modal on ESC key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeAllModals();
  }
});

// ============================================================
// CONFIRMATION DIALOG
// ============================================================

function confirm(message = 'Are you sure?') {
  return new Promise((resolve) => {
    const confirmed = window.confirm(message);
    resolve(confirmed);
  });
}

// ============================================================
// FORM HANDLING
// ============================================================

// Auto-submit form on change
function autoSubmitForm(selector) {
  $$(selector).forEach(form => {
    form.addEventListener('change', () => {
      form.submit();
    });
  });
}

// Form validation helper
function validateForm(form) {
  const inputs = form.querySelectorAll('[required]');
  let isValid = true;
  
  inputs.forEach(input => {
    if (!input.value.trim()) {
      input.classList.add('error');
      isValid = false;
    } else {
      input.classList.remove('error');
    }
  });
  
  return isValid;
}

// ============================================================
// TABLE SEARCH & SORT
// ============================================================

function initTableSearch(tableSelector, searchSelector) {
  const table = $(tableSelector);
  const searchInput = $(searchSelector);
  
  if (!table || !searchInput) return;
  
  searchInput.addEventListener('input', (e) => {
    const term = e.target.value.toLowerCase();
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(term) ? '' : 'none';
    });
  });
}

function initTableSort(tableSelector) {
  const table = $(tableSelector);
  if (!table) return;
  
  const headers = table.querySelectorAll('th[data-sortable]');
  
  headers.forEach(header => {
    header.style.cursor = 'pointer';
    header.addEventListener('click', () => {
      const index = Array.from(header.parentNode.children).indexOf(header);
      const ascending = !header.classList.contains('asc');
      
      // Remove sort classes from all headers
      headers.forEach(h => h.classList.remove('asc', 'desc'));
      header.classList.add(ascending ? 'asc' : 'desc');
      
      sortTable(table, index, ascending);
    });
  });
}

function sortTable(table, column, ascending = true) {
  const tbody = table.querySelector('tbody');
  const rows = Array.from(tbody.querySelectorAll('tr'));
  
  rows.sort((a, b) => {
    const aText = a.children[column].textContent.trim();
    const bText = b.children[column].textContent.trim();
    
    const aNum = parseFloat(aText);
    const bNum = parseFloat(bText);
    
    if (!isNaN(aNum) && !isNaN(bNum)) {
      return ascending ? aNum - bNum : bNum - aNum;
    }
    
    return ascending 
      ? aText.localeCompare(bText)
      : bText.localeCompare(aText);
  });
  
  rows.forEach(row => tbody.appendChild(row));
}

// ============================================================
// NAVIGATION
// ============================================================

function setActiveNav(selector) {
  const currentPath = window.location.href;
  
  $$(selector).forEach(link => {
    if (link.href === currentPath) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });
}

// Mobile sidebar toggle
function toggleSidebar() {
  const sidebar = $('.sidebar');
  if (sidebar) {
    sidebar.classList.toggle('active');
  }
}

// ============================================================
// NOTIFICATION BELL
// ============================================================

let notificationPollInterval = null;

function initNotificationBell() {
  const bell = $('.notification-bell');
  const dropdown = $('.notification-dropdown');
  
  if (!bell || !dropdown) return;
  
  // Toggle dropdown
  bell.addEventListener('click', async () => {
    dropdown.classList.toggle('active');
    
    if (dropdown.classList.contains('active')) {
      await loadNotifications();
    }
  });
  
  // Close when clicking outside
  document.addEventListener('click', (e) => {
    if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.classList.remove('active');
    }
  });
  
  // Poll for updates every 60 seconds
  startNotificationPoll();
}

async function loadNotifications() {
  try {
    const data = await get('/api/notifications.php');
    updateNotificationUI(data);
  } catch (error) {
    console.error('Failed to load notifications');
  }
}

function updateNotificationUI(data) {
  const badge = $('.notification-badge');
  const list = $('.notification-list');
  
  if (badge && data.unread_count !== undefined) {
    badge.textContent = data.unread_count;
    badge.style.display = data.unread_count > 0 ? 'flex' : 'none';
  }
  
  if (list && data.notifications) {
    list.innerHTML = data.notifications.map(n => `
      <div class="notification-item" data-id="${n.id}">
        <strong>${escapeHtml(n.title)}</strong>
        <p>${escapeHtml(n.message)}</p>
        <small>${timeAgo(n.created_at)}</small>
      </div>
    `).join('');
  }
}

function startNotificationPoll() {
  if (notificationPollInterval) return;
  
  notificationPollInterval = setInterval(async () => {
    try {
      const data = await get('/api/notifications.php');
      const badge = $('.notification-badge');
      
      if (badge && data.unread_count !== undefined) {
        badge.textContent = data.unread_count;
        badge.style.display = data.unread_count > 0 ? 'flex' : 'none';
      }
    } catch (error) {
      console.error('Notification poll failed');
    }
  }, 60000);
}

// ============================================================
// UTILITY FUNCTIONS
// ============================================================

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function timeAgo(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const seconds = Math.floor((now - date) / 1000);
  
  if (seconds < 60) return 'Just now';
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
  if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
  
  return date.toLocaleDateString();
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'BDT'
  }).format(amount);
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  });
}

// ============================================================
// INITIALIZATION
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  // Set active navigation
  setActiveNav('.sidebar-nav a');
  
  // Initialize notification bell
  initNotificationBell();
  
  // Initialize table features
  initTableSearch('.table', '.table-search');
  initTableSort('.table-sortable');
  
  // Auto-submit forms with data-auto-submit
  autoSubmitForm('form[data-auto-submit]');
  
  // Confirm delete buttons
  $$('.btn-delete').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      if (!await confirm('Are you sure you want to delete this?')) {
        e.preventDefault();
      }
    });
  });
});

// ============================================================
// BARCODE SCANNER PLACEHOLDER
// ============================================================

// Barcode scanner is loaded dynamically when needed
// See barcode.js for implementation
