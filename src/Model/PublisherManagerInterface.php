<?php

namespace Nerd4ever\Kaya\Seed\Model;

interface PublisherManagerInterface
{

    public function install(string $name): bool;

    public function state(int $orderId, string $state): bool;

    public function actions(): array;

    public function states(): array;
}