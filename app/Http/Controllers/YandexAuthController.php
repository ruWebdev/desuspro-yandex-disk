<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class YandexAuthController extends Controller
{
    /**
     * Display the Yandex.Disk token management page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        return Inertia::render('Admin/AuthorizeYandex');
    }

    /**
     * Redirect to Yandex OAuth for authorization.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function connect()
    {
        $clientId = config('services.yandex.client_id');
        $redirectUri = route('integrations.yandex.callback');
        $scope = 'cloud_api:disk.app_folder cloud_api:disk.read cloud_api:disk.write';
        
        $authUrl = "https://oauth.yandex.ru/authorize" . 
                  "?response_type=code" . 
                  "&client_id={$clientId}" . 
                  "&redirect_uri=" . urlencode($redirectUri) . 
                  "&scope=" . urlencode($scope);
        
        return redirect($authUrl);
    }

    /**
     * Handle the Yandex OAuth callback.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request)
    {
        $code = $request->get('code');
        
        if (!$code) {
            return redirect()->route('admin.yandex.token')
                ->with('error', 'Не удалось получить код авторизации от Яндекс.Диска');
        }
        
        try {
            $client = new \GuzzleHttp\Client();
            
            $response = $client->post('https://oauth.yandex.ru/token', [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'client_id' => config('services.yandex.client_id'),
                    'client_secret' => config('services.yandex.client_secret'),
                ],
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['access_token'])) {
                // Store the token in the database or cache
                \App\Models\Setting::updateOrCreate(
                    ['key' => 'yandex_disk_token'],
                    ['value' => $data['access_token']]
                );
                
                if (isset($data['expires_in'])) {
                    $expiresAt = now()->addSeconds($data['expires_in']);
                    \App\Models\Setting::updateOrCreate(
                        ['key' => 'yandex_disk_token_expires_at'],
                        ['value' => $expiresAt]
                    );
                }
                
                if (isset($data['refresh_token'])) {
                    \App\Models\Setting::updateOrCreate(
                        ['key' => 'yandex_disk_refresh_token'],
                        ['value' => $data['refresh_token']]
                    );
                }
                
                return redirect()->route('admin.yandex.token')
                    ->with('success', 'Яндекс.Диск успешно подключен!');
            }
            
            return redirect()->route('admin.yandex.token')
                ->with('error', 'Не удалось получить токен доступа');
                
        } catch (\Exception $e) {
            \Log::error('Yandex OAuth Error: ' . $e->getMessage());
            
            return redirect()->route('admin.yandex.token')
                ->with('error', 'Ошибка при подключении к Яндекс.Диску: ' . $e->getMessage());
        }
    }
    
    /**
     * Get the current Yandex.Disk token status.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status()
    {
        $token = \App\Models\Setting::where('key', 'yandex_disk_token')->value('value');
        $expiresAt = \App\Models\Setting::where('key', 'yandex_disk_token_expires_at')->value('value');
        
        return response()->json([
            'connected' => !empty($token),
            'expires_at' => $expiresAt,
        ]);
    }
}
