<?php

namespace Nerd4ever\Kaya\Seed\Model;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

/**
 * My PublishManager
 *
 * @package Nerd4ever\Kaya\Seed\Model
 * @author Sileno de Oliveira Brito
 */
final class PublishManager implements PublisherManagerInterface
{
    private TokenManagerInterface $tokenManager;

    /**
     * @param TokenManagerInterface $tokenManager
     */
    public function __construct(TokenManagerInterface $tokenManager)
    {
        $this->tokenManager = $tokenManager;
    }

    public function install(string $name): bool
    {
        $token = $this->tokenManager->access_token();
        if (!empty($token)) return false;
        $client = new Client([
            'verify' => !$this->tokenManager->sandbox()
        ]);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        ];
        $request = new Request('PUT', $this->marketplace_uri() . '/rest/V1/nerd4ever/kaya/endpoint/' . $name, $headers, null);
        $res = $client->sendAsync($request)->wait();
        return $res->getStatusCode() === 202;
    }

    public function state(int $orderId, string $state): bool
    {
        if (!in_array($state, $this->states())) return false;
        $token = $this->tokenManager->access_token();
        if (!empty($token)) return false;
        $client = new Client([
            'verify' => !$this->tokenManager->sandbox()
        ]);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        ];
        $body = '';
        $request = new Request('PUT', sprintf($this->marketplace_uri() . '/rest/V1/nerd4ever/kaya/order/%d/%s', $orderId, $state), $headers, $body);
        $res = $client->sendAsync($request)->wait();
        return $res->getStatusCode() === 202;
    }

    private function marketplace_uri(): string
    {
        return 'https://marketplace' . ($this->tokenManager->sandbox() ? '.sandbox' : '') . '.nerd4ever.com.br';
    }

    public function actions(): array
    {
        return [
            Artifact::ActionCreate,
            Artifact::ActionStart,
            Artifact::ActionStop,
            Artifact::ActionTerminate,
        ];
    }

    public function states(): array
    {
        return [
            Artifact::StateCreating,
            Artifact::StateCreated,
            Artifact::StateStopped,
            Artifact::StateStarting,
            Artifact::StateRunning,
            Artifact::StateStopping,
            Artifact::StateTerminating,
            Artifact::StateTerminated,
        ];
    }
}