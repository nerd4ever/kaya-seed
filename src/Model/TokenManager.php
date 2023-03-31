<?php

namespace Nerd4ever\Kaya\Seed\Model;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Exception;
use GuzzleHttp\Psr7\Request;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use stdClass;

/**
 * My AuthorizationManager
 *
 * @package Nerd4ever\Kaya\Seed\Model
 * @author Sileno de Oliveira Brito
 */
final class TokenManager implements TokenManagerInterface
{
    private bool $sandbox = true;
    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private int $expirationTime = 0;

    public function validate(string $jwt, array $roles = [], &$decodedToken = null): bool
    {
        try {
            $decodedToken = null;

            // Decodificando o cabeçalho do token para obter o nome do algoritmo
            $decodedHeader = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $jwt)[0]));
            $decodedPayload = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $jwt)[1]));
            if (!isset($decodedHeader->kid) || !isset($decodedPayload->iss) || !isset($decodedHeader->alg) || !isset($decodedPayload->exp)) {
                return false;
            }

            // Verificando se o token não está expirado
            $expirationTime = $decodedPayload->exp;
            $currentTime = time();
            if ($expirationTime < $currentTime) {
                return false;
            }

            $jwtSigningCert = $this->get_key($decodedHeader->kid, $decodedPayload->iss);
            $algorithm = $decodedHeader->alg;

            // Validando o token
            $decoded = JWT::decode($jwt, new Key($jwtSigningCert, $algorithm));

            // Verificando se o token possui o claim especificado
            foreach ($roles as $desiredClaim) {
                if (!property_exists($decoded['roles'], $desiredClaim)) {
                    // O token não possui o claim desejado
                    return false;
                }
            }
            $decodedToken = $decoded;
            return true;
        } catch (Exception $ex) {
            error_log($ex->getMessage());
            return false;
        }
    }

    public function authorize(string $clientId, string $username, string $password): ?stdClass
    {
        try {
            $client = new Client([
                'verify' => !$this->sandbox()
            ]);
            $headers = [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ];
            $options = [
                'form_params' => [
                    'grant_type' => 'password',
                    'username' => $username,
                    'password' => $password,
                    'client_id' => $clientId
                ]];
            return $this->token_authorize($headers, $client, $options);
        } catch (Exception $ex) {
            error_log($ex->getMessage());
            return null;
        }
    }

    public function refresh(?string $refreshToken = null): ?stdClass
    {
        if (empty($refreshToken)) $refreshToken = $this->refreshToken;
        $client = new Client([
            'verify' => !$this->sandbox()
        ]);
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $options = [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken
            ]];
        return $this->token_authorize($headers, $client, $options);
    }

    public function token_authorize(array $headers, Client $client, array $options): ?stdClass
    {
        $request = new Request('POST', $this->base_uri() . '/platform/v1/oauth2/token', $headers);
        $res = $client->sendAsync($request, $options)->wait();
        $data = (object)array_filter(json_decode($res->getBody(), true));
        if (json_last_error() !== JSON_ERROR_NONE) return null;
        if (!$this->credential_save($data)) return null;
        return $data;
    }

    /**
     * @return string|null
     */
    public function access_token(): ?string
    {
        if ($this->expirationTime < time()) {
            if (empty($this->refreshToken)) return null;
            if ($this->refresh($this->refreshToken) == null) {
                $this->refreshToken = null;
                return null;
            }
        }
        return $this->accessToken;
    }

    public function revoke(): bool
    {
        $token = $this->access_token();
        if (empty($token)) return false;

        $client = new Client([
            'verify' => !$this->sandbox()
        ]);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        ];
        $body = '';
        $request = new Request('DELETE', $this->base_uri() . '/platform/v1/oauth2', $headers, $body);
        $res = $client->sendAsync($request)->wait();

        // Clear cache
        $this->refreshToken = null;
        $this->accessToken = null;
        $this->expirationTime = 0;
        return $res->getStatusCode() === 204;
    }

    private function credential_save(stdClass $data): bool
    {
        if (!isset($data->id_token) || !isset($data->refresh_token) || !isset($data->token_type)) return false;
        $decodedPayload = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $data->id_token)[1]));
        if (!isset($decodedPayload->exp)) return false;
        if (!$this->validate($data->id_token, [])) return false;
        $this->accessToken = $data->id_token;
        $this->refreshToken = $data->refresh_token;
        $this->expirationTime = $decodedPayload->exp;
        return true;
    }

    public function has_roles(string $decodedToken, array $roles): bool
    {
        if (empty($roles)) return true;
        foreach ($roles as $role) {
            if ($this->has_role($decodedToken, $role)) return false;
        }
        return true;
    }

    public function has_role(string $decodedToken, array $role): bool
    {
        return isset($decodedToken->roles) && isset($decodedToken->roles->$role);
    }

    private function get_key(string $keyId, string $issuer): ?string
    {
        try {
            $client = new Client([
                    'verify' => !$this->sandbox()
                ]
            );
            $response = $client->get($issuer . '/.well-known/openid-configuration');
            $oidcDiscovery = json_decode((string)$response->getBody(), true);

            // URL do endpoint de certificados de assinatura do JWT
            $jwtSigningCertsUrl = $oidcDiscovery['jwks_uri'];

            // Solicitação HTTP GET para o endpoint de certificados de assinatura
            $response = $client->get($jwtSigningCertsUrl);
            $jwtSigningCerts = json_decode((string)$response->getBody(), true);

            // Encontrando o certificado correto com base no kid
            $jwtSigningCert = null;
            foreach ($jwtSigningCerts['keys'] as $key) {
                if ($key['kid'] == $keyId) {
                    $jwtSigningCert = $key;
                    break;
                }
            }
            if (empty($jwtSigningCert)) return null;

            if (!isset($jwtSigningCert['kty']) || !isset($jwtSigningCert['n']) || !isset($jwtSigningCert['e'])) {
                return null;
            }
            $data = JWK::parseKey($jwtSigningCert);
            $keyMaterial = $data->getKeyMaterial();
            if (is_string($keyMaterial)) {
                return $keyMaterial;
            } elseif (is_resource($keyMaterial) || $keyMaterial instanceof OpenSSLAsymmetricKey || $keyMaterial instanceof OpenSSLCertificate) {
                $keyData = openssl_pkey_get_details($keyMaterial);
                return $keyData['key'];
            } else {
                return null;
            }
        } catch (GuzzleException|Exception $ex) {
            error_log($ex->getMessage());
            return null;
        }
    }

    /**
     * @return bool
     */
    public function sandbox(): bool
    {
        return $this->sandbox;
    }

    private function base_uri(): string
    {
        return 'https://kaya-platform' . ($this->sandbox() ? '.sandbox' : '') . '.nerd4ever.com.br';
    }

}
