<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
use Aiassi\Lib\Config;

$storageDir = __DIR__ . '/storage';
$config = new Config($storageDir);
$logoPath = '';
$logoExts = ['png','jpg','jpeg','gif','webp'];
foreach ($logoExts as $ext) {
    $path = $storageDir . DIRECTORY_SEPARATOR . 'logo.' . $ext;
    if (is_file($path)) {
        $logoPath = '/aiassi/storage/logo.' . $ext;
        break;
    }
}
$chatButtonText = $config->getChatButtonText();
$chatButtonColor = $config->getChatButtonColor();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Home - Chatbot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
      body{font-family:'Inter',sans-serif}
      .chat-button{position:fixed;right:24px;bottom:24px;background:<?= htmlspecialchars($chatButtonColor) ?>;color:#fff;border-radius:9999px;padding:14px 18px;box-shadow:0 10px 20px rgba(0,0,0,.15);display:flex;align-items:center;gap:8px}
      .chat-modal{position:fixed;right:24px;bottom:88px;width:360px;max-width:calc(100vw - 32px);background:#fff;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,.2);display:none;overflow:hidden}
      .chat-header{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;background:#1f2937;color:#fff}
      .chat-body{max-height:420px;overflow:auto;padding:12px}
      .msg{margin:8px 0;padding:10px 12px;border-radius:12px}
      .msg-user{background:#e0f2fe;align-self:flex-end}
      .msg-bot{background:#f3f4f6}
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold">Home</h1>
            <a href="/aiassi/admin.php" class="text-blue-600 hover:underline">Admin</a>
        </div>
        <p class="mt-4 text-gray-600">Welcome. Use the chat button to ask questions against the uploaded dataset.</p>
    </div>

    <button id="chatBtn" class="chat-button">
        <?php if ($logoPath): ?>
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" class="w-5 h-5 rounded" />
        <?php else: ?>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <?php endif; ?>
        <span><?= htmlspecialchars($chatButtonText) ?></span>
    </button>

    <div id="chatModal" class="chat-modal">
        <div class="chat-header">
            <div class="flex items-center gap-2">
                <?php if ($logoPath): ?>
                <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" class="w-6 h-6 rounded" />
                <?php endif; ?>
                <div class="font-semibold">AI Assistant</div>
            </div>
            <button id="closeChat" class="text-white/80 hover:text-white">✕</button>
        </div>
        <div class="chat-body" id="chatBody"></div>
        <form id="chatForm" class="border-t p-2 bg-white flex gap-2">
            <input id="chatInput" class="flex-1 border rounded px-3 py-2" placeholder="Type your message..." />
            <button class="bg-blue-600 text-white px-3 py-2 rounded">Send</button>
        </form>
    </div>

    <script>
      const chatBtn = document.getElementById('chatBtn');
      const chatModal = document.getElementById('chatModal');
      const closeChat = document.getElementById('closeChat');
      const chatBody = document.getElementById('chatBody');
      const chatForm = document.getElementById('chatForm');
      const chatInput = document.getElementById('chatInput');

      const addMsg = (text, who) => {
        const div = document.createElement('div');
        div.className = 'msg ' + (who==='user'?'msg-user':'msg-bot');
        div.textContent = text;
        chatBody.appendChild(div);
        chatBody.scrollTop = chatBody.scrollHeight;
      }

      chatBtn.addEventListener('click', ()=>{
        chatModal.style.display = chatModal.style.display==='block'?'none':'block';
        if (chatModal.style.display==='block') chatInput.focus();
      });
      closeChat.addEventListener('click', ()=> chatModal.style.display='none');

      chatForm.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const msg = chatInput.value.trim();
        if (!msg) return;
        addMsg(msg, 'user');
        chatInput.value='';
        addMsg('Thinking...', 'bot');
        try {
          const res = await fetch('/public/api/aiassistant.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({message: msg})});
          const text = await res.text();
          let data;
          try { data = JSON.parse(text); } catch {
            data = { error: text || 'Invalid server response' };
          }
          const last = chatBody.lastElementChild; if (last) last.remove();
          if (!res.ok) {
            addMsg(data.error ? `Server error: ${data.error}` : 'Server error', 'bot');
          } else {
            // If response parsed as JSON and contains answer, show it; otherwise show raw text
            if (data && typeof data === 'object' && 'answer' in data) {
              addMsg(data.answer || 'No answer', 'bot');
            } else {
              addMsg(text || 'No answer', 'bot');
            }
          }
        } catch (err) {
          const last = chatBody.lastElementChild; if (last) last.remove();
          addMsg('Network error contacting server', 'bot');
        }
      });
    </script>
</body>
</html>

