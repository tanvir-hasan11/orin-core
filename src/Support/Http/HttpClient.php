<?php

declare(strict_types=1);

namespace Orin\Support\Http;

/**
 * Thin cURL wrapper returning an HttpResponse value object.
 */
final class HttpClient
{
    public function __construct(private int $timeout = 30)
    {
    }

    /** @param array<string, string> $headers */
    public function postJson(string $url, array $payload, array $headers = []): HttpResponse
    {
        return $this->request('POST', $url, $payload, $headers);
    }

    /** @param array<string, string> $headers */
    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->request('GET', $url, null, $headers);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string> $headers
     */
    private function request(string $method, string $url, ?array $payload, array $headers): HttpResponse
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new HttpClientException('Unable to initialise cURL handle.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $body === false) {
            throw new HttpClientException(sprintf('HTTP request failed (%d): %s', $errno, $error));
        }

        $decoded = json_decode((string) $body, true);

        return new HttpResponse($status, (string) $body, is_array($decoded) ? $decoded : null);
    }
}
