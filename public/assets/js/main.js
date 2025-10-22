/**
 * Alumni Portal - Main JavaScript
 */

// Global variables
let messagePollInterval;
let currentMessageRoom = null;
const MESSAGE_POLL_INTERVAL = 5000; // 5 seconds

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * Initialize the application
 */
function initializeApp() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Initialize form validation
    initializeFormValidation();

    // Initialize AJAX forms
    initializeAjaxForms();

    // Progressive UI enhancements across pages (non-breaking)
    initializePageEnhancements();

    // Initialize message if on message page
    if (window.location.pathname.includes('message')) {
        initializeMessage();
    }

    // Initialize notifications
    initializeNotifications();

    // Initialize dark mode toggle
    initializeDarkMode();
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Initialize AJAX forms
 */
function initializeAjaxForms() {
    const ajaxForms = document.querySelectorAll('form[data-ajax="true"]');
    
    ajaxForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitAjaxForm(form);
        });
    });
}

/**
 * Submit form via AJAX
 */
function submitAjaxForm(form) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';
    submitBtn.disabled = true;
    
    fetch(form.action || window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message || 'Operation completed successfully');
            if (data.redirect) {
                setTimeout(() => window.location.href = data.redirect, 1000);
            }
        } else {
            showAlert('danger', data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while processing your request');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

/**
 * Initialize message functionality
 */
function initializeMessage() {
    const messageForm = document.getElementById('messageForm');
    const messageInput = document.getElementById('messageInput');
    const messagesContainer = document.getElementById('messagesContainer');
    
    if (messageForm && messageInput && messagesContainer) {
        // Ensure action contains room_id for non-JS fallback
        const roomId = getCurrentRoomId();
        if (roomId) {
            messageForm.setAttribute('action', `message.php?room_id=${encodeURIComponent(roomId)}`);
        }
        // Load initial messages
        loadMessages();
        
        // Start polling for new messages
        startMessagePolling();
        
        // Handle form submission
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });
        
        // Auto-resize textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
        // Enter to send, Shift+Enter for newline
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                messageForm.dispatchEvent(new Event('submit'));
            }
        });
        
        // Scroll to bottom
        scrollToBottom();
    }
}

// Join room from UI
async function joinRoom(roomId) {
    if (!roomId) {
        showAlert('danger', 'No room ID provided');
        return;
    }
    
    const csrfToken = getCSRFToken();
    if (!csrfToken) {
        showAlert('danger', 'CSRF token not found');
        return;
    }
    
    try {
        const res = await fetch('api/message_join.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ room_id: roomId, csrf_token: csrfToken })
        });
        
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        }
        
        const data = await res.json();
        if (data.success) {
            showAlert('success', 'Joined room successfully');
            loadMessages();
            startMessagePolling();
            // Re-enable send button if disabled
            const btn = document.querySelector('#messageForm button[type="submit"]');
            if (btn) btn.disabled = false;
        } else {
            showAlert('danger', data.message || 'Failed to join room');
        }
    } catch (error) {
        console.error('Error joining room:', error);
        showAlert('danger', 'Network error joining room: ' + error.message);
    }
}

/**
 * Load message messages
 */
