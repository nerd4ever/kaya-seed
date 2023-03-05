<?php

namespace Nerd4ever\Kaya\Seed\Model;

use Nerd4ever\Common\Tools\IdTools;
use stdClass;
use Exception;

/**
 * My ArtifactManager
 *
 * @package Nerd4ever\Kaya\Seed\Entity
 * @author Sileno de Oliveira Brito
 */
class WebhookManager implements WebhookManagerInterface
{
    private int $defaultStock;
    private PublisherManagerInterface $publisherManager;
    private array $artifacts = [];

    /**
     * @param PublisherManagerInterface $publisherManager
     * @param int $defaultStock
     */
    public function __construct(PublisherManagerInterface $publisherManager, int $defaultStock)
    {
        $this->defaultStock = $defaultStock;
        $this->publisherManager = $publisherManager;
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

    public function provision($id, $orderId): ?stdClass
    {
        $artifact = $this->get($id);
        if (!$artifact instanceof Artifact) return null;
        if ($this->stock($id) <= 0) return null;
        $filename = $this->provision_filename($id, $orderId);
        if (file_exists($filename)) return null;
        $data = (object)[
            'id' => IdTools::gen(),
            'publicId' => IdTools::gen(),
            'privateId' => IdTools::gen(),
            'endpoint' => long2ip(rand(0, 4294967295)),
            'createdAt' => date('c'),
            'modifiedAt' => date('c'),
            'state' => Artifact::StateCreating,
            'action' => Artifact::ActionCreate,
        ];
        try {
            $handle = fopen($filename, 'w');
            if (!$handle) throw new Exception('failure on open provision file: ' . $filename);
            fwrite($handle, json_encode($data));
            if (error_get_last()) {
                fclose($handle);
                throw new Exception('failure on write in provision file: ' . $filename);
            }
            fflush($handle);
            if (error_get_last()) {
                fclose($handle);
                throw new Exception('failure on clear buffer in provision file: ' . $filename);
            }
            fclose($handle);
            return $data;
        } catch (Exception $ex) {
            return null;
        }
    }

    public function metadata($id, $orderId): array
    {
        if (!$this->exists($id, $orderId)) return [];
        $filename = $this->provision_filename($id, $orderId);
        return json_decode(file_get_contents($filename), true);
    }

    private function provision_filename($id, $orderId): string
    {
        return $this->cache_dir() . '/' . $id . '.' . $orderId . '.metadata';
    }

    public function exists($id, $orderId): bool
    {
        $artifact = $this->get($id);
        if (!$artifact instanceof Artifact) return false;
        $filename = $this->provision_filename($id, $orderId);
        return file_exists($filename);
    }


    public function execute($id, $orderId, $action): array
    {
        if (!in_array($action, $this->publisherManager->actions())) {
            return [];
        }
        $filename = $this->provision_filename($id, $orderId);
        $data = $this->metadata($id, $orderId);
        if ($data == null) return [];
        if (!isset($data['state']) || !isset($data['modifiedAt'])) return [];
        $data['action'] = $action;
        switch ($action) {
            case Artifact::ActionCreate:
                $data['state'] = Artifact::StateCreating;
                break;
            case Artifact::ActionStop:
                $data['state'] = Artifact::StateStopping;
                break;
            case Artifact::ActionStart:
                $data['state'] = Artifact::StateStarting;
                break;
            case Artifact::ActionTerminate:
                $data['state'] = Artifact::StateTerminating;
                break;
        }
        $data['modifiedAt'] = date('c');
        file_put_contents($filename, json_encode($data));
        return $data;
    }

    private function cache_dir(): string
    {
        return __DIR__ . '/../../sample/.data';
    }

    private function log_filename($id): string
    {
        return $this->cache_dir() . '/' . $id . '.log';
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
        $dir = dir($this->cache_dir());
        while ($f = $dir->read()) {
            if (in_array($f, ['.', '..'])) continue;
            if (!str_starts_with($f, $id) || pathinfo($this->cache_dir(). '/'. $f, PATHINFO_EXTENSION) != 'metadata') continue;
            $using++;
        }
        return $this->defaultStock - $using;
    }

    public function clear()
    {
        $files = array_diff(scandir($this->cache_dir()), array('.', '..'));
        foreach ($files as $f) {
            $filename = $this->cache_dir() . '/' . $f;
            if (!is_file($filename)) continue;
            unlink($filename);
        }
    }

    public function error($address, $error, $errorDescription = null): stdClass
    {
        return (object)array_filter([
            'error' => $error,
            'errorDescription' => $errorDescription,
            'address' => $address,
            'date' => date('c')
        ]);
    }
}
