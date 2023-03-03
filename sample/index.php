<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Nerd4ever\Kaya\Seed\Model\ArtifactManager;
use Nerd4ever\Kaya\Seed\Model\TokenManager;
use Nerd4ever\Kaya\Seed\Model\Artifact;

function to_uuidv4($value): string
{
    $hash = sha1($value);
    $data = str_replace('-', '', $hash);
    return substr($data, 0, 8) . '-' .
        substr($data, 8, 4) . '-' .
        '4' . substr($data, 12, 3) . '-' .
        dechex(hexdec(substr($data, 16, 2)) & 0x3f | 0x80) . substr($data, 18, 2) . '-' .
        substr($data, 20, 12);
}
$app = new Application();
$artifactManager = new ArtifactManager(5);
$tokenManager = new TokenManager();

$app->before(function (Request $request) use ($app, $artifactManager, $tokenManager) {
    if (!$request->headers->has('Authorization')) {
        return $app->json($artifactManager->error($request->getClientIp(), 'authorization_required'), 401);
    }
    $tmp = $request->headers->get('Authorization');
    $pattern = "/^(bearer) .+/i";
    if (!preg_match_all($pattern, $tmp)) {
        return $app->json($artifactManager->error($request->getClientIp(), 'authorization_must_be_bearer'), 401);
    }
    $data = explode(' ', $tmp, 2);
    $jwt = $data[1];

    if (!$tokenManager->validate($jwt, [])) {
        return $app->json($artifactManager->error($request->getClientIp(), 'authorization_rejected'), 401);
    }
    return null;
});

$artifactManager->add(
    (new Artifact())->setId(to_uuidv4('kaya-seed-one'))
        ->setShortname('kaya-seed-one')
        ->setDisplayName('Simple Kaya Seed Example One')
        ->setEnabled(true)
);
$artifactManager->add(
    (new Artifact())->setId(to_uuidv4('kaya-seed-two'))
        ->setShortname('kaya-seed-two')
        ->setDisplayName('Simple Kaya Seed Example Two')
        ->setEnabled(true)
);
/**
 * Para listar todos os produtos e serviços
 */
$app->get('/kaya-marketplace/discovery', function () use ($app, $artifactManager) {
    $artifacts = [];
    $list = $artifactManager->all();
    foreach ($list as $l) {
        if (!$l instanceof Artifact) continue;
        $artifacts[] = [
            'artifact' => $l,
            'stock' => $artifactManager->stock($l->getId()),
        ];
    }
    return $app->json(
        [
            'artifacts' => $artifacts,
            'actions' => $artifactManager->actions(),
            'states' => $artifactManager->states()
        ]
    );
});
/**
 * Para obter os detalhes de um produto ou serviço específico
 */
$app->get('/kaya-marketplace/artifact/{id}', function (Request $request, $id) use ($app, $artifactManager) {
    $artifact = $artifactManager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($artifactManager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }
    return $app->json(
        [
            'artifact' => $artifact,
            'stock' => $artifactManager->stock($id),
        ]
    );
});
/**
 * Para obter os detalhes de um provisionamento de produto ou serviço específico contratado
 */
$app->get('/kaya-marketplace/artifact/{id}/order/{orderId}', function (Request $request, $id, $orderId) use ($app, $artifactManager) {
    $artifact = $artifactManager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($artifactManager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }
    if (!$artifactManager->exists($id, $orderId)) {
        $artifactManager->log_write($id, 'get invalid order: ' . $orderId);
        return $app->json($artifactManager->error($request->getClientIp(), 'artifact_provision_not_found'), 404);
    }
    $metadata = $artifactManager->metadata($id, $orderId);
    if (empty($metadata)) {
        $artifactManager->log_write($id, 'get invalid order, provision is empty: ' . $orderId);
        return $app->json($artifactManager->error($request->getClientIp(), 'artifact_provision_empty'), 404);
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
$app->get('/kaya-marketplace/artifact/{id}/stock', function (Request $request, $id) use ($app, $artifactManager) {
    $artifact = $artifactManager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($artifactManager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }
    return $app->json(
        [
            'artifact' => $artifact,
            'stock' => $artifactManager->stock($id),
        ]
    );
});
/**
 * Para obter o log de um produto ou serviço específico
 */
$app->get('/kaya-marketplace/artifact/{id}/log', function (Request $request, $id) use ($app, $artifactManager) {
    $artifact = $artifactManager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($artifactManager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }
    return $app->json(['log' => $artifactManager->log($id)]);
});
/**
 * Para criar um novo pedido
 */
$app->post('/kaya-marketplace/artifact/{id}/order/{orderId}', function (Request $request, $id, $orderId) use ($app, $artifactManager) {
    $artifact = $artifactManager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($artifactManager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }
    if ($artifactManager->stock($id) <= 0) {
        return $app->json($artifactManager->error($request->getClientIp(), 'artifact_out_of_stock'), 409);
    }
    if ($artifactManager->exists($id, $orderId)) {
        return $app->json($artifactManager->error($request->getClientIp(), 'provisiona_already_exists'), 409);
    }
    $metadata = $artifactManager->provision($id, $orderId);
    if (empty($metadata)) {
        $artifactManager->log_write($id, 'provision failed to order: ' . $orderId);
        return $app->json($artifactManager->error($request->getClientIp(), 'artifact_provision_failed'), 422);
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
$app->put('/kaya-marketplace/artifact/{id}/order/{orderId}/{action}', function (Request $request, $id, $orderId, $action) use ($app, $artifactManager) {
    $artifact = $artifactManager->get($id);
    if (!$artifact instanceof Artifact) {
        return $app->json($artifactManager->error($request->getClientIp(), 'artifact_not_found'), 404);
    }
    if (!in_array($action, $artifactManager->actions())) {
        $artifactManager->log_write($id, 'unsupported action to order: ' . $orderId);
        return $app->json($artifactManager->error(
            $request->getClientIp(),
            'artifact_unsupported_action',
            'unsupported action ' . $action . ' to order ' . $orderId . ', available actions are: ' . join(', ', $artifactManager->actions())
        ), 422);
    }
    if (!$artifactManager->exists($id, $orderId)) {
        return $app->json($artifactManager->error($request->getClientIp(), 'artifact_provision_not_found'), 404);
    }
    $data = $artifactManager->execute($id, $orderId, $action);
    return $app->json(
        [
            'artifact' => $artifact,
            'metadata' => $data
        ]
    );
});
$app->run();