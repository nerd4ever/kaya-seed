<?php

namespace Nerd4ever\Kaya\Seed\Model;

interface ArtifactManagerInterface
{
    public function add(Artifact $artifact): bool;

    public function all(): array;

    public function get($id): ?Artifact;

    public function log($id): array;

    public function stock($id): int;

    public function provision($id, $orderId): ?string;

    public function exists($id, $orderId): bool;

    public function metadata($id, $orderId): ?array;

    public function change($id, $orderId, $state): array;
}