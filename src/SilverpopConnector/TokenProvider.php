<?php

namespace SilverpopConnector;

class TokenProvider {
  private string $clientId;
  private string $clientSecret;
  private string $refreshToken;
  private string $url; // e.g. api-campaign-us-1.goacoustic.com
  private ?string $accessToken = null;
  private int $expiresAt = 0;

  public function __construct($clientId, $secret, $refreshToken, $url) {
    $this->clientId = $clientId;
    $this->clientSecret = $secret;
    $this->refreshToken = $refreshToken;
    $this->url = $url;
  }

  public function get(): string {
    if (!$this->accessToken || time() >= $this->expiresAt - 600) { // refresh if <10min left
      $this->refresh();
    }
    return $this->accessToken;
  }

  public function refresh(): void {
    $http = new \GuzzleHttp\Client(['base_uri' => "{$this->url}"]);
    $resp = $http->post('/oauth/token', [
      'form_params' => [
        'grant_type' => 'refresh_token',
        'client_id' => $this->clientId,
        'client_secret' => $this->clientSecret,
        'refresh_token' => $this->refreshToken,
      ],
    ]);
    $json = json_decode((string)$resp->getBody(), true);
    $this->accessToken = $json['access_token'] ?? '';
    $this->expiresAt = time() + (int)($json['expires_in'] ?? 14400);
  }

}
