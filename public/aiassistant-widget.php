<?php
/**
 * AI Assistant Widget Component
 * Include this file to add the AI assistant functionality to any page
 */

require_once __DIR__ . '/../aiassi/lib/config.php';
use Aiassi\Lib\Config;

$storageDir = __DIR__ . '/../aiassi/storage';
$config = new Config($storageDir);

// Check for logo
$logoPath = '';
$logoExts = ['png','jpg','jpeg','gif','webp'];
foreach ($logoExts as $ext) {
    $path = $storageDir . DIRECTORY_SEPARATOR . 'logo.' . $ext;
    if (is_file($path)) {
        $logoPath = '../aiassi/storage/logo.' . $ext;
        break;
    }
}

$chatButtonText = $config->getChatButtonText();
$chatButtonColor = $config->getChatButtonColor();
?>

<!-- AI Assistant Widget Styles -->
<style>
.aiassistant-button {
    position: fixed;
    right: 24px;
    bottom: 75px;
    background: <?= htmlspecialchars($chatButtonColor) ?>;
    color: #fff;
    border: none;
    border-radius: 50px;
    padding: 14px 18px;
    box-shadow: 0 10px 20px rgba(0,0,0,.15);
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    z-index: 1000;
    font-family: inherit;
    font-size: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.aiassistant-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 30px rgba(0,0,0,.2);
}

.aiassistant-modal {
    position: fixed;
    right: 24px;
    bottom: 88px;
    width: 360px;
    max-width: calc(100vw - 32px);
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0,0,0,.2);
    display: none;
    overflow: hidden;
    z-index: 1001;
    font-family: inherit;
}

.aiassistant-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 14px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
}

.aiassistant-body {
    max-height: 420px;
    overflow-y: auto;
    padding: 12px;
    background: #f8f9fa;
}

.aiassistant-message {
    margin: 8px 0;
    padding: 10px 12px;
    border-radius: 12px;
    max-width: 85%;
    word-wrap: break-word;
}

.aiassistant-message.user {
    background: #007bff;
    color: white;
    margin-left: auto;
    text-align: right;
}

.aiassistant-message.bot {
    background: #fff;
    color: #333;
    border: 1px solid #e9ecef;
}

.aiassistant-form {
    border-top: 1px solid #e9ecef;
    padding: 12px;
    background: #fff;
    display: flex;
    gap: 8px;
}

.aiassistant-input {
    flex: 1;
    border: 1px solid #ced4da;
    border-radius: 20px;
    padding: 8px 16px;
    outline: none;
    font-size: 14px;
}

.aiassistant-input:focus {
    border-color: <?= htmlspecialchars($chatButtonColor) ?>;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.aiassistant-send {
    background: <?= htmlspecialchars($chatButtonColor) ?>;
    color: white;
    border: none;
    border-radius: 20px;
    padding: 8px 16px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.aiassistant-send:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.aiassistant-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.8);
    font-size: 18px;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: color 0.2s ease;
}

.aiassistant-close:hover {
    color: white;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .aiassistant-modal {
        right: 16px;
        bottom: 80px;
        width: calc(100vw - 32px);
        max-width: none;
    }
    
    .aiassistant-button {
        right: 5px;
        bottom: 75px;
    }
}
</style>

<!-- AI Assistant Widget HTML -->
<button id="aiassistantBtn" class="aiassistant-button">
    <?php if ($logoPath): ?>
    <img src="<?= htmlspecialchars($logoPath) ?>" alt="AI Assistant" class="w-5 h-5 rounded" />
    <?php else: ?>
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    <?php endif; ?>
    <span><?= htmlspecialchars($chatButtonText) ?></span>
</button>

<div id="aiassistantModal" class="aiassistant-modal">
    <div class="aiassistant-header">
        <div class="flex items-center gap-2" style="display: flex; align-items: center; gap: 8px;">
            <?php if ($logoPath): ?>
            <img src="<?= htmlspecialchars($logoPath) ?>" alt="AI Assistant" style="width: 24px; height: 24px; border-radius: 4px;" />
            <?php endif; ?>
            <div style="font-weight: 600;">AI Assistant</div>
        </div>
        <button id="aiassistantClose" class="aiassistant-close">✕</button>
    </div>
    <div class="aiassistant-body" id="aiassistantBody">
        <div class="aiassistant-message bot">
            👋 Hello! I'm your AI assistant. How can I help you today?
        </div>
    </div>
    <form id="aiassistantForm" class="aiassistant-form">
        <input id="aiassistantInput" class="aiassistant-input" placeholder="Type your message..." autocomplete="off" />
        <button type="submit" class="aiassistant-send">Send</button>
    </form>
</div>

<!-- AI Assistant Widget JavaScript -->
<script>
(function() {
    const aiassistantBtn = document.getElementById('aiassistantBtn');
    const aiassistantModal = document.getElementById('aiassistantModal');
    const aiassistantClose = document.getElementById('aiassistantClose');
    const aiassistantBody = document.getElementById('aiassistantBody');
    const aiassistantForm = document.getElementById('aiassistantForm');
    const aiassistantInput = document.getElementById('aiassistantInput');

    if (!aiassistantBtn || !aiassistantModal || !aiassistantClose || !aiassistantBody || !aiassistantForm || !aiassistantInput) {
        console.error('AI Assistant: Required elements not found');
        return;
    }

    const addMessage = (text, sender) => {
        const div = document.createElement('div');
        div.className = 'aiassistant-message ' + sender;
        div.textContent = text;
        aiassistantBody.appendChild(div);
        aiassistantBody.scrollTop = aiassistantBody.scrollHeight;
    };

    const toggleChat = () => {
        const isVisible = aiassistantModal.style.display === 'block';
        aiassistantModal.style.display = isVisible ? 'none' : 'block';
        if (!isVisible) {
            aiassistantInput.focus();
        }
    };

    aiassistantBtn.addEventListener('click', toggleChat);
    aiassistantClose.addEventListener('click', () => {
        aiassistantModal.style.display = 'none';
    });

    aiassistantForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = aiassistantInput.value.trim();
        if (!message) return;

        addMessage(message, 'user');
        aiassistantInput.value = '';
        addMessage('Thinking...', 'bot');

        try {
            const response = await fetch('api/aiassistant.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message: message })
            });

            const text = await response.text();
            let data;
            
            try {
                data = JSON.parse(text);
            } catch {
                data = { error: text || 'Invalid server response' };
            }

            // Remove "Thinking..." message
            const lastMessage = aiassistantBody.lastElementChild;
            if (lastMessage && lastMessage.textContent === 'Thinking...') {
                lastMessage.remove();
            }

            if (!response.ok) {
                addMessage(data.error ? `Error: ${data.error}` : 'Server error occurred', 'bot');
            } else {
                const answer = data && typeof data === 'object' && 'answer' in data 
                    ? data.answer 
                    : text;
                addMessage(answer || 'No response received', 'bot');
            }
        } catch (error) {
            // Remove "Thinking..." message
            const lastMessage = aiassistantBody.lastElementChild;
            if (lastMessage && lastMessage.textContent === 'Thinking...') {
                lastMessage.remove();
            }
            addMessage('Network error. Please try again.', 'bot');
        }
    });

    // Close modal when clicking outside
    document.addEventListener('click', (e) => {
        if (!aiassistantModal.contains(e.target) && !aiassistantBtn.contains(e.target)) {
            if (aiassistantModal.style.display === 'block') {
                aiassistantModal.style.display = 'none';
            }
        }
    });
})();
</script>
