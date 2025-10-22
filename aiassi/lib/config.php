<?php
declare(strict_types=1);

namespace Aiassi\Lib;

final class Config
{
    private string $configPath;
    /** @var array<string,mixed> */
    private array $data = [
        'provider' => 'gemini',
        'openai' => [ 'api_key' => '' ],
        'gemini' => [ 'api_key' => '' ],
        'deepseek' => [ 'api_key' => '' ],
        'model' => [ 'gemini' => 'gemini-2.5-flash', 'openai' => 'gpt-4o-mini', 'deepseek' => 'deepseek-chat' ],
        'rag' => [ 'chunk_size' => 1200, 'chunk_overlap' => 200, 'top_k' => 4 ],
        'ui' => [ 'chat_button_text' => 'Chat', 'chat_button_color' => '#2563eb' ],
    ];

    public function __construct(string $storageDir)
    {
        $this->configPath = rtrim($storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config.json';
        if (!is_dir($storageDir)) { @mkdir($storageDir, 0777, true); }
        $this->load();
    }

    private function load(): void
    {
        if (is_file($this->configPath)) {
            $json = (string) @file_get_contents($this->configPath);
            if ($json !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $this->data = array_replace_recursive($this->data, $decoded);
                }
            }
        }
    }

    public function save(): void
    {
        @file_put_contents($this->configPath, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string,mixed> */
    public function all(): array { return $this->data; }
    public function setProvider(string $p): void { if (in_array($p, ['gemini','openai','deepseek'], true)) $this->data['provider'] = $p; }
    public function setApiKey(string $p, string $k): void { if (in_array($p, ['gemini','openai','deepseek'], true)) $this->data[$p]['api_key'] = $k; }
    public function getProvider(): string { return (string) ($this->data['provider'] ?? 'gemini'); }
    public function getApiKey(string $p): string { return (string) ($this->data[$p]['api_key'] ?? ''); }
    public function getModel(string $p): string {
        return (string) ($this->data['model'][$p] ?? (
            $p==='gemini' ? 'gemini-2.5-flash' : ($p==='deepseek' ? 'deepseek-chat' : 'gpt-4o-mini')
        ));
    }
    public function setModel(string $p, string $model): void {
        if (!isset($this->data['model']) || !is_array($this->data['model'])) { $this->data['model'] = []; }
        if (in_array($p, ['gemini','openai','deepseek'], true) && $model !== '') {
            $this->data['model'][$p] = $model;
        }
    }
    public function setChatButtonText(string $text): void {
        if (!isset($this->data['ui']) || !is_array($this->data['ui'])) { $this->data['ui'] = []; }
        $this->data['ui']['chat_button_text'] = $text !== '' ? $text : 'Chat';
    }
    public function setChatButtonColor(string $color): void {
        if (!isset($this->data['ui']) || !is_array($this->data['ui'])) { $this->data['ui'] = []; }
        $this->data['ui']['chat_button_color'] = $color !== '' ? $color : '#2563eb';
    }
    public function getChatButtonText(): string {
        return (string) ($this->data['ui']['chat_button_text'] ?? 'Chat');
    }
    public function getChatButtonColor(): string {
        return (string) ($this->data['ui']['chat_button_color'] ?? '#2563eb');
    }
    public function getRagSetting(string $k, int $d): int { $v = $this->data['rag'][$k] ?? $d; return is_int($v)?$v:$d; }
}


