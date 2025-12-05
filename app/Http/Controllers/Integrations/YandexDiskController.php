<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\YandexToken;
use App\Services\YandexDiskService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class YandexDiskController extends Controller
{
    public function __construct(private readonly YandexDiskService $disk)
    {
        $this->middleware(['auth']);
    }

    /**
     * Process a previously saved list file: clean to items[] structure and run yim.py --use-file-field.
     * Request: { file: string } where file is a filename under storage/app/private/yandex/list.
     * Response: JSON produced by yim.py (status, files[], errors...).
     */
    public function processList(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'string'], // e.g. 20250829_122712_abcdef.json
        ]);

        $relative = ltrim($data['file'], '/');
        $baseDir = 'private/yandex/list';
        $fullPath = $baseDir . '/' . basename($relative);

        if (!Storage::disk('local')->exists($fullPath)) {
            abort(404, 'List file not found');
        }

        // Load and clean to { items: [ ...files only... ] }
        $raw = json_decode(Storage::disk('local')->get($fullPath), true);
        if (!is_array($raw)) {
            abort(400, 'Invalid JSON');
        }
        $embedded = data_get($raw, 'response._embedded.items', []);
        if (!is_array($embedded)) $embedded = [];
        $files = array_values(array_filter($embedded, fn($it) => is_array($it) && ($it['type'] ?? null) === 'file'));

        $clean = ['items' => $files];

        // Save cleaned file next to original for traceability
        $cleanName = preg_replace('/\.json$/', '', basename($fullPath)) . '.clean.json';
        $cleanPath = $baseDir . '/' . $cleanName;
        Storage::disk('local')->put($cleanPath, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Run Python resolver with the cleaned file path under project root
        $resolver = app(\App\Services\YandexUrlResolver::class);
        $output = $resolver->resolveFromListFile('storage/app/' . $cleanPath, true);

        return response()->json($output);
    }

    public function connect(Request $request)
    {
        $state = Str::random(32);
        $request->session()->put('yandex_oauth_state', $state);
        $url = $this->disk->getAuthorizeUrl($state);

        // Diagnostic logging
        Log::info('Yandex OAuth connect init', [
            'saved_state' => $state,
            'session_id' => $request->session()->getId(),
            'redirect_uri' => Config::get('services.yandex.redirect'),
            'app_url' => Config::get('app.url'),
            'user_id' => optional($request->user())->id,
        ]);
        return redirect()->away($url);
    }

    public function callback(Request $request)
    {
        $state = $request->string('state');
        $expected = $request->session()->get('yandex_oauth_state'); // Не используем pull, чтобы не удалять сразу

        // Логируем до проверки состояния
        $logContext = [
            'received_state' => (string) $state,
            'expected_state' => (string) $expected,
            'session_id' => $request->session()->getId(),
            'session_data' => $request->session()->all(),
            'cookies' => $request->cookies->all(),
            'full_url' => $request->fullUrl(),
            'host' => $request->getHost(),
            'scheme' => $request->getScheme(),
            'session_domain' => config('session.domain'),
            'session_same_site' => config('session.same_site'),
            'app_url' => config('app.url'),
        ];

        Log::info('Yandex OAuth callback - State validation', $logContext);

        // Проверяем состояние
        if (empty($state) || empty($expected) || !hash_equals((string)$expected, (string)$state)) {
            Log::error('Invalid OAuth state', $logContext);
            // Очищаем состояние из сессии, чтобы избежать повторного использования
            $request->session()->forget('yandex_oauth_state');
            return redirect()->route('dashboard')
                ->with('error', 'Неверное состояние OAuth. Пожалуйста, попробуйте снова.');
        }

        // Если состояние верное, удаляем его из сессии
        $request->session()->forget('yandex_oauth_state');

        $code = $request->string('code');
        if (!$code) {
            return redirect()->route('dashboard')->with('status', 'Yandex authorization failed: no code');
        }

        $data = $this->disk->exchangeCodeForToken($code);
        $expiresIn = Arr::get($data, 'expires_in');

        YandexToken::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'access_token' => Arr::get($data, 'access_token'),
                'refresh_token' => Arr::get($data, 'refresh_token'),
                'token_type' => Arr::get($data, 'token_type', 'bearer'),
                'scope' => Arr::get($data, 'scope'),
                'expires_at' => $expiresIn ? Carbon::now()->addSeconds((int) $expiresIn) : null,
            ]
        );

        return redirect()->route('dashboard')->with('status', 'Yandex connected');
    }

    public function status(Request $request)
    {
        // Use shared token (latest available), regardless of user
        $token = YandexToken::orderByDesc('updated_at')->first();
        if (!$token) {
            return response()->json(['connected' => false]);
        }
        $token = $this->disk->ensureValidToken($token);

        return response()->json([
            'connected' => true,
            'expires_at' => $token->expires_at,
        ]);
    }

    public function diskInfo(Request $request)
    {
        $token = $this->requireToken($request);
        $token = $this->disk->ensureValidToken($token);
        return response()->json($this->disk->diskInfo($token->access_token));
    }

    public function list(Request $request)
    {
        $token = $this->requireToken($request);
        $token = $this->disk->ensureValidToken($token);
        $path = $request->query('path', '/');
        $limit = (int) $request->query('limit', 20);
        $data = $this->disk->listResources($token->access_token, $path, $limit);

        // Persist list request/response for later analysis
        try {
            $payload = [
                'requested_at' => Carbon::now()->toIso8601String(),
                'user_id' => optional($request->user())->id,
                'query' => [
                    'path' => $path,
                    'limit' => $limit,
                ],
                'response' => $data,
            ];
            $dir = 'private/yandex/list';
            $filename = sprintf('%s/%s_%s.json', $dir, Carbon::now()->format('Ymd_His'), md5((string) $path));
            Storage::disk('local')->put($filename, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            Log::warning('Failed to persist Yandex list payload', ['error' => $e->getMessage()]);
        }

        return response()->json($data);
    }

    public function createFolder(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $token = $this->requireToken($request);
        $token = $this->disk->ensureValidToken($token);
        // Create folder and immediately publish it to get read-only public URL
        return response()->json(
            $this->disk->createFolderPublic($token->access_token, (string) $request->string('path'))
        );
    }

    public function delete(Request $request)
    {
        $request->validate(['path' => 'required|string', 'permanently' => 'sometimes|boolean']);
        $token = $this->requireToken($request);
        $token = $this->disk->ensureValidToken($token);
        return response()->json($this->disk->deleteResource($token->access_token, $request->string('path'), (bool) $request->boolean('permanently')));
    }

    public function downloadUrl(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $token = $this->requireToken($request);
        $token = $this->disk->ensureValidToken($token);
        // Return a public embeddable link (suitable for <img>), publishing if needed
        $href = $this->disk->publicFileUrl($token->access_token, (string) $request->string('path'));
        return response()->json(['href' => $href]);
    }

    /**
     * Resolve a Yandex downloader URL to the final storage URL (follow redirects).
     */
    public function resolveUrl(Request $request)
    {
        $request->validate(['url' => 'required|string']);
        $final = $this->disk->resolveFinalUrl((string) $request->string('url'));
        return response()->json(['href' => $final]);
    }

    /**
     * Resolve from a single Yandex item payload using Python script (yim.py --input ...)
     */
    public function resolveFromItem(Request $request)
    {
        $data = $request->validate(['item' => 'required|array']);
        $item = $data['item'];
        $resolver = app(\App\Services\YandexUrlResolver::class);
        $href = $resolver->resolveFromItem($item);
        return response()->json(['href' => $href]);
    }

    public function publishFolder(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $token = $this->requireToken($request);
        $token = $this->disk->ensureValidToken($token);

        $result = $this->disk->createFolderPublic($token->access_token, $request->string('path'));
        return response()->json($result);
    }

    public function upload(Request $request)
    {
        $request->validate(['path' => 'required|string', 'file' => 'required|file']);
        $token = $this->requireToken($request);
        $token = $this->disk->ensureValidToken($token);

        $file = $request->file('file');
        $resource = fopen($file->getRealPath(), 'r');

        $path = (string) $request->string('path');
        try {
            try {
                // First try without overwrite to avoid accidental clobber
                $result = $this->disk->upload($token->access_token, $path, $resource, false);
            } catch (\Illuminate\Http\Client\RequestException $e) {
                // If resource exists already, retry with overwrite=true for idempotency
                if (optional($e->response)->status() === 409) {
                    // Rewind stream for second attempt
                    if (is_resource($resource)) {
                        @rewind($resource);
                    }
                    $result = $this->disk->upload($token->access_token, $path, $resource, true);
                } else {
                    throw $e;
                }
            }
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        // Immediately publish the uploaded file so it is viewable without OAuth
        $publicUrl = $this->disk->getOrPublishPublicUrl($token->access_token, $path);
        // Also provide a direct embeddable URL for images
        $fileUrl = null;
        try {
            $fileUrl = $this->disk->publicFileUrl($token->access_token, $path);
        } catch (\Throwable $e) {
            // Fallback: keep null; client may still use download url endpoint
        }

        return response()->json(array_merge($result, [
            'public_url' => $publicUrl,
            'file_url' => $fileUrl,
        ]));
    }

    public function move(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
            'overwrite' => ['sometimes', 'boolean'],
        ]);
        $token = $this->requireToken($request);
        $token = $this->disk->ensureValidToken($token);
        $res = $this->disk->moveResource(
            $token->access_token,
            (string) $request->string('from'),
            (string) $request->string('to'),
            (bool) $request->boolean('overwrite', false)
        );
        return response()->json($res);
    }

    /**
     * Download a Yandex public URL (or already resolved direct URL) to a temporary local file
     * and return a public URL under storage (e.g. /storage/tmp/yandex/...).
     * Request JSON:
     *   - public_url?: string  (e.g. https://yadi.sk/i/... or any public resource URL)
     *   - direct_url?: string  (e.g. https://downloader.disk.yandex.ru/disk/...)
     * Response JSON: { url: string, id: string, path: string, mime: string }
     */
    public function downloadPublicToTemp(Request $request)
    {
        $data = $request->validate([
            'public_url' => ['sometimes', 'string'],
            'direct_url' => ['sometimes', 'string'],
        ]);

        if (empty($data['public_url']) && empty($data['direct_url'])) {
            abort(422, 'Either public_url or direct_url must be provided');
        }

        // Determine source URL to download
        $sourceUrl = $data['direct_url'] ?? null;
        if (!$sourceUrl && !empty($data['public_url'])) {
            // Use Yandex public resources API to get a direct href
            $publicKey = (string) $data['public_url'];
            $apiUrl = 'https://cloud-api.yandex.net/v1/disk/public/resources/download?public_key=' . urlencode($publicKey);
            $apiJson = @file_get_contents($apiUrl);
            if ($apiJson === false) {
                abort(502, 'Failed to contact Yandex public API');
            }
            $api = json_decode($apiJson, true);
            $href = $api['href'] ?? null;
            if (!$href) {
                abort(502, 'Failed to obtain direct download link from Yandex');
            }
            $sourceUrl = $href;
        }

        // Fetch headers to determine mime and a safe extension
        $headers = @get_headers($sourceUrl, 1);
        if ($headers === false || !isset($headers['Content-Type'])) {
            abort(502, 'Failed to determine MIME type');
        }
        $mime = is_array($headers['Content-Type']) ? end($headers['Content-Type']) : $headers['Content-Type'];
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/bmp'  => 'bmp',
            'image/svg+xml' => 'svg',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
        ];

        $ext = $mimeMap[$mime] ?? null;
        if (!$ext) {
            // Try to infer from URL query or content-disposition
            $ext = 'bin';
        }

        // Download file contents
        $content = @file_get_contents($sourceUrl);
        if ($content === false) {
            abort(502, 'Failed to download file');
        }

        // Save under public disk for temporary serving
        $dir = 'tmp/yandex';
        $name = 'yd_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
        $path = $dir . '/' . $name;
        Storage::disk('public')->put($path, $content);

        // Build public URL manually to avoid static analysis warning on Storage::url
        $url = asset('storage/' . ltrim($path, '/')); // typically /storage/...

        return response()->json([
            'url' => $url,
            'id' => $name,
            'path' => $path,
            'mime' => $mime,
        ]);
    }

    /**
     * Delete a previously created temporary file by relative path or id (filename).
     * Accepts either { path } or { id } referring to storage/app/public/tmp/yandex.
     */
    public function deleteTemp(Request $request)
    {
        $data = $request->validate([
            'path' => ['sometimes', 'string'],
            'id' => ['sometimes', 'string'],
        ]);

        if (empty($data['path']) && empty($data['id'])) {
            abort(422, 'Provide path or id');
        }
        $rel = $data['path'] ?? ('tmp/yandex/' . basename($data['id']));
        // Only allow deletion inside tmp/yandex
        if (!str_starts_with($rel, 'tmp/yandex/')) {
            abort(403, 'Invalid path');
        }
        $ok = Storage::disk('public')->delete($rel);
        return response()->json(['deleted' => $ok]);
    }

    /**
     * Replace an existing file on Yandex Disk with a new file.
     * The new file must have the same name as the existing file.
     * Request: { path: string (full path to existing file), file: UploadedFile }
     */
    public function replaceFile(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'file' => 'required|file',
        ]);

        // Check if user has can_edit_result permission
        $user = $request->user();
        if (!$user || !$user->can_edit_result) {
            abort(403, 'У вас нет прав на замену файлов результата');
        }

        $token = $this->requireToken($request);
        $token = $this->disk->ensureValidToken($token);

        $file = $request->file('file');
        $path = (string) $request->string('path');

        // Verify the filename matches
        $existingFilename = basename($path);
        $uploadedFilename = $file->getClientOriginalName();

        if ($existingFilename !== $uploadedFilename) {
            abort(422, "Имя файла должно совпадать: ожидается '{$existingFilename}', получено '{$uploadedFilename}'");
        }

        $resource = fopen($file->getRealPath(), 'r');

        try {
            // Upload with overwrite=true to replace the file
            $result = $this->disk->upload($token->access_token, $path, $resource, true);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        // Re-publish the file to ensure it's accessible
        $publicUrl = $this->disk->getOrPublishPublicUrl($token->access_token, $path);
        $fileUrl = null;
        try {
            $fileUrl = $this->disk->publicFileUrl($token->access_token, $path);
        } catch (\Throwable $e) {
            // Fallback: keep null
        }

        return response()->json(array_merge($result, [
            'public_url' => $publicUrl,
            'file_url' => $fileUrl,
            'message' => 'Файл успешно заменён',
        ]));
    }

    /**
     * Move files to an 'old' archive folder within the same parent directory.
     * Request: { paths: string[] (array of full paths to files to archive) }
     */
    public function archiveFiles(Request $request)
    {
        $data = $request->validate([
            'paths' => 'required|array|min:1',
            'paths.*' => 'required|string',
        ]);

        // Check if user has can_edit_result permission
        $user = $request->user();
        if (!$user || !$user->can_edit_result) {
            abort(403, 'У вас нет прав на архивирование файлов результата');
        }

        $token = $this->requireToken($request);
        $token = $this->disk->ensureValidToken($token);

        $archived = [];
        $errors = [];

        foreach ($data['paths'] as $path) {
            try {
                $parentDir = dirname($path);
                $filename = basename($path);
                $archiveDir = $parentDir . '/old';

                // Ensure 'old' folder exists
                try {
                    $this->disk->createFolder($token->access_token, $archiveDir);
                } catch (\Illuminate\Http\Client\RequestException $ex) {
                    // Ignore 409 (already exists)
                    if (optional($ex->response)->status() !== 409) {
                        throw $ex;
                    }
                }

                // Move file to archive folder
                $newPath = $archiveDir . '/' . $filename;
                try {
                    $this->disk->moveResource($token->access_token, $path, $newPath, false);
                    $archived[] = ['from' => $path, 'to' => $newPath];
                } catch (\Illuminate\Http\Client\RequestException $ex) {
                    $status = optional($ex->response)->status();
                    $body = optional($ex->response)->body();

                    // Treat 409 Conflict on move as "already archived" to make operation idempotent
                    if ($status === 409) {
                        $archived[] = [
                            'from' => $path,
                            'to' => $newPath,
                            'note' => 'already exists in old',
                        ];
                    } else {
                        $errors[] = [
                            'path' => $path,
                            'error' => $ex->getMessage(),
                            'status' => $status,
                            'body' => Str::limit($body, 500),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'archived' => $archived,
            'errors' => $errors,
            'message' => count($archived) > 0 ? 'Файлы перемещены в архив' : 'Не удалось переместить файлы',
        ]);
    }

    private function requireToken(Request $request): YandexToken
    {
        // Fetch a shared/global token (most recently updated)
        $token = YandexToken::orderByDesc('updated_at')->first();
        abort_if(!$token, 400, 'Yandex not connected');
        return $token;
    }
}
