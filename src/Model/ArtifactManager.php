<?php

namespace Nerd4ever\Kaya\Seed\Model;

use Nerd4ever\Common\Tools\IdTools;
use DateTime;

/**
 * My ArtifactManager
 *
 * @package Nerd4ever\Kaya\Seed\Entity
 * @author Sileno de Oliveira Brito
 */
class ArtifactManager implements ArtifactManagerInterface
{
    private int $defaultStock;

    private array $artifacts = [];

    /**
     * @param int $defaultStock
     */
    public function __construct(int $defaultStock)
    {
        $this->defaultStock = $defaultStock;
    }

    public function add(Artifact $artifact): bool
    {
        if (isset($this->artifacts[$artifact->getId()])) return false;
        $this->artifacts[$artifact->getId()] = $artifact;
        return true;
    }

    public function all(): array
    {
        $data = array_values($this->artifacts);
        foreach ($data as $d) {
            if (!$d instanceof Artifact) continue;
            $this->log_write($d->getId(), 'artifact load');
        }
        return $data;
    }

    public function get($id): ?Artifact
    {
        if (!isset($this->artifacts[$id])) return null;
        $this->log_write($id, 'artifact read');
        return $this->artifacts[$id];
    }

    public function log($id): array
    {
        $filename = $this->log_filename($id);
        $data = [];
        if (file_exists($filename)) {
            $data = json_decode(file_get_contents($filename), true);
        }
        $this->log_write($id, 'log read');
        return $data;
    }

    public function provision($id, $orderId): ?string
    {
        $artifact = $this->get($id);
        if ($artifact instanceof Artifact) return null;
        $filename = $this->provision_filename($id, $orderId);
        if (file_exists($filename)) return null;
        $data = [
            'id' => IdTools::gen(),
            'publicId' => IdTools::gen(),
            'privateId' => IdTools::gen(),
            'endpoint' => long2ip(rand(0, 4294967295)),
            'createdAt' => new DateTime(),
            'modifiedAt' => new DateTime(),
            'state' => Artifact::StateCreating
        ];
        file_put_contents($filename, json_encode($data));
        return $data['id'];
    }

    public function metadata($id, $orderId): ?array
    {
        if (!$this->exists($id, $orderId)) return null;
        $filename = $this->provision_filename($id, $orderId);
        return json_decode(file_get_contents($filename, true));
    }

    private function provision_filename($id, $orderId): string
    {
        return $id . '.' . $orderId . '.metadata';
    }

    public function exists($id, $orderId): bool
    {
        $artifact = $this->get($id);
        if ($artifact instanceof Artifact) return false;
        $filename = $this->provision_filename($id, $orderId);
        return file_exists($filename);
    }

    public function change($id, $orderId, $state): array
    {
        if (!in_array($state, [
            Artifact::StateCreating,
            Artifact::StateCreated,
            Artifact::StatePausing,
            Artifact::StatePaused,
            Artifact::StateStarting,
            Artifact::StateRunning,
            Artifact::StateStopping,
            Artifact::StateTerminating,
            Artifact::StateTerminated,
        ])) {
            return [];
        }
        $filename = $this->provision_filename($id, $orderId);
        $data = $this->metadata($id, $orderId);
        if ($data == null) return [];
        if (!isset($data['state']) || !isset($data['modifiedAt'])) return [];
        $data['state'] = $state;
        $data['modifiedAt'] = new DateTime();
        file_put_contents($filename, json_encode($data));
        return $data;
    }

    private function log_filename($id): string
    {
        return $id . '.log';
    }

    public function log_write(string $id, string $message)
    {
        $filename = $this->log_filename($id);
        $data = [];
        if (file_exists($filename)) {
            $data = json_decode(file_get_contents($filename), true);
        }
        array_unshift($data, sprintf('[%s] %s', date('c'), $message));
        file_put_contents($filename, json_encode($data));
    }

    public function stock($id): int
    {
        $using = 0;
        $dir = dir('.');
        while ($f = $dir->read()) {
            if (in_array($f, ['.', '..'])) continue;
            if (substr($f, 0, strlen($id)) != $id || pathinfo($id, PATHINFO_EXTENSION) == 'metadata') continue;
            $using++;
        }
        return $this->defaultStock - $using;
    }
}