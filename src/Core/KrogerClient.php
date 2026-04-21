<?php
class KrogerClient {
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private ?string $accessToken = null;

    public function __construct(array $config) {
        $this->baseUrl = $config['base_url'];
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
    }

    private function getAccessToken(): string {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new RuntimeException('Missing Kroger API credentials in environment.');
        }

        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $ch = curl_init('https://api.kroger.com/v1/connect/oauth2/token');
        $data = http_build_query([
            'grant_type' => 'client_credentials',
            'scope' => 'product.compact',
        ]);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('Kroger token request failed');
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $json = json_decode($response, true);
        $this->accessToken = $json['access_token'] ?? null;

        if (!$this->accessToken) {
            $message = $json['error_description'] ?? $json['error'] ?? 'No access token from Kroger';
            throw new RuntimeException($status >= 400 ? "Kroger auth failed: {$message}" : $message);
        }

        return $this->accessToken;
    }

    private function request(string $endpoint, array $params = []): array {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->getAccessToken(),
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('Kroger API request failed');
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $json = json_decode($response, true) ?? [];
        if ($status >= 400) {
            $message = $json['errors'][0]['message']
                ?? $json['errors']['reason']
                ?? $json['error_description']
                ?? $json['error']
                ?? 'Kroger API request failed';
            throw new RuntimeException($message);
        }

        return $json;
    }

    public function searchProducts(string $term, string $locationId, int $limit = 20): array {
        return $this->request('/products', [
            'filter.term' => $term,
            'filter.locationId' => $locationId,
            'filter.limit' => $limit,
        ]);
    }

    public function searchLocations(string $zipCode, int $limit = 8): array {
        return $this->request('/locations', [
            'filter.zipCode.near' => $zipCode,
            'filter.limit' => $limit,
        ]);
    }

    public function getProduct(string $productId, string $locationId): array {
        return $this->request('/products/' . rawurlencode($productId), [
            'filter.locationId' => $locationId,
        ]);
    }
}
