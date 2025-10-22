<?php
declare(strict_types=1);

use Aiassi\Lib\Config;
use Aiassi\Lib\RagIndex;
use Aiassi\Lib\Utils;

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/rag.php';
require_once __DIR__ . '/lib/utils.php';

$storageDir = __DIR__ . '/storage';
$uploadsDir = __DIR__ . '/uploads';
@mkdir($uploadsDir, 0777, true);

$config = new Config($storageDir);
$rag = new RagIndex($storageDir);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['rebuild_index'])) {
        $rag->reset();
        $count = 0;
        $chunkSize = $config->getRagSetting('chunk_size', 1200);
        $overlap = $config->getRagSetting('chunk_overlap', 200);
        $files = @scandir($uploadsDir) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $path = $uploadsDir . DIRECTORY_SEPARATOR . $f;
            if (is_file($path)) {
                $rag->addFile($path, $chunkSize, $overlap);
                $count++;
            }
        }
        $message = 'Index rebuilt from ' . (int) $count . ' file(s).';
    }
    if (isset($_POST['provider'])) {
        $prov = in_array($_POST['provider'], ['gemini','openai','deepseek'], true) ? $_POST['provider'] : 'gemini';
        $config->setProvider($prov);
    }
    if (isset($_POST['openai_api_key'])) { $config->setApiKey('openai', trim((string) $_POST['openai_api_key'])); }
    if (isset($_POST['gemini_api_key'])) { $config->setApiKey('gemini', trim((string) $_POST['gemini_api_key'])); }
    if (isset($_POST['deepseek_api_key'])) { $config->setApiKey('deepseek', trim((string) $_POST['deepseek_api_key'])); }
    if (isset($_POST['model_openai'])) { $config->setModel('openai', trim((string) $_POST['model_openai'])); }
    if (isset($_POST['model_gemini'])) {
        $m = trim((string) $_POST['model_gemini']);
        if ($m === 'gemini-1.5-flash') $m = 'gemini-1.5-flash-latest';
        if ($m === 'gemini-1.5-pro') $m = 'gemini-1.5-pro-latest';
        $config->setModel('gemini', $m);
    }
    if (isset($_POST['model_deepseek'])) { $config->setModel('deepseek', trim((string) $_POST['model_deepseek'])); }
    if (isset($_POST['chat_button_text'])) { $config->setChatButtonText(trim((string) $_POST['chat_button_text'])); }
    if (isset($_POST['chat_button_color'])) { $config->setChatButtonColor(trim((string) $_POST['chat_button_color'])); }
    $config->save();

    if (!empty($_FILES['logo']['name'] ?? '')) {
        $file = $_FILES['logo'];
        if (($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg','gif','webp'], true)) {
                $logoPath = $storageDir . DIRECTORY_SEPARATOR . 'logo.' . $ext;
                if (@move_uploaded_file($file['tmp_name'], $logoPath)) {
                    $message = 'Logo uploaded successfully.';
                } else { $message = 'Failed to save logo file.'; }
            } else { $message = 'Logo must be png, jpg, jpeg, gif, or webp.'; }
        } else { $message = 'Logo upload error code: ' . (int) $file['error']; }
    } else if (!empty($_POST['manual_source'] ?? '') || !empty($_POST['manual_text'] ?? '')) {
        $src = trim((string) ($_POST['manual_source'] ?? 'Manual Input'));
        $txt = trim((string) ($_POST['manual_text'] ?? ''));
        if ($txt !== '') {
            $rag->addText($src, $txt, $config->getRagSetting('chunk_size', 1200), $config->getRagSetting('chunk_overlap', 200));
            $message = 'Manual text indexed: ' . htmlspecialchars($src !== '' ? $src : 'Manual Input');
        } else {
            $message = 'No text provided to index.';
        }
    } else if ($message === '') { $message = 'Settings saved.'; }
}

