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
use Illuminate\Support\Str;

class YandexDiskController extends Controller
{
    public function __construct(private readonly YandexDiskService $disk)
    {
        $this->middleware(['auth']);
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
        return response()->json($this->disk->listResources($token->access_token, $path, $limit));
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
        return response()->json(['href' => $this->disk->downloadUrl($token->access_token, $request->string('path'))]);
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

        try {
            $result = $this->disk->upload($token->access_token, $request->string('path'), $resource);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        return response()->json($result);
    }

    public function move(Request $request)
    {
        $data = $request->validate([
            'from' => ['required','string'],
            'to' => ['required','string'],
            'overwrite' => ['sometimes','boolean'],
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

    private function requireToken(Request $request): YandexToken
    {
        // Fetch a shared/global token (most recently updated)
        $token = YandexToken::orderByDesc('updated_at')->first();
        abort_if(!$token, 400, 'Yandex not connected');
        return $token;
    }
}
