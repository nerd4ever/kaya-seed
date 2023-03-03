<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nerd4ever\Kaya\Seed\Model\ArtifactManager;
use Nerd4ever\Kaya\Seed\Model\Artifact;

function to_uuidv4($data): string
{
    $sha1 = sha1($data);
    $sha1 = str_replace('-', '', $sha1);
    $uuid = substr($sha1, 0, 8) . '-' .
        substr($sha1, 8, 4) . '-' .
        '4' . substr($sha1, 12, 3) . '-' .
        dechex(hexdec(substr($sha1, 16, 2)) & 0x3f | 0x80) . substr($sha1, 18, 2) . '-' .
        substr($sha1, 20, 12);
    return $uuid;
}

$app = new Silex\Application();
$manager = new ArtifactManager(20);
$manager->add(
    (new Artifact())->setId('kaya_seed_' . to_uuidv4('kaya-seed-one'))
        ->setShortname('kaya-seed-one')
        ->setDisplayName('Simple Kaya Seed Example One')
        ->setEnabled(true)
);
$manager->add(
    (new Artifact())->setId('kaya_seed_' . to_uuidv4('kaya-seed-two'))
        ->setShortname('kaya-seed-two')
        ->setDisplayName('Simple Kaya Seed Example Two')
        ->setEnabled(true)
);

/**
 * Para listar todos os produtos e serviços
 */
$app->get('/kaya-marketplace/artifact', function () use ($app, $manager) {
    return $app->json(
        [
            'artifacts' => $manager->all()
        ]
    );
});
/**
 * Para obter os detalhes de um produto ou serviço específico
 */
$app->get('/kaya-marketplace/artifact/{id}/{orderId}', function ($id, $orderId) use ($app, $manager) {
    $artifact = $manager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->abort(404, 'artifact_not_found');
    }
    return $app->json(
        [
            'artifact' => $artifact,
            'metadata' => $manager->metadata($id, $orderId),
        ]
    );
});
/**
 * Para obter o estoque de um produto ou serviço específico
 */
$app->get('/kaya-marketplace/artifact/{id}/inventory', function ($id) use ($app, $manager) {
    $artifact = $manager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->abort(404, 'artifact_not_found');
    }
    return $app->json([$manager->stock($id)]);
});
/**
 * Para obter o log de um produto ou serviço específico
 */
$app->get('/kaya-marketplace/artifact/{id}/log', function ($id) use ($app, $manager) {
    $artifact = $manager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->abort(404, 'artifact_not_found');
    }
    return $app->json(['log' => $manager->log($id)]);
});
/**
 * Para criar um novo pedido
 */
$app->post('/kaya-marketplace/artifact/{id}/{orderId}', function ($id, $orderId) use ($app, $manager) {
    $artifact = $manager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->abort(404, 'artifact_not_found');
    }
    if (!$manager->provision($id, $orderId)) {
        $manager->log_write($id, 'provision failed to order: ' . $orderId);
        return $app->abort(422, 'artifact_provision_failed');
    }
    return $app->json(
        [
            'artifact' => $artifact,
            'metadata' => $manager->metadata($id, $orderId)
        ]
    );
});
/**
 * Para atualizar o status de um produto ou pedido
 */
$app->put('/kaya-marketplace/artifact/{id}/{orderId}/{state}', function ($id, $orderId, $state) use ($app, $manager) {
    $artifact = $manager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->abort(404, 'artifact_not_found');
    }
    if (!$manager->exists($id, $orderId)) {
        return $app->abort(404, 'artifact_provision_not_found');
    }
    $data = $manager->change($id, $orderId, $state);
    return $app->json(
        [
            'artifact' => $artifact,
            'metadata' => $data
        ]
    );
});
$app->run();