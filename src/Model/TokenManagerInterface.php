<?php

namespace Nerd4ever\Kaya\Seed\Model;

interface TokenManagerInterface
{
    public function validate(string $jwt, array $roles, &$decodedToken): bool;

    public function has_roles(string $decodedToken, array $roles): bool;

    public function has_role(string $decodedToken, array $role): bool;
}