function loadMessages() {
    const roomId = getCurrentRoomId();
    if (!roomId) {
        console.error('No room ID found');
        return;
    }
    
    fetch(`api/message_messages.php?room_id=${roomId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        let data = {};
        try { 
            data = await response.json(); 
        } catch (e) {
            console.error('Error parsing JSON:', e);
        }
        
        if (!response.ok) {
            if (response.status === 403) {
                // Try to join the room automatically
                attemptJoinRoom(roomId)
                    .then(joined => {
                        if (joined) {
                            loadMessages();
                            startMessagePolling();
                        } else {
                            showAlert('warning', data.message || 'You are not a participant in this message room.');
                            stopMessagePolling();
                        }
                    });
            } else {
                showAlert('danger', data.message || 'Failed to load messages');
            }
            return;
        }
        if (data.success) {
            displayMessages(data.messages);
        }
    })
    .catch(error => {
        console.error('Error loading messages:', error);
        showAlert('danger', 'Network error loading messages: ' + error.message);
    });
}

/**
 * Send message
 */
function sendMessage() {
    const messageInput = document.getElementById('messageInput');
    const fileInput = document.getElementById('messageFile');
    const roomId = getCurrentRoomId();
    const message = (messageInput.value || '').trim();
    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

    if (!roomId) return;
    if (!hasFile && !message) return;
    
    const formData = new FormData();
    formData.append('room_id', roomId);
    formData.append('message', message);
    formData.append('csrf_token', getCSRFToken());
    if (hasFile) {
        formData.append('file', fileInput.files[0]);
    }
    
    fetch('api/message_send.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        let data = {};
        try { data = await response.json(); } catch (e) {}
        if (!response.ok) {
            if (response.status === 403) {
                // Try to join and resend once
                attemptJoinRoom(roomId).then(joined => {
                    if (joined) {
                        sendMessage();
                    } else {
                        showAlert('warning', data.message || 'You are not a participant in this message room.');
                    }
                });
            } else if (response.status === 429) {
                showAlert('warning', data.message || 'Rate limit exceeded.');
            } else {
                showAlert('danger', data.message || 'Failed to send message');
            }
            return;
        }
        if (data.success) {
            messageInput.value = '';
            messageInput.style.height = 'auto';
            if (fileInput) fileInput.value = '';
            loadMessages(); // Reload to show new message
        } else {
            showAlert('danger', data.message || 'Failed to send message');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Failed to send message');
    });
}

async function attemptJoinRoom(roomId) {
    try {
        const res = await fetch('api/message_join.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ room_id: roomId, csrf_token: getCSRFToken() })
        });
        const data = await res.json();
        return res.ok && data.success;
    } catch (e) {
        return false;
    }
}

/**
 * Start message polling
 */
function startMessagePolling() {
    if (messagePollInterval) {
        clearInterval(messagePollInterval);
    }
    
    messagePollInterval = setInterval(() => {
        loadMessages();
    }, MESSAGE_POLL_INTERVAL || 5000);
}

/**
 * Stop message polling
 */
function stopMessagePolling() {
    if (messagePollInterval) {
        clearInterval(messagePollInterval);
        messagePollInterval = null;
    }
}

/**
 * Display messages in message container
 */
function displayMessages(messages) {
    const container = document.getElementById('messagesContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    messages.forEach(message => {
        const messageEl = createMessageElement(message);
        container.appendChild(messageEl);
    });
    
    scrollToBottom();
}

/**
 * Create message element
 */
function createMessageElement(message) {
    const div = document.createElement('div');
    div.className = `message ${message.sender_id === getCurrentUserId() ? 'sent' : 'received'}`;
    
    const time = new Date(message.created_at).toLocaleTimeString();
    
    let contentHtml = '';
    if (message.message_type === 'image' && message.file_path) {
        contentHtml += `<div class="mb-2"><a href="${message.file_path}" target="_blank"><img src="${message.file_path}" class="img-fluid rounded"></a></div>`;
    } else if (message.message_type === 'file' && message.file_path) {
        const fileName = message.file_path.split('/').pop();
        contentHtml += `<div class="mb-2"><a href="${message.file_path}" target="_blank"><i class="fas fa-paperclip"></i> ${escapeHtml(fileName)}</a></div>`;
    }
    if (message.message) {
        contentHtml += `<div class="message-text">${escapeHtml(message.message)}</div>`;
    }

    div.innerHTML = `
        <div class="message-header">
            <strong>${escapeHtml(message.sender_name)}</strong>
            <small class="text-muted">${time}</small>
        </div>
        <div class="message-content">${contentHtml}</div>
    `;
    
    return div;
}

/**
 * Scroll to bottom of message
 */
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

/**
 * Get current room ID
 */
function getCurrentRoomId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('room_id') || currentMessageRoom;
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return document.body.getAttribute('data-user-id') || null;
}

/**
 * Get CSRF token
 */
function getCSRFToken() {
    const token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
}

/**
 * Initialize notifications
 */
function initializeNotifications() {
    // Check for new notifications periodically
    setInterval(checkNotifications, 30000); // Every 30 seconds
}

/**
 * Check for new notifications
 */
function checkNotifications() {
    fetch('api/notifications.php?action=check')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count > 0) {
                updateNotificationBadge(data.count);
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}

/**
 * Update notification badge
 */
function updateNotificationBadge(count) {
    const badge = document.querySelector('#notificationsDropdown .badge');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'block' : 'none';
    }
}

/**
 * Initialize dark mode toggle
 */
function initializeDarkMode() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    
    if (darkModeToggle) {
        // Check saved preference
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        if (isDarkMode) {
            document.body.classList.add('dark-mode');
            darkModeToggle.checked = true;
        }
        
        darkModeToggle.addEventListener('change', function() {
            if (this.checked) {
                document.body.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'true');
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'false');
            }
        });
    }
}

/**
 * Show alert message
 */
function showAlert(type, message, duration = 5000) {
    const alertContainer = document.getElementById('alertContainer') || createAlertContainer();
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    // Auto-dismiss after duration
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, duration);
}

/**
 * Create alert container if it doesn't exist
 */
function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alertContainer';
    container.className = 'position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

/**
 * Format date for display
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) {
        return 'Yesterday';
    } else if (diffDays < 7) {
        return `${diffDays} days ago`;
    } else {
        return date.toLocaleDateString();
    }
}

/**
 * Confirm action with user
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showAlert('success', 'Copied to clipboard!');
    }, function(err) {
        console.error('Could not copy text: ', err);
        showAlert('danger', 'Failed to copy to clipboard');
    });
}

/**
 * Load more content (for infinite scroll)
 */
function loadMoreContent(url, container, callback) {
    const loadingIndicator = document.getElementById('loadingIndicator');
    if (loadingIndicator) {
        loadingIndicator.style.display = 'block';
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                callback(data.content);
            }
        })
        .catch(error => {
            console.error('Error loading more content:', error);
            showAlert('danger', 'Failed to load more content');
        })
        .finally(() => {
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
        });
}

// Cleanup when page unloads
window.addEventListener('beforeunload', function() {
    stopMessagePolling();
});

/**
 * Non-breaking UI enhancements applied globally
 */
function initializePageEnhancements() {
    enhanceTables();
    enhanceActionRows();
}

function enhanceTables() {
    document.querySelectorAll('table').forEach(function(tbl){
        if (!tbl.classList.contains('table')) {
            tbl.classList.add('table','table-striped','table-hover');
        }
        if (tbl.querySelector('thead')) {
            tbl.classList.add('table-sticky');
        }
        // Wrap table in a responsive container if not already
        if (!tbl.closest('.table-responsive')) {
            const wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            tbl.parentNode.insertBefore(wrap, tbl);
            wrap.appendChild(tbl);
        }
    });
}

function enhanceActionRows() {
    // Add spacing utilities to common action toolbars without changing layout
    document.querySelectorAll('.actions-row, .btn-toolbar').forEach(function(row){
        row.classList.add('d-flex','flex-wrap','gap-2');
    });
}
