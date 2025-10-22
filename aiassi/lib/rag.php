<?php
declare(strict_types=1);

namespace Aiassi\Lib;

final class RagIndex
{
    private string $indexPath;
    /** @var list<array{file:string,chunk:string,vec:array<string,int>}> */
    private array $entries = [];

    public function __construct(string $storageDir)
    {
        $this->indexPath = rtrim($storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.json';
        $this->load();
    }

    private function load(): void
    {
        if (is_file($this->indexPath)) {
            $json = (string) @file_get_contents($this->indexPath);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) { $this->entries = $decoded; }
        }
    }

    public function save(): void
    {
        @file_put_contents($this->indexPath, json_encode($this->entries, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    public function reset(): void { $this->entries = []; $this->save(); }

    public function addFile(string $filePath, int $chunkSize, int $overlap): void
    {
        $text = Utils::readTextFromFile($filePath);
        if ($text === '') return;
        $chunks = Utils::splitIntoChunks($text, $chunkSize, $overlap);
        foreach ($chunks as $chunk) {
            $this->entries[] = [ 'file' => basename($filePath), 'chunk' => $chunk, 'vec' => Utils::vectorize($chunk) ];
        }
        $this->save();
    }

    public function addText(string $sourceName, string $text, int $chunkSize, int $overlap): void
    {
        $cleanName = $sourceName !== '' ? $sourceName : 'manual-input.txt';
        if (trim($text) === '') return;
        $chunks = Utils::splitIntoChunks($text, $chunkSize, $overlap);
        foreach ($chunks as $chunk) {
            $this->entries[] = [ 'file' => $cleanName, 'chunk' => $chunk, 'vec' => Utils::vectorize($chunk) ];
        }
        $this->save();
    }

    /** @return list<array{file:string,chunk:string,score:float}> */
    public function retrieve(string $query, int $topK = 4): array
    {
        $qv = Utils::vectorize($query);
        $scores = [];
        foreach ($this->entries as $i=>$e) { $scores[$i] = Utils::cosine($qv, $e['vec']); }
        arsort($scores, SORT_NUMERIC);
        $out = [];
        foreach (array_slice(array_keys($scores), 0, $topK) as $idx) {
            $e = $this->entries[$idx];
            $out[] = ['file'=>$e['file'],'chunk'=>$e['chunk'],'score'=>(float)$scores[$idx]];
        }
        return $out;
    }
}