$data = $config->all();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin - AI Assistant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style> body{font-family:'Inter',sans-serif;} </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-3xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold">Admin</h1>
            <a href="/aiassi/index.php" class="text-blue-600 hover:underline">Go to Home</a>
        </div>

        <?php if ($message): ?>
        <div class="mb-4 p-3 rounded bg-green-50 text-green-700 border border-green-200"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="space-y-6 bg-white p-6 rounded-xl shadow">
            <div>
                <label class="block font-medium mb-1">Provider</label>
                <select name="provider" class="border rounded px-3 py-2 w-full">
                    <option value="gemini" <?= $data['provider']==='gemini'?'selected':'' ?>>Gemini</option>
                    <option value="openai" <?= $data['provider']==='openai'?'selected':'' ?>>OpenAI</option>
                    <option value="deepseek" <?= $data['provider']==='deepseek'?'selected':'' ?>>DeepSeek</option>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-medium">OpenAI API Key</label>
                    <input type="password" name="openai_api_key" value="<?= htmlspecialchars($data['openai']['api_key'] ?? '') ?>" class="border rounded px-3 py-2 w-full" />
                    <label class="block font-medium mt-2">OpenAI Model</label>
                    <input type="text" name="model_openai" value="<?= htmlspecialchars($data['model']['openai'] ?? 'gpt-4o-mini') ?>" class="border rounded px-3 py-2 w-full" />
                </div>
                <div>
                    <label class="block font-medium">Google (Gemini) API Key</label>
                    <input type="password" name="gemini_api_key" value="<?= htmlspecialchars($data['gemini']['api_key'] ?? '') ?>" class="border rounded px-3 py-2 w-full" />
                    <p class="text-sm text-gray-500 mt-1">Get a key from <a class="text-blue-600 hover:underline" href="https://aistudio.google.com/app/api-keys" target="_blank" rel="noreferrer">Google AI Studio</a> and paste it here.</p>
                    <label class="block font-medium mt-2">Gemini Model</label>
                    <input type="text" name="model_gemini" value="<?= htmlspecialchars($data['model']['gemini'] ?? 'gemini-2.5-flash') ?>" class="border rounded px-3 py-2 w-full" />
                </div>
                <div>
                    <label class="block font-medium">DeepSeek API Key</label>
                    <input type="password" name="deepseek_api_key" value="<?= htmlspecialchars($data['deepseek']['api_key'] ?? '') ?>" class="border rounded px-3 py-2 w-full" />
                    <p class="text-sm text-gray-500 mt-1">Paste the full, unmasked key. Default model: deepseek-chat.</p>
                    <label class="block font-medium mt-2">DeepSeek Model</label>
                    <input type="text" name="model_deepseek" value="<?= htmlspecialchars($data['model']['deepseek'] ?? 'deepseek-chat') ?>" class="border rounded px-3 py-2 w-full" />
                </div>
            </div>

            <div>
                <label class="block font-medium mb-1">Upload AI Assistant logo</label>
                <input type="file" name="logo" accept="image/*" class="block w-full" />
                <p class="text-sm text-gray-500 mt-1">Upload PNG, JPG, GIF, or WebP image to customize the AI Assistant appearance.</p>
            </div>

            <div>
                <label class="block font-medium mb-1">Chat Button Customization</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium mb-1">Button Text</label>
                        <input type="text" name="chat_button_text" value="<?= htmlspecialchars($config->getChatButtonText()) ?>" class="border rounded px-3 py-2 w-full" />
                    </div>
                    <div>
                        <label class="block font-medium mb-1">Button Color</label>
                        <input type="color" name="chat_button_color" value="<?= htmlspecialchars($config->getChatButtonColor()) ?>" class="border rounded px-3 py-2 w-full h-10" />
                    </div>
                </div>
            </div>

            <div>
                <label class="block font-medium mb-1">Or paste text to index</label>
                <input type="text" name="manual_source" placeholder="Source name (e.g., Knowledge Base)" class="border rounded px-3 py-2 w-full mb-2" />
                <textarea name="manual_text" rows="6" placeholder="Paste content here..." class="border rounded px-3 py-2 w-full"></textarea>
                <p class="text-sm text-gray-500 mt-1">This will be chunked and added to the index, replacing the need to upload PDFs.</p>
            </div>

            <div class="flex justify-end">
                <button class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            </div>
        </form>

        <form method="post" class="mt-4 bg-white p-6 rounded-xl shadow flex items-center justify-between">
            <div>
                <div class="font-medium">Rebuild Index</div>
                <p class="text-sm text-gray-500">Reset and index all files currently in uploads.</p>
            </div>
            <div>
                <input type="hidden" name="rebuild_index" value="1" />
                <button class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded">Rebuild</button>
            </div>
        </form>
    </div>
</body>
</html>


