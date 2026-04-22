<?php

class KrogerHttpClient
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private ?string $accessToken = null;

    public function __construct(array $config)
    {
        $this->clientId     = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->baseUrl      = rtrim($config['base_url'], '/');
    }

    public function get(string $path, array $query = []): array
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        $url = $this->baseUrl . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('Kroger API request failed: ' . curl_error($ch));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new RuntimeException("Kroger API error: HTTP {$status} - {$response}");
        }

        return json_decode($response, true) ?? [];
    }

    private function authenticate(): void
    {
        $ch = curl_init($this->baseUrl . '/v1/connect/oauth2/token');
        $body = http_build_query([
            'grant_type'    => 'client_credentials',
            'scope'         => 'product.compact profile.compact cart.basic',
        ]);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('Kroger auth failed: ' . curl_error($ch));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new RuntimeException("Kroger auth error: HTTP {$status} - {$response}");
        }

        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'] ?? null;

        if (!$this->accessToken) {
            throw new RuntimeException('Kroger auth: missing access_token');
        }
    }
}
