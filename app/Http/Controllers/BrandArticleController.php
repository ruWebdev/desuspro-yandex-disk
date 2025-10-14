<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Brand;
use App\Models\TaskType;
use App\Models\YandexToken;
use App\Services\YandexDiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BrandArticleController extends Controller
{
    public function index(Request $request, Brand $brand): JsonResponse
    {
        $articles = $brand->articles()->orderBy('name')->get(['id','name']);
        // Determine if folders already exist per article for any enabled task type
        $types = TaskType::query()->where('create_empty_folder', true)->get(['id','name','prefix']);
        $token = YandexToken::orderByDesc('updated_at')->first();
        $disk = app(YandexDiskService::class);
        if ($token) {
            $token = $disk->ensureValidToken($token);
        }
        $brandName = $this->sanitizeName($brand->name);
        $data = $articles->map(function ($a) use ($types, $token, $disk, $brandName) {
            $has = false;
            if ($token && $types->isNotEmpty()) {
                foreach ($types as $type) {
                    $typeName = $this->sanitizeName($type->name);
                    $prefix = $type->prefix ?: mb_substr($typeName, 0, 1);
                    $articleName = $this->sanitizeName($a->name);
                    $leaf = '/' . $brandName . '/' . $typeName . '/' . $prefix . '_' . $articleName;
                    try {
                        $disk->getResource($token->access_token, $leaf, ['path']);
                        $has = true; // exists
                        break;
                    } catch (\Illuminate\Http\Client\RequestException $ex) {
                        $status = optional($ex->response)->status();
                        if ($status === 404) {
                            // not found, continue to next type
                        } else {
                            // other errors log and continue
                            Log::warning('Yandex getResource error during has_folder check', [
                                'status' => $status,
                                'leaf' => $leaf,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Yandex error during has_folder check', ['error' => $e->getMessage()]);
                    }
                }
            }
            return [
                'id' => $a->id,
                'name' => $a->name,
                'has_folder' => $has,
            ];
        });
        return response()->json(['data' => $data]);
    }

    /**
     * Create empty Yandex.Disk folders for the given brand/article for all task types
     * configured with create_empty_folder = true. Idempotent on Yandex side (ignores 409).
     */
    private function createEmptyFoldersForArticle(Brand $brand, Article $article): void
    {
        try {
            $types = TaskType::query()->where('create_empty_folder', true)->get(['id','name','prefix']);
            if ($types->isEmpty()) return;

            // Use latest shared token
            $token = YandexToken::orderByDesc('updated_at')->first();
            if (!$token) {
                Log::warning('Yandex token not found during empty folder creation', ['brand_id'=>$brand->id,'article_id'=>$article->id]);
                return;
            }
            $disk = app(YandexDiskService::class);
            $token = $disk->ensureValidToken($token);

            $brandName = $this->sanitizeName($brand->name);
            $articleName = $this->sanitizeName($article->name);

            foreach ($types as $type) {
                $typeName = $this->sanitizeName($type->name);
                $prefix = $type->prefix ?: mb_substr($typeName, 0, 1);
                $brandPath = '/' . $brandName;
                $typePath = $brandPath . '/' . $typeName;
                $leaf = $typePath . '/' . $prefix . '_' . $articleName;

                // Ensure parent folders, then create leaf folder (do not publish here)
                $this->ensureFolder($disk, $token->access_token, $brandPath);
                $this->ensureFolder($disk, $token->access_token, $typePath);
                $this->ensureFolder($disk, $token->access_token, $leaf);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to create empty Yandex.Disk folders for article', [
                'brand_id' => $brand->id,
                'article_id' => $article->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /** Sanitize names for Yandex paths: remove slashes and control characters. */
    private function sanitizeName(string $name): string
    {
        $name = preg_replace('/[\\\n\r\t]/u', ' ', $name);
        $name = str_replace('/', '-', $name);
        return trim($name);
    }

    /** Create folder if not exists, ignore 409 Conflict errors. */
    private function ensureFolder(YandexDiskService $disk, string $accessToken, string $path): void
    {
        try {
            $disk->createFolder($accessToken, $path);
        } catch (\Illuminate\Http\Client\RequestException $ex) {
            $status = optional($ex->response)->status();
            if ($status === 409) { return; }
            Log::error('Yandex.Disk createFolder failed (article flow)', [
                'path' => $path,
                'status' => $status,
                'body' => optional($ex->response)->body(),
            ]);
            throw $ex;
        }
    }

    public function store(Request $request, Brand $brand): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
        ]);
        $article = $brand->articles()->firstOrCreate(['name' => $data['name']]);
        if ($article->wasRecentlyCreated) {
            // Create empty folders on Yandex.Disk for each TaskType with flag enabled
            $this->createEmptyFoldersForArticle($brand, $article);
        }
        if ($request->expectsJson()) {
            return response()->json(['data' => $article], 201);
        }
        return back()->with('status', 'article-created');
    }

    public function bulkUpload(Request $request, Brand $brand): RedirectResponse|JsonResponse
    {
        $request->validate([
            'file' => ['required','file','mimetypes:text/plain','max:1024'],
        ]);
        $file = $request->file('file');
        $path = $file->store('tmp', 'local');
        $full = Storage::disk('local')->path($path);

        $created = 0;
        $skipped = 0;
        if (is_readable($full)) {
            $lines = file($full, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $name = trim($line);
                if ($name === '') { continue; }
                $res = $brand->articles()->firstOrCreate(['name' => $name]);
                if ($res->wasRecentlyCreated) {
                    $created++;
                    // Create empty folders only for newly created articles
                    $this->createEmptyFoldersForArticle($brand, $res);
                } else {
                    $skipped++;
                }
            }
        }
        Storage::disk('local')->delete($path);

        $payload = ['created' => $created, 'skipped' => $skipped];
        if ($request->expectsJson()) {
            return response()->json(['data' => $payload]);
        }
        return back()->with('status', 'articles-uploaded')->with('meta', $payload);
    }

    public function destroy(Request $request, Brand $brand, Article $article): RedirectResponse|JsonResponse
    {
        abort_unless($article->brand_id === $brand->id, 404);
        $article->delete();
        if ($request->expectsJson()) {
            return response()->json(['status' => 'deleted']);
        }
        return back()->with('status', 'article-deleted');
    }

    /**
     * Create empty folders for a specific article on demand.
     */
    public function createFolders(Request $request, Brand $brand, Article $article): JsonResponse
    {
        abort_unless($article->brand_id === $brand->id, 404);
        $this->createEmptyFoldersForArticle($brand, $article);
        return response()->json(['success' => true]);
    }
}
