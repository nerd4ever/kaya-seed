<?php

namespace Nerd4ever\Kaya\Seed\Model;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Exception;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;

/**
 * My AuthorizationManager
 *
 * @package Nerd4ever\Kaya\Seed\Model
 * @author Sileno de Oliveira Brito
 */
class TokenManager implements TokenManagerInterface
{
    public function validate(string $jwt, array $roles, &$decodedToken = null): bool
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

            $jwtSigningCert = $this->getCert($decodedHeader->kid, $decodedPayload->iss);
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

    private function getCert(string $keyId, string $issuer): ?string
    {
        try {
            $client = new Client();
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
                throw new Exception('Tipo de chave não suportado');
            }
        } catch (GuzzleException|Exception $ex) {
            return null;
        }
    }
}
