<?php

namespace Nerd4ever\Kaya\Seed\Model;

use stdClass;

interface TokenManagerInterface
{
    public function authorize(string $clientId, string $username, string $password): ?stdClass;

    public function refresh(string $refreshToken): ?stdClass;

    public function revoke(): bool;

    public function validate(string $jwt, array $roles, &$decodedToken): bool;

    public function has_roles(string $decodedToken, array $roles): bool;

    public function has_role(string $decodedToken, array $role): bool;
}