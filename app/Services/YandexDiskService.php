<?php

namespace App\Services;

use App\Models\YandexToken;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class YandexDiskService
{
    private string $authUrl = 'https://oauth.yandex.com/authorize';
    private string $tokenUrl = 'https://oauth.yandex.com/token';
    private string $apiBase = 'https://cloud-api.yandex.net/v1/disk';

    /**
     * Resolve a Yandex URL to its final destination URL
     * First tries the Python script, falls back to PHP method if needed
     */
    public function resolveFinalUrl(string $url, int $timeout = 15): string
    {
        try {
            // First try using the Python script for more reliable resolution
            return app(YandexUrlResolver::class)->resolve($url);
        } catch (\Exception $e) {
            // Fallback to PHP method if Python fails
            return $this->resolveWithPhp($url, $timeout);
        }
    }

    /**
     * Resolve URL using PHP's cURL as fallback
     */
    protected function resolveWithPhp(string $url, int $timeout): string
    {
        // Normalize URL by removing escaping slashes
        $clean = str_replace('\\/', '/', $url);
        
        // Use cURL to get the final URL after redirects
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $clean);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        // Set a UA to avoid being blocked
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; YD-App/1.0)');
        curl_exec($ch);
        $effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return $effective ?: $clean;
    }

    public function getAuthorizeUrl(string $state): string
    {
        $clientId = Config::get('services.yandex.client_id');
        $scope = Config::get('services.yandex.scope');
        $redirect = Config::get('services.yandex.redirect');

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'scope' => $scope,
            'force_confirm' => 'yes',
            'state' => $state,
        ]);

        return $this->authUrl.'?'.$query;
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => Config::get('services.yandex.client_id'),
            'client_secret' => Config::get('services.yandex.client_secret'),
        ])->throw();

        return $response->json();
    }

    public function refreshAccessToken(YandexToken $token): YandexToken
    {
        if (!$token->refresh_token) {
            return $token; // Nothing to refresh
        }

        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->refresh_token,
            'client_id' => Config::get('services.yandex.client_id'),
            'client_secret' => Config::get('services.yandex.client_secret'),
        ])->throw();

        $data = $response->json();
        $expiresIn = Arr::get($data, 'expires_in');
        $token->access_token = Arr::get($data, 'access_token', $token->access_token);
        $token->token_type = Arr::get($data, 'token_type', $token->token_type);
        if ($expiresIn) {
            $token->expires_at = Carbon::now()->addSeconds((int) $expiresIn);
        }
        $token->save();

        return $token;
    }

    public function ensureValidToken(YandexToken $token): YandexToken
    {
        if ($token->isExpired()) {
            return $this->refreshAccessToken($token);
        }
        return $token;
    }

    private function authHeaders(string $accessToken): array
    {
        return [
            'Authorization' => 'OAuth '.$accessToken,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Ensure a Yandex Disk URL is embeddable (inline) in <img> by replacing disposition=attachment with inline.
     */
    public function normalizeEmbeddableUrl(?string $url): ?string
    {
        if (!$url) return $url;
        return str_contains($url, 'disposition=attachment')
            ? str_replace('disposition=attachment', 'disposition=inline', $url)
            : $url;
    }

    /**
     * Normalize path to Yandex format: 'disk:/...'.
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return 'disk:/';
        }
        if (str_starts_with($path, 'disk:')) {
            return $path;
        }
        $path = ltrim($path, '/');
        return 'disk:/'.$path;
    }

    /**
     * Get parent path of a Yandex Disk path (expects normalized or raw path).
     */
    private function parentPath(string $path): string
    {
        $norm = $this->normalizePath($path);
        $trim = rtrim(substr($norm, 5), '/'); // remove 'disk:/' prefix
        $parent = dirname($trim);
        return $parent === '.' ? 'disk:/' : 'disk:/'.ltrim($parent, '/');
    }

    /**
     * Get basename (filename) from Yandex Disk path.
     */
    private function baseName(string $path): string
    {
        $norm = $this->normalizePath($path);
        $trim = rtrim(substr($norm, 5), '/');
        return basename($trim);
    }

    public function diskInfo(string $accessToken): array
    {
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->get($this->apiBase)
            ->throw();
        return $res->json();
    }

    public function listResources(string $accessToken, string $path = '/', int $limit = 20): array
    {
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->get($this->apiBase.'/resources', [
                'path' => $this->normalizePath($path),
                'limit' => $limit,
            ])->throw();
        return $res->json();
    }

    public function createFolder(string $accessToken, string $path): array
    {
        // Yandex Disk expects 'path' as a query parameter for PUT
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->send('PUT', $this->apiBase.'/resources', [
                'query' => ['path' => $this->normalizePath($path)],
            ])->throw();
        return $res->json();
    }

    /**
     * Publish resource to make it publicly accessible (read-only link).
     */
    public function publish(string $accessToken, string $path): array
    {
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->send('PUT', $this->apiBase.'/resources/publish', [
                'query' => ['path' => $this->normalizePath($path)],
            ])->throw();
        return $res->json();
    }

    /**
     * Get resource metadata. Optionally restrict returned fields (e.g. 'public_url').
     */
    public function getResource(string $accessToken, string $path, array $fields = []): array
    {
        $query = ['path' => $this->normalizePath($path)];
        if (!empty($fields)) {
            $query['fields'] = implode(',', $fields);
        }
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->get($this->apiBase.'/resources', $query)
            ->throw();
        return $res->json();
    }

    /**
     * Create folder, publish it, and return public_url in response.
     */
    public function createFolderPublic(string $accessToken, string $path): array
    {
        // Create (ignore 409 Conflict if already exists)
        try {
            $this->createFolder($accessToken, $path);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $code = optional($e->response)->status();
            if ($code !== 409) { // 409 = already exists
                throw $e;
            }
        }
        // Publish (ignore 409 if already published)
        try {
            $this->publish($accessToken, $path);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $code = optional($e->response)->status();
            if ($code !== 409) {
                throw $e;
            }
        }
        // Fetch meta with public_url
        $meta = $this->getResource($accessToken, $path, ['public_url', 'path', 'name']);
        return [
            'success' => true,
            'path' => Arr::get($meta, 'path', $path),
            'name' => Arr::get($meta, 'name'),
            'public_url' => Arr::get($meta, 'public_url'),
        ];
    }

    public function deleteResource(string $accessToken, string $path, bool $permanently = false): array
    {
        // Yandex Disk expects query parameters on DELETE
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->withOptions(['query' => [
                'path' => $this->normalizePath($path),
                'permanently' => $permanently ? 'true' : 'false',
            ]])
            ->delete($this->apiBase.'/resources')
            ->throw();

        if ($res->status() === 202 || $res->status() === 204) {
            return ['success' => true];
        }

        return $res->json();
    }

    public function downloadUrl(string $accessToken, string $path): string
    {
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->get($this->apiBase.'/resources/download', [
                'path' => $this->normalizePath($path),
            ])->throw();
        return Arr::get($res->json(), 'href');
    }

    /**
     * Try to get resource public URL, publish if missing, and return it.
     */
    public function getOrPublishPublicUrl(string $accessToken, string $path): ?string
    {
        // Try fetch meta with public_url
        $meta = $this->getResource($accessToken, $path, ['public_url']);
        $publicUrl = Arr::get($meta, 'public_url');
        if ($publicUrl) return $publicUrl;

        // Publish (ignore 409 if already public)
        try {
            $this->publish($accessToken, $path);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $code = optional($e->response)->status();
            if ($code !== 409) {
                throw $e;
            }
        }

        // Re-fetch
        $meta = $this->getResource($accessToken, $path, ['public_url']);
        return Arr::get($meta, 'public_url');
    }

    /**
     * Get a direct public download URL for a file using its public key (URL).
     * Ensures the resource is published first.
     */
    public function publicDownloadUrl(string $accessToken, string $path): string
    {
        $publicKey = $this->getOrPublishPublicUrl($accessToken, $path);
        if (!$publicKey) {
            throw new \RuntimeException('Failed to obtain public URL for resource.');
        }
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->get($this->apiBase.'/public/resources/download', [
                'public_key' => $publicKey,
                // If public key is a folder link, Yandex requires the relative path within that public resource.
                // Our $path points to concrete file; since we publish that file directly, the key should be for the file, so no 'path' needed.
            ])->throw();
        $href = Arr::get($res->json(), 'href');
        return $this->normalizeEmbeddableUrl($href);
    }

    /**
     * Get a direct, embeddable link to a public file using the 'file' field of public resource meta.
     * Some Yandex download links enforce attachment; the 'file' href is suitable for <img> tags.
     */
    public function publicFileUrl(string $accessToken, string $path): string
    {
        $publicKey = $this->getOrPublishPublicUrl($accessToken, $path);
        if (!$publicKey) {
            throw new \RuntimeException('Failed to obtain public URL for resource.');
        }
        // Try to get direct file URL and/or preview for images
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->get($this->apiBase.'/public/resources', [
                'public_key' => $publicKey,
                'fields' => 'file,name,media_type,preview,sizes',
                'preview_size' => 'XL', // request preview link if media_type=image
            ])->throw();
        $json = $res->json();
        // Prefer sizes urls when available (ORIGINAL > XL > L)
        $sizes = Arr::get($json, 'sizes', []);
        if (is_array($sizes) && !empty($sizes)) {
            $byName = collect($sizes)->keyBy(function ($item) { return Arr::get($item, 'name'); });
            foreach (['ORIGINAL', 'XXXL', 'XXL', 'XL', 'L', 'M'] as $pref) {
                $u = Arr::get($byName->get($pref, []), 'url');
                if ($u) return $this->normalizeEmbeddableUrl($u);
            }
            // If none of preferred names, take first url
            $first = Arr::get($sizes, '0.url');
            if ($first) return $this->normalizeEmbeddableUrl($first);
        }
        $fileUrl = Arr::get($json, 'file');
        $previewUrl = Arr::get($json, 'preview');
        if ($fileUrl) return $this->normalizeEmbeddableUrl($fileUrl);
        if ($previewUrl) return $this->normalizeEmbeddableUrl($previewUrl);

        // Fallback: maybe only parent folder is public; try using parent's public_key + relative path
        $parent = $this->parentPath($path);
        $parentPublicKey = $this->getOrPublishPublicUrl($accessToken, $parent);
        if ($parentPublicKey) {
            $relativePath = Str::of($this->normalizePath($path))
                ->after($this->normalizePath($parent))
                ->trim('/');

            // Construct a direct download link using the parent's public key and the relative path.
            // This avoids making another API call which might be failing.
            $res = Http::get('https://cloud-api.yandex.net/v1/disk/public/resources/download', [
                'public_key' => $parentPublicKey,
                'path' => '/' . (string)$relativePath,
            ]);

            if ($res->successful()) {
                $href = Arr::get($res->json(), 'href');
                if ($href) {
                    return $this->normalizeEmbeddableUrl($href);
                }
            }
        }
        // Fallback to public download URL
        return $this->publicDownloadUrl($accessToken, $path);
    }

    /**
     * @param string $accessToken
     * @param string $path
     * @param resource|string $contents Stream resource or file contents as a string
     * @param bool $overwrite
     * @return array
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function upload(string $accessToken, string $path, $contents, bool $overwrite = false): array
    {
        $params = ['path' => $path, 'overwrite' => $overwrite ? 'true' : 'false'];
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->get($this->apiBase.'/resources/upload', $params)
            ->throw();
        $href = Arr::get($res->json(), 'href');
        if (!$href) throw new \RuntimeException('No upload URL provided');
        $put = Http::attach('file', $contents, basename($path))->put($href);
        return ['success' => $put->successful()];
    }

    /**
     * Move or rename a resource on Yandex.Disk.
     * Note: Yandex API requires parameters in query string, not body.
     */
    public function moveResource(string $accessToken, string $from, string $to, bool $overwrite = false): array
    {
        $query = http_build_query([
            'from' => $this->normalizePath($from),
            'path' => $this->normalizePath($to),
            'overwrite' => $overwrite ? 'true' : 'false',
        ]);
        
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->post($this->apiBase.'/resources/move?'.$query)
            ->throw();

        // 201 Created or 202 Accepted are success for async moves
        if (in_array($res->status(), [200, 201, 202])) {
            return ['success' => true];
        }
        return $res->json();
    }
}
