<?php

namespace Nerd4ever\Kaya\Seed\Model;

use stdClass;

interface TokenManagerInterface
{
    /**
     * Realiza a autenticação do usuário e retorna um objeto contendo informações sobre o token e seu tempo de vida;
     *
     * @param string $clientId
     * @param string $username
     * @param string $password
     *
     * @return stdClass|null
     */
    public function authorize(string $clientId, string $username, string $password): ?stdClass;

    /**
     * Realiza a atualização do token de acesso;
     *
     * @param string|null $refreshToken
     *
     * @return stdClass|null
     */
    public function refresh(?string $refreshToken): ?stdClass;

    /**
     * Revoga todos os tokens ativos do usuário e o refresh token;
     *
     * @return bool
     */
    public function revoke(): bool;

    /**
     * Verifica se o token é válido, realizando a validação da assinatura, verificação de expiração e verificação de
     * claims, e retorna true se o token é válido ou false caso contrário;
     *
     * @param string $jwt
     * @param array $roles
     * @param $decodedToken
     *
     * @return bool
     */
    public function validate(string $jwt, array $roles, &$decodedToken): bool;

    /**
     * Verifica se o token possui todas as roles solicitadas e retorna true se possui ou false caso contrário;
     *
     * @param string $decodedToken
     * @param array $roles
     *
     * @return bool
     */
    public function has_roles(string $decodedToken, array $roles): bool;

    /**
     * Verifica se o token possui a role solicitada e retorna true se possui ou false caso contrário;
     *
     * @param string $decodedToken
     * @param array $role
     *
     * @return bool
     */
    public function has_role(string $decodedToken, array $role): bool;

    /**
     * Retorna true se a aplicação está em modo de sandbox ou false caso contrário;
     *
     * @return bool
     */
    public function sandbox(): bool;

    /**
     * Retorna o token de acesso válido, se o token tiver expirado, o método realiza o refresh_token para gerar um novo
     * token válido.
     *
     * @return string|null
     */
    public function access_token(): ?string;
}