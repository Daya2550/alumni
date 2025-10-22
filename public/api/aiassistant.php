<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../aiassi/lib/config.php';
require_once __DIR__ . '/../../aiassi/lib/rag.php';
require_once __DIR__ . '/../../aiassi/lib/utils.php';

use Aiassi\Lib\Config;
use Aiassi\Lib\RagIndex;

// Require user to be logged in
Auth::requireLogin();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$storageDir = __DIR__ . '/../../aiassi/storage';
$config = new Config($storageDir);
$rag = new RagIndex($storageDir);

$sendError = function(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
};

if (!extension_loaded('curl')) {
    $sendError(500, 'PHP cURL extension is not enabled. Enable extension=curl in php.ini and restart Apache.');
}

$body = file_get_contents('php://input') ?: '';
$req = json_decode($body, true);
if (!is_array($req)) { $sendError(400, 'Invalid JSON body'); }

$message = (string) ($req['message'] ?? '');
if ($message === '') { $sendError(400, 'Message is required'); }

$topK = $config->getRagSetting('top_k', 4);
$contextItems = $rag->retrieve($message, $topK);
$contextText = '';
foreach ($contextItems as $i => $it) {
    $contextText .= "[Context #" . ($i+1) . " from " . $it['file'] . "]\n" . $it['chunk'] . "\n\n";
}

$provider = $config->getProvider();
try {
    if ($provider === 'gemini') {
        $apiKey = $config->getApiKey('gemini');
        if ($apiKey === '') { $sendError(400, 'Gemini API key not set by admin.'); }
        $model = $config->getModel('gemini');
        // Normalize legacy model names to current variants
        if ($model === 'gemini-1.5-flash') { $model = 'gemini-1.5-flash-latest'; }
        if ($model === 'gemini-1.5-pro') { $model = 'gemini-1.5-pro-latest'; }
        $answer = call_gemini($apiKey, $model, $message, $contextText);
    } else if ($provider === 'deepseek') {
        $apiKey = $config->getApiKey('deepseek');
        if ($apiKey === '') { $sendError(400, 'DeepSeek API key not set by admin.'); }
        if (strpos($apiKey, '*') !== false) { $sendError(400, 'DeepSeek API key appears masked or invalid. Please paste the full key.'); }
        $model = $config->getModel('deepseek');
        $answer = call_deepseek($apiKey, $model, $message, $contextText);
    } else {
        $apiKey = $config->getApiKey('openai');
        if ($apiKey === '') { $sendError(400, 'OpenAI API key not set by admin.'); }
        $model = $config->getModel('openai');
        $answer = call_openai($apiKey, $model, $message, $contextText);
    }
    echo json_encode(['answer' => $answer, 'context' => $contextItems]);
} catch (Throwable $e) {
    $sendError(500, 'Server error: ' . $e->getMessage());
}

function call_openai(string $apiKey, string $model, string $message, string $context): string
{
    if ($apiKey === '') return 'OpenAI API key not set by admin.';
    $url = 'https://api.openai.com/v1/chat/completions';
    $prompt = "You are a helpful assistant for an alumni portal system. Use the provided context to answer questions about the portal, events, jobs, news, and other alumni-related topics.\n\n" .
        "Context:\n" . $context . "\n\n" .
        "Question: " . $message . "\n\n" .
        "Provide a helpful and concise answer. If the context doesn't contain relevant information, provide general assistance about alumni portal features.but @start with then give the inforamtion outside context.";
    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful AI assistant for an alumni portal. Answer questions about the portal features, events, jobs, and help users navigate the system.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.2,
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);
    $res = curl_exec($ch);
    if ($res === false) return 'Error contacting OpenAI.';
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($res, true);
    if ($code >= 200 && $code < 300 && isset($json['choices'][0]['message']['content'])) {
        return (string) $json['choices'][0]['message']['content'];
    }
    return 'OpenAI error: ' . ($json['error']['message'] ?? 'unknown');
}

function call_gemini(string $apiKey, string $model, string $message, string $context): string
{
    if ($apiKey === '') return 'Gemini API key not set by admin.';
    $candidates = [$model];
    // Try a couple of reasonable fallbacks
    if ($model !== 'gemini-2.5-flash') { $candidates[] = 'gemini-2.5-flash'; }
    if ($model !== 'gemini-1.5-flash-latest') { $candidates[] = 'gemini-1.5-flash-latest'; }
    if ($model !== 'gemini-1.5-pro-latest') { $candidates[] = 'gemini-1.5-pro-latest'; }
    $prompt = "You are a helpful assistant for an alumni portal system. Use the provided context to answer questions about the portal, events, jobs, news, and other alumni-related topics.\n\n" .
        "Context:\n" . $context . "\n\n" .
        "Question: " . $message . "\n\n" .
        "Provide a helpful and concise answer. If the context doesn't contain relevant information, provide general assistance about alumni portal features.";
    $payload = [
        'contents' => [ [ 'role' => 'user', 'parts' => [ ['text' => $prompt] ] ] ],
        'generationConfig' => [ 'temperature' => 0.2 ],
    ];
    foreach ($candidates as $m) {
        $urls = [
            'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($m) . ':generateContent?key=' . rawurlencode($apiKey),
            'https://generativelanguage.googleapis.com/v1/models/' . rawurlencode($m) . ':generateContent?key=' . rawurlencode($apiKey),
        ];
        foreach ($urls as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [ 'Content-Type: application/json' ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 30,
            ]);
            $res = curl_exec($ch);
            if ($res === false) { curl_close($ch); continue; }
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $json = json_decode($res, true);
            if ($code >= 200 && $code < 300 && isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                return (string) $json['candidates'][0]['content']['parts'][0]['text'];
            }
            if (!empty($json['error']['message']) && strpos((string)$json['error']['message'], 'not found') === false && strpos((string)$json['error']['message'], 'not supported') === false) {
                return 'Gemini error: ' . (string) $json['error']['message'];
            }
        }
    }
    return 'Gemini error: selected model not available for generateContent.';
}

function call_deepseek(string $apiKey, string $model, string $message, string $context): string
{
    if ($apiKey === '') return 'DeepSeek API key not set by admin.';
    $url = 'https://api.deepseek.com/v1/chat/completions';
    $prompt = "You are a helpful assistant for an alumni portal system. Use the provided context to answer questions about the portal, events, jobs, news, and other alumni-related topics.\n\n" .
        "Context:\n" . $context . "\n\n" .
        "Question: " . $message . "\n\n" .
        "Provide a helpful and concise answer. If the context doesn't contain relevant information, provide general assistance about alumni portal features.";
    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful AI assistant for an alumni portal. Answer questions about the portal features, events, jobs, and help users navigate the system.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.2,
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);
    $res = curl_exec($ch);
    if ($res === false) return 'Error contacting DeepSeek.';
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($res, true);
    if ($code >= 200 && $code < 300 && isset($json['choices'][0]['message']['content'])) {
        return (string) $json['choices'][0]['message']['content'];
    }
    return 'DeepSeek error: ' . ($json['error']['message'] ?? 'unknown');
}
?>

