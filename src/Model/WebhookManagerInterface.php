<?php

namespace Nerd4ever\Kaya\Seed\Model;

use stdClass;

interface WebhookManagerInterface
{
    public function add(Artifact $artifact): bool;

    public function all(): array;

    public function get($id): ?Artifact;

    public function log($id): array;

    public function stock($id): int;

    public function provision($id, $orderId): ?stdClass;

    public function exists($id, $orderId): bool;

    public function metadata($id, $orderId): array;

    public function execute($id, $orderId, $action): array;

    public function error($address, $error, $errorDescription): stdClass;

}