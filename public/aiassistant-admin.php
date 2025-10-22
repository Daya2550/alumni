<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../aiassi/lib/config.php';
require_once __DIR__ . '/../aiassi/lib/rag.php';
require_once __DIR__ . '/../aiassi/lib/utils.php';

use Aiassi\Lib\Config;
use Aiassi\Lib\RagIndex;
use Aiassi\Lib\Utils;

// Require admin or staff access
Auth::requireAnyRole(['admin', 'staff']);
$user = Auth::getCurrentUser();

$storageDir = __DIR__ . '/../aiassi/storage';
$uploadsDir = __DIR__ . '/../aiassi/uploads';
@mkdir($uploadsDir, 0777, true);

$allowedLogoExts = ['png','jpg','jpeg','gif','webp'];
$logoExt = null;
foreach ($allowedLogoExts as $ext) {
    if (file_exists($storageDir . DIRECTORY_SEPARATOR . 'logo.' . $ext)) { $logoExt = $ext; break; }
}
$logoWebPath = $logoExt ? '../aiassi/storage/logo.' . $logoExt : null;

$config = new Config($storageDir);
$rag = new RagIndex($storageDir);
$message = '';

// Get current knowledge base entries
$knowledgeEntries = [];
$indexPath = $storageDir . DIRECTORY_SEPARATOR . 'index.json';
if (file_exists($indexPath)) {
    $indexData = json_decode(file_get_contents($indexPath), true);
    if (is_array($indexData)) {
        // Group entries by source file
        $grouped = [];
        foreach ($indexData as $i => $entry) {
            $file = $entry['file'] ?? 'unknown';
            if (!isset($grouped[$file])) {
                $grouped[$file] = ['chunks' => [], 'total_size' => 0];
            }
            $grouped[$file]['chunks'][] = [
                'index' => $i,
                'chunk' => $entry['chunk'] ?? '',
                'preview' => substr($entry['chunk'] ?? '', 0, 100) . '...'
            ];
            $grouped[$file]['total_size'] += strlen($entry['chunk'] ?? '');
        }
        $knowledgeEntries = $grouped;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle removing current logo and reverting to default
    if (!empty($_POST['remove_logo'])) {
        foreach ($allowedLogoExts as $ext) {
            @unlink($storageDir . DIRECTORY_SEPARATOR . 'logo.' . $ext);
        }
        $message = 'Logo removed. Using default.';
        $logoExt = null;
        $logoWebPath = null;
    }
    // Handle knowledge base entry deletion
    if (isset($_POST['delete_source'])) {
        $sourceToDelete = trim($_POST['delete_source']);
        if ($sourceToDelete && file_exists($indexPath)) {
            $indexData = json_decode(file_get_contents($indexPath), true);
            if (is_array($indexData)) {
                // Filter out entries from the specified source
                $filteredData = array_filter($indexData, function($entry) use ($sourceToDelete) {
                    return ($entry['file'] ?? '') !== $sourceToDelete;
                });
                // Re-index array to avoid gaps
                $filteredData = array_values($filteredData);
                file_put_contents($indexPath, json_encode($filteredData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                $message = "Deleted all entries from source: " . htmlspecialchars($sourceToDelete);
                // Refresh knowledge entries
                $knowledgeEntries = [];
                if (!empty($filteredData)) {
                    $grouped = [];
                    foreach ($filteredData as $i => $entry) {
                        $file = $entry['file'] ?? 'unknown';
                        if (!isset($grouped[$file])) {
                            $grouped[$file] = ['chunks' => [], 'total_size' => 0];
                        }
                        $grouped[$file]['chunks'][] = [
                            'index' => $i,
                            'chunk' => $entry['chunk'] ?? '',
                            'preview' => substr($entry['chunk'] ?? '', 0, 100) . '...',
                            'source' => $file
                        ];
                        $grouped[$file]['total_size'] += strlen($entry['chunk'] ?? '');
                    }
                    $knowledgeEntries = $grouped;
                }
            }
        }
    }
    // Handle chunk editing
    elseif (isset($_POST['edit_chunk'])) {
        $chunkIndex = intval($_POST['chunk_index']);
        $newContent = trim($_POST['chunk_content']);
        $newSource = trim($_POST['chunk_source']);
        
        if ($newContent && file_exists($indexPath)) {
            $indexData = json_decode(file_get_contents($indexPath), true);
            if (is_array($indexData) && isset($indexData[$chunkIndex])) {
                // Update the chunk content and source
                $indexData[$chunkIndex]['chunk'] = $newContent;
                $indexData[$chunkIndex]['file'] = $newSource ?: $indexData[$chunkIndex]['file'];
                // Re-vectorize the chunk with new content
                $indexData[$chunkIndex]['vec'] = Utils::vectorize($newContent);
                
                file_put_contents($indexPath, json_encode($indexData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                $message = "Knowledge chunk updated successfully.";
                
                // Refresh knowledge entries
                $knowledgeEntries = [];
                $grouped = [];
                foreach ($indexData as $i => $entry) {
                    $file = $entry['file'] ?? 'unknown';
                    if (!isset($grouped[$file])) {
                        $grouped[$file] = ['chunks' => [], 'total_size' => 0];
                    }
                    $grouped[$file]['chunks'][] = [
                        'index' => $i,
                        'chunk' => $entry['chunk'] ?? '',
                        'preview' => substr($entry['chunk'] ?? '', 0, 100) . '...'
                    ];
                    $grouped[$file]['total_size'] += strlen($entry['chunk'] ?? '');
                }
                $knowledgeEntries = $grouped;
            }
        }
    }
    // Handle individual chunk deletion
    elseif (isset($_POST['delete_chunk'])) {
        $chunkIndex = intval($_POST['delete_chunk']);
        if (file_exists($indexPath)) {
            $indexData = json_decode(file_get_contents($indexPath), true);
            if (is_array($indexData) && isset($indexData[$chunkIndex])) {
                unset($indexData[$chunkIndex]);
                // Re-index array
                $indexData = array_values($indexData);
                file_put_contents($indexPath, json_encode($indexData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                $message = "Deleted knowledge chunk successfully.";
                // Refresh knowledge entries
                $knowledgeEntries = [];
                if (!empty($indexData)) {
                    $grouped = [];
                    foreach ($indexData as $i => $entry) {
                        $file = $entry['file'] ?? 'unknown';
                        if (!isset($grouped[$file])) {
                            $grouped[$file] = ['chunks' => [], 'total_size' => 0];
                        }
                        $grouped[$file]['chunks'][] = [
                            'index' => $i,
                            'chunk' => $entry['chunk'] ?? '',
                            'preview' => substr($entry['chunk'] ?? '', 0, 100) . '...',
                            'source' => $file
                        ];
                        $grouped[$file]['total_size'] += strlen($entry['chunk'] ?? '');
                    }
                    $knowledgeEntries = $grouped;
                }
            }
        }
    }
    elseif (isset($_POST['rebuild_index'])) {
        $rag->reset();
        $count = 0;
        $chunkSize = $config->getRagSetting('chunk_size', 1200);
        $overlap = $config->getRagSetting('chunk_overlap', 200);
        
        // Process uploaded files
        $files = @scandir($uploadsDir) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $path = $uploadsDir . DIRECTORY_SEPARATOR . $f;
            if (is_file($path)) {
                $rag->addFile($path, $chunkSize, $overlap);
                $count++;
            }
        }
        
        $message = 'Knowledge index rebuilt successfully from ' . (int) $count . ' file(s).';
        
        // Refresh knowledge entries after rebuild
        $knowledgeEntries = [];
        if (file_exists($indexPath)) {
            $indexData = json_decode(file_get_contents($indexPath), true);
            if (is_array($indexData)) {
                $grouped = [];
                foreach ($indexData as $i => $entry) {
                    $file = $entry['file'] ?? 'unknown';
                    if (!isset($grouped[$file])) {
                        $grouped[$file] = ['chunks' => [], 'total_size' => 0];
                    }
                    $grouped[$file]['chunks'][] = [
                        'index' => $i,
                        'chunk' => $entry['chunk'] ?? '',
                        'preview' => substr($entry['chunk'] ?? '', 0, 100) . '...',
                        'source' => $file
                    ];
                    $grouped[$file]['total_size'] += strlen($entry['chunk'] ?? '');
                }
                $knowledgeEntries = $grouped;
            }
        }
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
    if (isset($_POST['aiassistant_button_text'])) { $config->setChatButtonText(trim((string) $_POST['aiassistant_button_text'])); }
    if (isset($_POST['aiassistant_button_color'])) { $config->setChatButtonColor(trim((string) $_POST['aiassistant_button_color'])); }
    $config->save();

if (!empty($_FILES['logo']['name'] ?? '')) {
        $file = $_FILES['logo'];
        if (($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowedLogoExts, true)) {
                // Remove any existing logo files with different extensions
                foreach ($allowedLogoExts as $e) {
                    @unlink($storageDir . DIRECTORY_SEPARATOR . 'logo.' . $e);
                }
                $logoPath = $storageDir . DIRECTORY_SEPARATOR . 'logo.' . $ext;
                if (@move_uploaded_file($file['tmp_name'], $logoPath)) {
                    $message = 'Logo uploaded successfully.';
                    $logoExt = $ext;
                    $logoWebPath = '../aiassi/storage/logo.' . $ext;
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant Admin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .knowledge-chunk {
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            background-color: #f8f9fa;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        .accordion-button:not(.collapsed) {
            background-color: #e7f3ff;
            border-color: #b8daff;
        }
        .btn-group-vertical .btn {
            margin-bottom: 2px;
        }
        .btn-group-vertical .btn:last-child {
            margin-bottom: 0;
        }
        .accordion-item {
            border: 1px solid #dee2e6;
            margin-bottom: 0.5rem;
        }
        .knowledge-stats {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-robot text-primary me-2"></i>
                AI Assistant Administration
            </h1>
            <span class="badge bg-danger">
                <?php echo ucfirst($user['role']); ?> Access
            </span>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <form method="post" enctype="multipart/form-data">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-cog me-2"></i>
                                AI Provider Configuration
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">AI Provider</label>
                                <select name="provider" class="form-select">
                                    <option value="gemini" <?= $data['provider']==='gemini'?'selected':'' ?>>Google Gemini</option>
                                    <option value="openai" <?= $data['provider']==='openai'?'selected':'' ?>>OpenAI</option>
                                    <option value="deepseek" <?= $data['provider']==='deepseek'?'selected':'' ?>>DeepSeek</option>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted">OpenAI Configuration</h6>
                                    <div class="mb-3">
                                        <label class="form-label">API Key</label>
                                        <input type="password" name="openai_api_key" value="<?= htmlspecialchars($data['openai']['api_key'] ?? '') ?>" class="form-control" />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Model</label>
                                        <input type="text" name="model_openai" value="<?= htmlspecialchars($data['model']['openai'] ?? 'gpt-4o-mini') ?>" class="form-control" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Google Gemini Configuration</h6>
                                    <div class="mb-3">
                                        <label class="form-label">API Key</label>
                                        <input type="password" name="gemini_api_key" value="<?= htmlspecialchars($data['gemini']['api_key'] ?? '') ?>" class="form-control" />
                                        <div class="form-text">
                                            Get a key from <a href="https://aistudio.google.com/app/api-keys" target="_blank" rel="noreferrer">Google AI Studio</a>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Model</label>
                                        <input type="text" name="model_gemini" value="<?= htmlspecialchars($data['model']['gemini'] ?? 'gemini-2.5-flash') ?>" class="form-control" />
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted">DeepSeek Configuration</h6>
                                    <div class="mb-3">
                                        <label class="form-label">API Key</label>
                                        <input type="password" name="deepseek_api_key" value="<?= htmlspecialchars($data['deepseek']['api_key'] ?? '') ?>" class="form-control" />
                                        <div class="form-text">Paste the full, unmasked key</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Model</label>
                                        <input type="text" name="model_deepseek" value="<?= htmlspecialchars($data['model']['deepseek'] ?? 'deepseek-chat') ?>" class="form-control" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-palette me-2"></i>
                                AI Assistant Appearance
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Button Text</label>
                                        <input type="text" name="aiassistant_button_text" value="<?= htmlspecialchars($config->getChatButtonText()) ?>" class="form-control" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Button Color</label>
                                        <input type="color" name="aiassistant_button_color" value="<?= htmlspecialchars($config->getChatButtonColor()) ?>" class="form-control form-control-color" />
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
<label class="form-label">AI Assistant Logo</label>
                                <input type="file" name="logo" accept="image/*" class="form-control" />
                                <div class="form-text">Upload PNG, JPG, GIF, or WebP image to customize the AI assistant appearance.</div>
                                <?php if (!empty($logoWebPath)): ?>
                                    <div class="mt-2 d-flex align-items-center gap-2">
                                        <img src="<?= htmlspecialchars($logoWebPath) ?>" alt="Current Logo" style="height:48px;width:48px;object-fit:contain;border:1px solid #e9ecef;border-radius:8px;background:#fff;">
                                        <div class="form-check ms-2">
                                            <input class="form-check-input" type="checkbox" name="remove_logo" id="remove_logo" value="1">
                                            <label class="form-check-label" for="remove_logo">Remove current logo and use default</label>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="form-text text-muted">No custom logo set. Using default.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-database me-2"></i>
                                Knowledge Base Management
                            </h5>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addKnowledgeForm">
                                <i class="fas fa-plus me-1"></i>Add New Content
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Add new knowledge form (collapsed by default) -->
                            <div class="collapse mb-4" id="addKnowledgeForm">
                                <div class="border rounded p-3 bg-light">
                                    <h6 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Add New Knowledge</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Source Name</label>
                                        <input type="text" name="manual_source" placeholder="e.g., Alumni Portal Guide" class="form-control" />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Content</label>
                                        <textarea name="manual_text" rows="6" placeholder="Paste content here to add to the AI assistant's knowledge base..." class="form-control"></textarea>
                                        <div class="form-text">This content will be indexed and used by the AI to answer questions.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Current Knowledge Base Display -->
                            <div class="mb-3">
                                <h6 class="mb-3">
                                    <i class="fas fa-list me-2"></i>
                                    Current Knowledge Base 
                                    <span class="badge bg-info"><?php echo count($knowledgeEntries); ?> sources</span>
                                </h6>
                                
                                <?php if (empty($knowledgeEntries)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No knowledge base entries found. Add some content above to get started!
                                    </div>
                                <?php else: ?>
                                    <!-- Bulk Operations -->
                                    <div class="card mb-3 bg-light">
                                        <div class="card-body py-2">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-tasks me-2 text-primary"></i>
                                                        <strong>Bulk Operations:</strong>
                                                        <span class="ms-2 knowledge-stats">
                                                            Total: <?php 
                                                            $totalChunks = array_sum(array_map(function($data) { return count($data['chunks']); }, $knowledgeEntries));
                                                            echo $totalChunks; 
                                                            ?> chunks from <?php echo count($knowledgeEntries); ?> sources
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="btn-group w-100" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="expandAll()">
                                                            <i class="fas fa-expand-alt me-1"></i>Expand All
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="collapseAll()">
                                                            <i class="fas fa-compress-alt me-1"></i>Collapse All
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion" id="knowledgeAccordion">
                                        <?php foreach ($knowledgeEntries as $source => $data): ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading<?php echo md5($source); ?>">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                                        data-bs-target="#collapse<?php echo md5($source); ?>" aria-expanded="false">
                                                    <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($source); ?></strong>
                                                            <small class="text-muted ms-2">
                                                                <?php echo count($data['chunks']); ?> chunks, 
                                                                <?php echo number_format($data['total_size']); ?> characters
                                                            </small>
                                                        </div>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deleteSource('<?php echo htmlspecialchars($source); ?>')" 
                                                                    title="Delete all entries from this source">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo md5($source); ?>" class="accordion-collapse collapse" 
                                                 data-bs-parent="#knowledgeAccordion">
                                                <div class="accordion-body">
                                                    <?php foreach ($data['chunks'] as $chunk): ?>
                                                    <div class="card mb-2">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div class="flex-grow-1">
                                                                    <div class="text-muted small mb-2">Chunk #<?php echo $chunk['index']; ?></div>
                                                                    <div class="knowledge-chunk" style="max-height: 100px; overflow-y: auto;">
                                                                        <?php echo nl2br(htmlspecialchars(substr($chunk['chunk'], 0, 300))); ?>
                                                                        <?php if (strlen($chunk['chunk']) > 300): ?>
                                                                            <span class="text-muted">... [truncated]</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="text-muted small mt-2">
                                                                        <?php echo number_format(strlen($chunk['chunk'])); ?> characters
                                                                    </div>
                                                                </div>
                                                                <div class="ms-3">
                                                                    <div class="btn-group-vertical" role="group">
                                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                                onclick="viewFullChunk(<?php echo $chunk['index']; ?>)" 
                                                                                title="View full content">
                                                                            <i class="fas fa-eye"></i>
                                                                        </button>
                                                                        <button type="button" class="btn btn-sm btn-outline-success" 
                                                                                onclick="editChunk(<?php echo $chunk['index']; ?>)" 
                                                                                title="Edit this chunk">
                                                                            <i class="fas fa-edit"></i>
                                                                        </button>
                                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                                onclick="deleteChunk(<?php echo $chunk['index']; ?>)" 
                                                                                title="Delete this chunk">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Configuration
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tools me-2"></i>
                            Maintenance
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" onsubmit="return confirmRebuild()">
                            <div class="d-grid">
                                <input type="hidden" name="rebuild_index" value="1" />
                                <button type="submit" class="btn btn-warning" id="rebuildBtn">
                                    <i class="fas fa-sync-alt me-2"></i>
                                    Rebuild Knowledge Index
                                </button>
                            </div>
                            <div class="form-text mt-2">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                This will reset and rebuild the search index from all uploaded files in the uploads directory.
                            </div>
                        </form>
                        
                        <?php 
                        $uploadFiles = @scandir($uploadsDir) ?: [];
                        $fileCount = count(array_filter($uploadFiles, function($f) use ($uploadsDir) {
                            return $f !== '.' && $f !== '..' && is_file($uploadsDir . DIRECTORY_SEPARATOR . $f);
                        }));
                        ?>
                        <div class="mt-3 p-2 bg-light rounded">
                            <small class="text-muted">
                                <i class="fas fa-folder me-1"></i>
                                Upload directory: <code><?php echo htmlspecialchars(basename($uploadsDir)); ?></code>
                                <br>
                                <i class="fas fa-file me-1"></i>
                                Files available: <strong><?php echo $fileCount; ?></strong>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Quick Start
                        </h5>
                    </div>
                    <div class="card-body">
                        <ol class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-key text-primary me-2"></i>
                                <strong>1.</strong> Set up an AI provider API key
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-upload text-success me-2"></i>
                                <strong>2.</strong> Add content to the knowledge base
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-sync text-warning me-2"></i>
                                <strong>3.</strong> Rebuild the index
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-comments text-info me-2"></i>
                                <strong>4.</strong> Test the AI assistant!
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <!-- Knowledge Base Management Modals -->
    
    <!-- View Full Chunk Modal -->
    <div class="modal fade" id="viewChunkModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Knowledge Chunk Content</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="chunkContent" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; font-family: monospace; background: #f8f9fa; padding: 1rem; border-radius: 0.375rem;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Source Confirmation Modal -->
    <div class="modal fade" id="deleteSourceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Knowledge Source</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete all knowledge entries from the source:</p>
                    <p><strong id="sourceToDelete"></strong></p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>This action cannot be undone!</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteSourceForm">
                        <input type="hidden" name="delete_source" id="deleteSourceInput">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Source</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Chunk Modal -->
    <div class="modal fade" id="editChunkModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Knowledge Chunk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editChunkForm">
                    <div class="modal-body">
                        <input type="hidden" name="edit_chunk" value="1">
                        <input type="hidden" name="chunk_index" id="editChunkIndex">
                        
                        <div class="mb-3">
                            <label for="editChunkSource" class="form-label">Source Name</label>
                            <input type="text" class="form-control" name="chunk_source" id="editChunkSource" 
                                   placeholder="e.g., Alumni Portal Guide">
                        </div>
                        
                        <div class="mb-3">
                            <label for="editChunkContent" class="form-label">Content</label>
                            <textarea class="form-control" name="chunk_content" id="editChunkContent" 
                                      rows="12" style="font-family: 'Courier New', monospace;"></textarea>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Edit the content and it will be re-indexed automatically.
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="text-muted small">
                                    <i class="fas fa-chart-line me-1"></i>
                                    Character count: <span id="editCharCount">0</span>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="text-muted small">
                                    <i class="fas fa-database me-1"></i>
                                    Chunk #<span id="editChunkNumber">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Chunk Confirmation Modal -->
    <div class="modal fade" id="deleteChunkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Knowledge Chunk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this knowledge chunk?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>This action cannot be undone!</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteChunkForm">
                        <input type="hidden" name="delete_chunk" id="deleteChunkInput">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Chunk</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Knowledge base data for JavaScript access
        const knowledgeData = <?php echo json_encode($knowledgeEntries); ?>;
        
        // Get all chunks in a flat array for easy access
        const allChunks = [];
        Object.keys(knowledgeData).forEach(source => {
            knowledgeData[source].chunks.forEach(chunk => {
                allChunks[chunk.index] = chunk;
            });
        });

        function viewFullChunk(chunkIndex) {
            const chunk = allChunks[chunkIndex];
            if (chunk) {
                document.getElementById('chunkContent').textContent = chunk.chunk;
                const modal = new bootstrap.Modal(document.getElementById('viewChunkModal'));
                modal.show();
            }
        }

        function deleteSource(sourceName) {
            document.getElementById('sourceToDelete').textContent = sourceName;
            document.getElementById('deleteSourceInput').value = sourceName;
            const modal = new bootstrap.Modal(document.getElementById('deleteSourceModal'));
            modal.show();
        }

        function editChunk(chunkIndex) {
            const chunk = allChunks[chunkIndex];
            if (chunk) {
                document.getElementById('editChunkIndex').value = chunkIndex;
                document.getElementById('editChunkSource').value = chunk.source || '';
                document.getElementById('editChunkContent').value = chunk.chunk || '';
                document.getElementById('editChunkNumber').textContent = chunkIndex;
                updateCharCount();
                const modal = new bootstrap.Modal(document.getElementById('editChunkModal'));
                modal.show();
            }
        }

        function deleteChunk(chunkIndex) {
            document.getElementById('deleteChunkInput').value = chunkIndex;
            const modal = new bootstrap.Modal(document.getElementById('deleteChunkModal'));
            modal.show();
        }

        function updateCharCount() {
            const content = document.getElementById('editChunkContent').value;
            document.getElementById('editCharCount').textContent = content.length;
        }

        function confirmRebuild() {
            const confirmed = confirm('Are you sure you want to rebuild the knowledge index?\n\nThis will:\n- Delete all current knowledge base entries\n- Re-process all uploaded files\n- Re-create the search index\n\nThis action cannot be undone!');
            
            if (confirmed) {
                const btn = document.getElementById('rebuildBtn');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Rebuilding...';
                btn.disabled = true;
            }
            
            return confirmed;
        }

        // Prevent accordion button click when clicking action buttons
        document.querySelectorAll('.btn-group button').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });

        // Bulk operations functions
        function expandAll() {
            Object.keys(knowledgeData).forEach(source => {
                const collapseElement = document.getElementById('collapse' + md5(source));
                if (collapseElement && !collapseElement.classList.contains('show')) {
                    const collapse = new bootstrap.Collapse(collapseElement, { show: true });
                }
            });
        }

        function collapseAll() {
            Object.keys(knowledgeData).forEach(source => {
                const collapseElement = document.getElementById('collapse' + md5(source));
                if (collapseElement && collapseElement.classList.contains('show')) {
                    const collapse = new bootstrap.Collapse(collapseElement, { hide: true });
                }
            });
        }
        
        // Add event listener for character count update
        document.addEventListener('DOMContentLoaded', function() {
            const editContentTextarea = document.getElementById('editChunkContent');
            if (editContentTextarea) {
                editContentTextarea.addEventListener('input', updateCharCount);
            }
        });
        
        // Auto-expand accordion if there are few sources
        document.addEventListener('DOMContentLoaded', function() {
            const sourceCount = Object.keys(knowledgeData).length;
            if (sourceCount <= 3) {
                // Auto-expand all accordions if there are 3 or fewer sources
                Object.keys(knowledgeData).forEach(source => {
                    const collapseElement = document.getElementById('collapse' + md5(source));
                    if (collapseElement) {
                        const collapse = new bootstrap.Collapse(collapseElement, { show: true });
                    }
                });
            }
        });

        // Simple MD5 hash function for consistency with PHP
        function md5(str) {
            // This is a simple hash function for demo purposes
            // In production, you'd want to use the same hash as PHP
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32bit integer
            }
            return Math.abs(hash).toString(16);
        }
    </script>
</body>
</html>
