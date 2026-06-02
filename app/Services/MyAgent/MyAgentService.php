<?php

namespace App\Services\MyAgent;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MyAgentService
{
    /**
     * @throws Exception
     */
    public function get(string $uri, array $query = []): array
    {
        return $this->sendWithAuthRetry('GET', $uri, [
            'query' => $query,
        ]);
    }

    /**
     * @throws Exception
     */
    public function post(string $uri, array $payload = []): array
    {
        return $this->sendWithAuthRetry('POST', $uri, [
            'json' => $payload,
        ]);
    }

    /**
     * @throws Exception
     */
    private function sendWithAuthRetry(string $method, string $uri, array $options): array
    {
        $token = $this->getAuthToken();

        $response = $this->send($method, $uri, $this->withAuthKey($options, $token));

        if ($this->isAuthExpiredResponse($response)) {
            $this->log('Auth token expired. Refreshing token and retrying request.', [
                'method' => $method,
                'uri' => $uri,
                'status' => $response->status(),
                'body' => $this->maskSensitiveBody($response->body()),

            ]);

            $this->forgetAuthToken();

            $token = $this->login();
            $response = $this->send($method, $uri, $this->withAuthKey($options, $token));
        }

        if (!$response->successful()) {
            $this->log('Request failed', [
                'method' => $method,
                'uri' => $uri,
                'status' => $response->status(),
                'body' => $this->maskSensitiveBody($response->body()),
            ], 'error');

            throw new Exception('MyAgent request failed: ' . $response->body());
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new Exception('MyAgent returned invalid JSON response.');
        }

        if (($data['success'] ?? true) === false) {
            $message = $this->extractErrorMessage($data);

            $this->log('Request returned unsuccessful response', [
                'method' => $method,
                'uri' => $uri,
                'response' => $data,
            ], 'warning');

            throw new Exception($message ?: 'MyAgent request was unsuccessful.');
        }

        $this->extendAuthTokenTtl($token);

        return $data;
    }

    /**
     * MyAgent docs pass auth token as auth_key request parameter.
     */
    private function withAuthKey(array $options, string $token): array
    {
        if (isset($options['query'])) {
            $options['query']['auth_key'] = $token;
            return $options;
        }

        if (isset($options['json'])) {
            $options['json']['auth_key'] = $token;
            return $options;
        }

        $options['query'] = ['auth_key' => $token];

        return $options;
    }

    /**
     * @throws Exception
     */
    private function getAuthToken(): string
    {
        $cacheKey = config('myagent.cache.auth_token_key');

        $token = Cache::get($cacheKey);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        return Cache::lock('myagent_auth_login_lock', 30)->block(10, function () use ($cacheKey) {
            $token = Cache::get($cacheKey);

            if (is_string($token) && $token !== '') {
                return $token;
            }

            return $this->login();
        });
    }

    /**
     * @throws Exception
     */
    private function login(): string
    {
        $baseUrl = rtrim(config('myagent.base_url'), '/');
        $url = $baseUrl . '/user/login';

        $payload = [
            'login' => config('myagent.login'),
            'password' => config('myagent.password'),
        ];

        $this->log('Login request', [
            'url' => $url,
            'login' => config('myagent.login'),
        ]);

        $response = $this->baseClient()
            ->asJson()
            ->post($url, $payload);

        $this->log('Login response', [
            'status' => $response->status(),
            'body' => $this->maskSensitiveBody($response->body()),
        ]);

        if (!$response->successful()) {
            throw new Exception('MyAgent login failed: ' . $response->body());
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new Exception('MyAgent login returned invalid JSON response.');
        }

        if (($data['success'] ?? true) === false) {
            $message = $this->extractErrorMessage($data);

            throw new Exception($message ?: 'MyAgent login was unsuccessful.');
        }

        if (isset($data['data']['session'], $data['data']['salt']) && empty($data['data']['auth_token'])) {
            throw new Exception('MyAgent login requires 2FA. Disable 2FA for the API user or use a dedicated API account without 2FA.');
        }

        $token = $data['data']['auth_token'] ?? null;

        if (!is_string($token) || $token === '') {
            $this->log('Login response did not contain auth token', [
                'response' => $data,
            ], 'error');

            throw new Exception('MyAgent login response did not contain auth token.');
        }

        Cache::put(
            config('myagent.cache.auth_token_key'),
            $token,
            now()->addSeconds((int) config('myagent.cache.auth_token_ttl'))
        );

        return $token;
    }

    private function forgetAuthToken(): void
    {
        Cache::forget(config('myagent.cache.auth_token_key'));
    }

    private function send(string $method, string $uri, array $options): Response
    {
        $baseUrl = rtrim(config('myagent.base_url'), '/');
        $url = $baseUrl . '/' . ltrim($uri, '/');

        $this->log('Request', [
            'method' => $method,
            'url' => $url,
            'options' => $this->maskSensitiveOptions($options),
        ]);

        $response = match (strtoupper($method)) {
            'GET' => $this->baseClient()->get($url, $options['query'] ?? []),
            'POST' => $this->baseClient()->asJson()->post($url, $options['json'] ?? []),
            default => throw new InvalidArgumentException("Unsupported MyAgent HTTP method: {$method}"),
        };

        $this->log('Response', [
            'method' => $method,
            'url' => $url,
            'status' => $response->status(),
            'body' => $this->maskSensitiveBody($response->body()),
        ]);

        return $response;
    }

    private function baseClient(): PendingRequest
    {
        return Http::timeout((int) config('myagent.timeout'))
            ->acceptJson()
            ->withHeaders([
                'Accept-Encoding' => 'gzip',
                'User-Agent' => config('myagent.user_agent'),
            ]);
    }

    private function isAuthExpiredResponse(Response $response): bool
    {
        if (in_array($response->status(), [401, 403], true)) {
            return true;
        }

        $data = $response->json();

        if (!is_array($data)) {
            return false;
        }

        if (($data['code'] ?? null) === 9) {
            return true;
        }

        $message = strtolower($this->extractErrorMessage($data));

        return ($data['success'] ?? true) === false
            && (
                str_contains($message, 'token')
                || str_contains($message, 'auth')
                || str_contains($message, 'unauthorized')
                || str_contains($message, 'expired')
                || str_contains($message, 'авториза')
                || str_contains($message, 'токен')
            );
    }

    private function extractErrorMessage(array $data): string
    {
        return (string) (
            $data['message']
            ?? $data['error']
            ?? $data['data']['message']
            ?? $data['errors'][0]['message']
            ?? ''
        );
    }

    private function maskSensitiveOptions(array $options): array
    {
        $masked = $options;

        foreach (['query', 'json'] as $key) {
            if (isset($masked[$key]['auth_key'])) {
                $masked[$key]['auth_key'] = '***';
            }

            if (isset($masked[$key]['password'])) {
                $masked[$key]['password'] = '***';
            }
        }

        return $masked;
    }

    private function extendAuthTokenTtl(string $token): void
    {
        Cache::put(
            config('myagent.cache.auth_token_key'),
            $token,
            now()->addSeconds((int) config('myagent.cache.auth_token_ttl'))
        );
    }

    private function log(string $message, array $context = [], string $level = 'info'): void
    {
        Log::channel('myagent')->{$level}($message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function maskSensitiveBody(string $body): string
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return $body;
        }

        if (isset($decoded['data']['auth_token'])) {
            $decoded['data']['auth_token'] = '***';
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
