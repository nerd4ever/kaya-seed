<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
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

$app = new Application();

$manager = new ArtifactManager(5);
$manager->add(
    (new Artifact())->setId(to_uuidv4('kaya-seed-one'))
        ->setShortname('kaya-seed-one')
        ->setDisplayName('Simple Kaya Seed Example One')
        ->setEnabled(true)
);
$manager->add(
    (new Artifact())->setId(to_uuidv4('kaya-seed-two'))
        ->setShortname('kaya-seed-two')
        ->setDisplayName('Simple Kaya Seed Example Two')
        ->setEnabled(true)
);

/**
 * Para listar todos os produtos e serviços
 */
$app->get('/kaya-marketplace/discovery', function () use ($app, $manager) {
    $artifacts = [];
    $list = $manager->all();
    foreach ($list as $l) {
        if (!$l instanceof Artifact) continue;
        $artifacts[] = [
            'artifact' => $l,
            'stock' => $manager->stock($l->getId()),
        ];
    }
    return $app->json(
        [
            'artifacts' => $artifacts,
            'actions' => $manager->actions(),
            'states' => $manager->states()
        ]
    );
});
/**
 * Para obter os detalhes de um produto ou serviço específico
 */
$app->get('/kaya-marketplace/artifact/{id}', function (Request $request, $id) use ($app, $manager) {
    $artifact = $manager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($manager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }
    return $app->json(
        [
            'artifact' => $artifact,
            'stock' => $manager->stock($id),
        ]
    );
});
/**
 * Para obter os detalhes de um provisionamento de produto ou serviço específico contratado
 */
$app->get('/kaya-marketplace/artifact/{id}/order/{orderId}', function (Request $request, $id, $orderId) use ($app, $manager) {
    $artifact = $manager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($manager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }

    if (!$manager->exists($id, $orderId)) {
        $manager->log_write($id, 'get invalid order: ' . $orderId);
        return $app->json($manager->error($request->getClientIp(), 'artifact_provision_not_found'), 404);
    }
    $metadata = $manager->metadata($id, $orderId);
    if (empty($metadata)) {
        $manager->log_write($id, 'get invalid order, provision is empty: ' . $orderId);
        return $app->json($manager->error($request->getClientIp(), 'artifact_provision_empty'), 404);
    }
    return $app->json(
        [
            'artifact' => $artifact,
            'metadata' => $metadata,
        ]
    );
});
/**
 * Para obter o estoque de um produto ou serviço específico
 */
$app->get('/kaya-marketplace/artifact/{id}/stock', function (Request $request, $id) use ($app, $manager) {
    $artifact = $manager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($manager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }
    return $app->json(
        [
            'artifact' => $artifact,
            'stock' => $manager->stock($id),
        ]
    );
});
/**
 * Para obter o log de um produto ou serviço específico
 */
$app->get('/kaya-marketplace/artifact/{id}/log', function (Request $request, $id) use ($app, $manager) {
    $artifact = $manager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($manager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }
    return $app->json(['log' => $manager->log($id)]);
});
/**
 * Para criar um novo pedido
 */
$app->post('/kaya-marketplace/artifact/{id}/order/{orderId}', function (Request $request, $id, $orderId) use ($app, $manager) {
    $artifact = $manager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($manager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }
    if ($manager->stock($id) <= 0) {
        return $app->json($manager->error($request->getClientIp(), 'artifact_out_of_stock'), 409);
    }
    if ($manager->exists($id, $orderId)) {
        return $app->json($manager->error($request->getClientIp(), 'provisiona_already_exists'), 409);
    }
    $metadata = $manager->provision($id, $orderId);
    if (empty($metadata)) {
        $manager->log_write($id, 'provision failed to order: ' . $orderId);
        return $app->json($manager->error($request->getClientIp(), 'artifact_provision_failed'), 422);
    }
    return $app->json(
        [
            'artifact' => $artifact,
            'metadata' => $metadata
        ]
    );
});
/**
 * Para atualizar o status de um produto ou pedido
 */
$app->put('/kaya-marketplace/artifact/{id}/order/{orderId}/{action}', function (Request $request, $id, $orderId, $action) use ($app, $manager) {
    $artifact = $manager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($manager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }
    if (!in_array($action, $manager->actions())) {
        $manager->log_write($id, 'unsupported action to order: ' . $orderId);
        return $app->json($manager->error(
            $request->getClientIp(),
            'artifact_unsupported_action',
            'unsupported action ' . $action . ' to order ' . $orderId . ', available actions are: ' . join(', ', $manager->actions())
        ), 422);
    }
    if (!$manager->exists($id, $orderId)) {
        return $app->json($manager->error($request->getClientIp(), 'artifact_provision_not_found'), 404);
    }

    $data = $manager->execute($id, $orderId, $action);
    return $app->json(
        [
            'artifact' => $artifact,
            'metadata' => $data
        ]
    );
});
$app->run();