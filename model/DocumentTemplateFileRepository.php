<?php

class DocumentTemplateFileRepository
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $base = $dir ?: (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'document_templates');
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        $this->dir = $base;
    }

    private function pathFor(string $key): string
    {
        $safe = preg_replace('~[^a-z0-9_\-]~i', '_', $key);
        return rtrim($this->dir, "\\/") . DIRECTORY_SEPARATOR . $safe . '.json';
    }

    public function listAll(): array
    {
        $out = [];
        foreach (glob(rtrim($this->dir, "\\/") . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            $json = json_decode(@file_get_contents($file) ?: 'null', true);
            if (is_array($json)) {
                $out[] = [
                    'key' => (string)($json['key'] ?? basename($file, '.json')),
                    'name' => (string)($json['name'] ?? basename($file, '.json')),
                    'config' => $json['config'] ?? [],
                    'file' => $file,
                ];
            }
        }
        return $out;
    }

    public function getByKey(string $key): ?array
    {
        $file = $this->pathFor($key);
        if (!is_file($file)) return null;
        $json = json_decode(@file_get_contents($file) ?: 'null', true);
        if (!is_array($json)) return null;
        return [
            'key' => (string)($json['key'] ?? $key),
            'name' => (string)($json['name'] ?? $key),
            'config' => $json['config'] ?? [],
            'file' => $file,
        ];
    }

    public function upsert(string $key, string $name, array $config): bool
    {
        $file = $this->pathFor($key);
        $payload = ['key' => $key, 'name' => $name, 'config' => $config];
        return (bool)@file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function delete(string $key): bool
    {
        $file = $this->pathFor($key);
        return is_file($file) ? @unlink($file) : true;
    }

    public function getDir(): string
    {
        return $this->dir;
    }
}
