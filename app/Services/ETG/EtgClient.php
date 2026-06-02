<?php

namespace App\Services\ETG;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;

class EtgClient
{
    private readonly string $baseUrl;
    private readonly string $username;
    private readonly string $password;

    public function __construct()
    {
        $this->baseUrl  = rtrim((string) config('services.etg.base_url'), '/');
        $this->username = (string) config('services.etg.username');
        $this->password = (string) config('services.etg.password');
    }

    /**
     * @return array{download_url: string, last_update: string}
     * @throws RequestException|ConnectionException
     */
    public function getDumpInfo(string $endpointPath, object|array|null $body = null): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpointPath, '/');

        $payload = $body ?? new \stdClass();

        $this->log()->info('Fetching dump info.', ['endpoint' => $endpointPath]);

        $response = Http::withBasicAuth($this->username, $this->password)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->retry(3, 2000, throw: true)
            ->post($url, $payload);

        if (!$response->successful()) {
            $this->log()->error('ETG API returned an error.', [
                'endpoint' => $endpointPath,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);

            $response->throw();
        }

        $data = $response->json('data');

        if (empty($data['url']) || empty($data['last_update'])) {
            throw new RuntimeException(
                "Unexpected ETG response for {$endpointPath} — missing url or last_update. Body: " . $response->body()
            );
        }

        $this->log()->info('Dump info fetched.', [
            'endpoint'    => $endpointPath,
            'last_update' => $data['last_update'],
        ]);

        return [
            'download_url' => $data['url'],
            'last_update'  => $data['last_update'],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     * @throws RequestException|ConnectionException
     */
    public function post(string $endpointPath, array $body): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpointPath, '/');

        $this->log()->info('ETG B2B POST.', ['endpoint' => $endpointPath]);

        $response = Http::withBasicAuth($this->username, $this->password)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->retry(2, 1000, throw: true)
            ->timeout(120)
            ->post($url, $body);

        if (!$response->successful()) {
            $this->log()->error('ETG B2B API error.', [
                'endpoint' => $endpointPath,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);

            $response->throw();
        }

        $json = $response->json();
        return is_array($json) ? $json : [];
    }

    /**
     * GET /hotel/order/document/voucher/download/?data={"partner_order_id":"…","language":"…"}
     *
     * @see https://docs.emergingtravel.com/docs/affiliate-api/documents/retrieve-voucher/
     *
     * Response body is either PDF bytes or JSON with an `error` key (pending, failed_to_generate_document, etc.).
     */
    public function getVoucherDownload(string $partnerOrderId, string $language): \Illuminate\Http\Client\Response
    {
        $url = $this->baseUrl . '/api/b2b/v3/hotel/order/document/voucher/download/';
        $data = json_encode([
            'partner_order_id' => $partnerOrderId,
            'language'         => $language,
        ], JSON_UNESCAPED_UNICODE);

        $this->log()->info('ETG voucher download GET.', ['partner_order_id' => $partnerOrderId]);

        return Http::withBasicAuth($this->username, $this->password)
            ->timeout(120)
            ->accept('*/*')
            ->get($url, ['data' => $data]);
    }

    private function log(): LoggerInterface
    {
        return Log::channel('etg');
    }
}
