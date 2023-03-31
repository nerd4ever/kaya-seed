<?php

namespace Nerd4ever\Kaya\Seed\Model;

use JsonSerializable;

/**
 * Produto ou serviço a ser comercializado
 */
class Artifact implements JsonSerializable
{
    const ActionCreate = 'create';
    const ActionStop = 'stop';
    const ActionStart = 'start';
    const ActionTerminate = 'terminate';

    const StateCreating = 'creating'; // Estado de criação de um recurso
    const StateCreated = 'created'; // Estado de um recurso que já foi criado
    const StateStopping = 'stopping'; // Estado de um recurso que está sendo parado
    const StateStopped = 'stopped'; // Estado de um recurso que foi parado
    const StateStarting = 'starting'; // Estado de um recurso que está sendo iniciado
    const StateRunning = 'running'; // Estado de um recurso que está em execução
    const StateTerminating = 'terminating'; // Estado de um recurso que está sendo encerrado
    const StateTerminated = 'terminated'; // Estado de um recurso que já foi encerrado

    private string $id; // Identificador único do produto ou serviço a ser comercializado (geralmente uuid)
    private string $displayName; // Nome de exibição do produto
    private string $shortname;  // Nome único usado para identificar o produto
    private bool $enabled; // Indica se o serviço está ativado ou desativado

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return Artifact
     */
    public function setId(string $id): Artifact
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * @param string $displayName
     * @return Artifact
     */
    public function setDisplayName(string $displayName): Artifact
    {
        $this->displayName = $displayName;
        return $this;
    }

    /**
     * @return string
     */
    public function getShortname(): string
    {
        return $this->shortname;
    }

    /**
     * @param string $shortname
     * @return Artifact
     */
    public function setShortname(string $shortname): Artifact
    {
        $this->shortname = $shortname;
        return $this;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     * @return Artifact
     */
    public function setEnabled(bool $enabled): Artifact
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'displayName' => $this->getDisplayName(),
            'shortname' => $this->getShortname(),
            'enabled' => $this->isEnabled(),
        ];
    }

}
