<?php
declare(strict_types=1);

use Chatbot\Lib\Config;
use Chatbot\Lib\RagIndex;
use Chatbot\Lib\Utils;

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
    if (isset($_POST['provider'])) {
        $config->setProvider($_POST['provider'] === 'openai' ? 'openai' : 'gemini');
    }
    if (isset($_POST['openai_api_key'])) {
        $config->setApiKey('openai', trim((string) $_POST['openai_api_key']));
    }
    if (isset($_POST['gemini_api_key'])) {
        $config->setApiKey('gemini', trim((string) $_POST['gemini_api_key']));
    }
    $config->save();

    if (!empty($_FILES['dataset']['name'] ?? '')) {
        $file = $_FILES['dataset'];
        if (($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
            $name = Utils::slugify((string) $file['name']);
            $dest = $uploadsDir . DIRECTORY_SEPARATOR . $name;
            if (@move_uploaded_file($file['tmp_name'], $dest)) {
                $rag->addFile($dest, $config->getRagSetting('chunk_size', 1200), $config->getRagSetting('chunk_overlap', 200));
                $message = 'Dataset uploaded and indexed: ' . htmlspecialchars($name);
            } else {
                $message = 'Failed to move uploaded file.';
            }
        } else {
            $message = 'Upload error code: ' . (int) $file['error'];
        }
    } else {
        $message = 'Settings saved.';
    }
}

$data = $config->all();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin - Chatbot</title>
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
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-medium">OpenAI API Key</label>
                    <input type="password" name="openai_api_key" value="<?= htmlspecialchars($data['openai']['api_key'] ?? '') ?>" class="border rounded px-3 py-2 w-full" />
                </div>
                <div>
                    <label class="block font-medium">Gemini API Key</label>
                    <input type="password" name="gemini_api_key" value="<?= htmlspecialchars($data['gemini']['api_key'] ?? '') ?>" class="border rounded px-3 py-2 w-full" />
                </div>
            </div>

            <div>
                <label class="block font-medium mb-1">Upload dataset (txt/csv/md)</label>
                <input type="file" name="dataset" class="block w-full" />
                <p class="text-sm text-gray-500 mt-1">Each upload is chunked and added to the index.</p>
            </div>

            <div class="flex justify-end">
                <button class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            </div>
        </form>
    </div>
</body>
</html>


