<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandArticleController extends Controller
{
    public function index(Request $request, Brand $brand): JsonResponse
    {
        $articles = $brand->articles()->orderBy('name')->get(['id','name']);
        return response()->json(['data' => $articles]);
    }

    public function store(Request $request, Brand $brand): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
        ]);
        $article = $brand->articles()->firstOrCreate(['name' => $data['name']]);
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
                if ($res->wasRecentlyCreated) { $created++; } else { $skipped++; }
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
}
