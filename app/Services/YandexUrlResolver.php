<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class YandexUrlResolver
{
    /**
     * Resolve a Yandex download URL to its final storage URL using Python script
     */
    public function resolve(string $url): string
    {
        // Path to the Python script
        $scriptPath = base_path('yim.py');
        
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('Python script not found at: ' . $scriptPath);
        }
        
        // Escape the URL for shell execution
        $escapedUrl = escapeshellarg($url);
        
        // Build and execute the command
        $command = "python3 " . $scriptPath . " " . $escapedUrl . " 2>&1";
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            Log::error('Python script failed', [
                'command' => $command,
                'return_var' => $returnVar,
                'output' => $output
            ]);
            throw new \RuntimeException('Failed to resolve Yandex URL');
        }
        
        // Extract the final URL from the output
        $output = implode("\n", $output);
        if (preg_match('/https?:\/\/[^\s\n"]+/', $output, $matches)) {
            return trim($matches[0]);
        }
        
        Log::error('Could not parse Python script output', ['output' => $output]);
        throw new \RuntimeException('Could not resolve Yandex URL: Invalid output format');
    }

    /**
     * Run yim.py passing a JSON with a single item and return first URL from stdout.
     * @param array $item The Yandex item structure as received from API (must include sizes/file etc)
     */
    public function resolveFromItem(array $item): string
    {
        $scriptPath = base_path('yim.py');
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('Python script not found at: ' . $scriptPath);
        }

        // Build JSON payload: {"items": [ item ]}
        $payload = json_encode(['items' => [$item]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new \RuntimeException('Failed to encode payload for Python');
        }

        $escaped = escapeshellarg($payload);
        $command = "python3 " . $scriptPath . " --input " . $escaped . " 2>&1";
        $output = [];
        $code = 0;
        exec($command, $output, $code);
        if ($code !== 0) {
            Log::error('yim.py failed', ['code' => $code, 'output' => $output]);
            throw new \RuntimeException('Resolver failed');
        }
        $joined = implode("\n", $output);

        // Try parse as JSON produced by yim.py
        try {
            $json = json_decode($joined, true, 512, JSON_THROW_ON_ERROR);
            // Expected shape:
            // { status, downloaded, total, files: [ { public_path, ... } ], errors }
            if (isset($json['files'][0]['public_path']) && is_string($json['files'][0]['public_path'])) {
                return $json['files'][0]['public_path'];
            }
            // If no files, try to surface error
            Log::warning('yim.py JSON has no files[0].public_path', ['json' => $json]);
        } catch (\Throwable $e) {
            Log::warning('Failed to decode yim.py JSON output, will try regex fallback', [
                'error' => $e->getMessage(),
                'output' => $joined,
            ]);
        }

        // Fallback to first URL present in output (older behavior)
        if (preg_match('/https?:\/\/[^\s\"\']+/', $joined, $m)) {
            return $m[0];
        }

        Log::error('Could not parse usable path from yim.py output', ['output' => $joined]);
        throw new \RuntimeException('No usable path found in yim.py output');
    }

    /**
     * Run yim.py for a previously saved JSON file.
     * @param string $inputPath Path relative to project base (e.g. 'storage/app/private/yandex/list/file.clean.json')
     * @param bool $useFileField Pass --use-file-field flag to the script
     * @return array Decoded JSON output from yim.py
     */
    public function resolveFromListFile(string $inputPath, bool $useFileField = false): array
    {
        $scriptPath = base_path('yim.py');
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('Python script not found at: ' . $scriptPath);
        }
        $full = base_path($inputPath);
        if (!file_exists($full)) {
            throw new \InvalidArgumentException('Input JSON file not found: ' . $inputPath);
        }

        $escaped = escapeshellarg($full);
        $cmd = "python3 " . $scriptPath . " --input " . $escaped;
        if ($useFileField) {
            $cmd .= " --use-file-field";
        }
        $cmd .= " 2>&1";

        $out = [];
        $code = 0;
        exec($cmd, $out, $code);
        if ($code !== 0) {
            Log::error('yim.py failed', ['code' => $code, 'output' => $out, 'cmd' => $cmd]);
            throw new \RuntimeException('yim.py failed');
        }
        $joined = implode("\n", $out);

        // Some runs may prepend progress text. Extract JSON object boundaries.
        $jsonText = $joined;
        $start = strpos($joined, '{');
        $end = strrpos($joined, '}');
        if ($start !== false && $end !== false && $end >= $start) {
            $jsonText = substr($joined, $start, $end - $start + 1);
        }

        $decoded = json_decode($jsonText, true);
        if (!is_array($decoded)) {
            Log::error('Failed to decode yim.py JSON output', ['output' => $joined]);
            throw new \RuntimeException('Invalid JSON from yim.py');
        }
        return $decoded;
    }
}
