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
     * @param string $accessToken
     * @param string $path
     * @param resource|string $contents Stream resource or file contents as a string
     * @param bool $overwrite
     * @return array
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function upload(string $accessToken, string $path, $contents, bool $overwrite = true): array
    {
        // Step 1: get upload URL
        $res = Http::withHeaders($this->authHeaders($accessToken))
            ->get($this->apiBase.'/resources/upload', [
                'path' => $this->normalizePath($path),
                'overwrite' => $overwrite ? 'true' : 'false',
            ])->throw();
        $href = Arr::get($res->json(), 'href');

        // Step 2: upload via PUT to href
        Http::withBody($contents, 'application/octet-stream')
            ->put($href)
            ->throw();

        return ['success' => true, 'path' => $path];
    }
}
