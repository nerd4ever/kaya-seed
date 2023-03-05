<?php

use Nerd4ever\Kaya\Seed\Model\TokenManagerInterface;
use Nerd4ever\Kaya\Seed\Model\PublishManager;
use Nerd4ever\Kaya\Seed\Model\WebhookManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Silex\Application;

require_once __DIR__ . '/../sample/index.php';

class SeedTest extends TestCase
{
    protected Application $app;
    protected MockObject $mock;
    protected ?string $accessToken = null;
    protected TokenManagerInterface $tokenManager;
    protected PublishManager $publishManager;
    protected WebhookManagerInterface $webhookManager;

    protected function setUp(): void
    {
        global $app;
        global $tokenManager;
        global $publisherManager;
        global $webhookManager;

        parent::setUp();
        $this->app = $app;
        $this->tokenManager = $tokenManager;
        $this->publishManager = $publisherManager;
        $this->webhookManager = $webhookManager;

        $webhookManager->clear();
    }

    private function match_token(stdClass $data): bool
    {
        return
            isset($data->id_token) && is_string($data->id_token) &&
            isset($data->token_type) && is_string($data->token_type) && $data->token_type == 'bearer' &&
            isset($data->refresh_token) && is_string($data->refresh_token);
    }

    private function match_error(stdClass $data, string $expected): bool
    {
        if (!isset($data->error) || !is_string($data->error)) return false;
        if (isset($data->errorDescription) && !is_string($data->errorDescription)) return false;
        if (!isset($data->address) || !filter_var($data->address, FILTER_VALIDATE_IP)) return false;
        if (!isset($data->date) || DateTime::createFromFormat(DateTimeInterface::ATOM, $data->date) === false) return false;
        return $data->error == $expected;
    }

    private function match_metadata(stdClass $data): bool
    {
        return
            isset($data->id) && is_string($data->id) &&
            isset($data->createdAt) && is_string($data->createdAt) && DateTime::createFromFormat(DateTimeInterface::ATOM, $data->createdAt) !== false &&
            isset($data->modifiedAt) && is_string($data->modifiedAt) && DateTime::createFromFormat(DateTimeInterface::ATOM, $data->modifiedAt) !== false &&
            isset($data->state) && is_string($data->state) && in_array($data->state, $this->publishManager->states()) &&
            isset($data->action) && is_string($data->action) && in_array($data->action, $this->publishManager->actions());
    }

    private function match_artifact(stdClass $data): bool
    {
        return
            isset($data->id) && is_string($data->id) &&
            isset($data->displayName) && is_string($data->displayName) &&
            isset($data->shortname) && is_string($data->shortname) &&
            isset($data->enabled) && is_bool($data->enabled);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testDiscoveryEndpoint(): void
    {
        $request = Request::create('/kaya-marketplace/discovery', 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'authorization_required'));

        $request = Request::create('/kaya-marketplace/discovery', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent());
        $this->assertTrue(property_exists($data, 'artifacts'));
        $this->assertTrue(property_exists($data, 'actions'));
        $this->assertTrue(property_exists($data, 'states'));

        $this->assertTrue(isset($data->states) && is_array($data->states) && count(array_diff($data->states, $this->publishManager->states())) == 0);
        $this->assertTrue(isset($data->actions) && is_array($data->actions) && count(array_diff($data->actions, $this->publishManager->actions())) == 0);
        $this->assertTrue(isset($data->artifacts) && is_array($data->artifacts));
        if (!isset($data->artifacts) || !is_array($data->artifacts)) $this->fail();
        foreach ($data->artifacts as $item) {
            $this->assertTrue(isset($item->artifact) && is_object($item->artifact));
            $this->assertTrue(isset($item->stock) && is_int($item->stock));

            if (!isset($item->artifact) || !is_object($item->artifact)) continue;
            $this->assertTrue($this->match_artifact($item->artifact));
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testArtifactEndpoint(): void
    {
        $id = 1;
        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s",
            $id
        ), 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'authorization_required'));

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s",
            $id
        ), 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'artifact_not_found'));

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s",
            $this->webhookManager->all()[0]->getId()
        ), 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent());

        $this->assertTrue(isset($data->artifact) && is_object($data->artifact));
        $this->assertTrue(isset($data->stock) && is_int($data->stock));
        $this->assertTrue($this->match_artifact($data->artifact));
    }

    /**
     * @depends testArtifactOrderPostEndpoint
     * @return void
     * @throws Exception
     */
    public function testArtifactOrderEndpoint(): void
    {
        $id = 1;
        $orderId = 1;
        $provisionOrderId = 1000;
        $artifact = $this->webhookManager->all()[0];

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/order/%d"
            , $id
            , $orderId
        ), 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'authorization_required'));

        $this->assertTrue($this->webhookManager->provision($artifact->getId(), $provisionOrderId) != null);
        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/order/%d"
            , 'any-id'
            , $orderId
        ), 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'artifact_not_found'));

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/order/%d"
            , $artifact->getId()
            , $orderId
        ), 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'artifact_provision_not_found'));

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/order/%d"
            , $artifact->getId()
            , $provisionOrderId
        ), 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent());

        $this->assertTrue(isset($data->artifact) && is_object($data->artifact));
        $this->assertTrue(isset($data->metadata) && is_object($data->metadata));
        $this->assertTrue($this->match_artifact($data->artifact));
        $this->assertTrue($this->match_metadata($data->metadata));

    }

    public function testArtifactStockEndpoint()
    {
        $artifact = $this->webhookManager->all()[0];

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/stock",
            $artifact->getId()
        ), 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'authorization_required'));

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/stock",
            'any-id'
        ), 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'artifact_not_found'));

        $stock = $this->webhookManager->stock($artifact->getId());
        $this->assertTrue($this->webhookManager->provision($artifact->getId(), 1) != null);

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/stock",
            $artifact->getId()
        ), 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent());

        $this->assertTrue(isset($data->artifact) && is_object($data->artifact));
        $this->assertTrue(isset($data->stock) && is_int($data->stock));
        $this->assertTrue($this->match_artifact($data->artifact));
        $this->assertEquals($stock - 1, $data->stock);
    }

    public function testArtifactLogEndpoint()
    {
        $id = 1;
        $artifact = $this->webhookManager->all()[0];
        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/log"
            , $id
        ), 'GET');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'authorization_required'));

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/log"
            , $id
        ), 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'artifact_not_found'));

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/log"
            , $artifact->getId()
        ), 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue(isset($data->log) && is_array($data->log));

    }

    public function testArtifactOrderPostEndpoint()
    {
        $id = 1;
        $orderId = 9999;
        $artifact = $this->webhookManager->all()[0];

        $request = Request::create(
            sprintf("/kaya-marketplace/artifact/%s/order/%d",
                $id,
                $orderId
            ), 'POST');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'authorization_required'));

        $request = Request::create(
            sprintf("/kaya-marketplace/artifact/%s/order/%d",
                $id,
                $orderId
            ), 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'artifact_not_found'));

        $this->assertTrue($this->webhookManager->provision($artifact->getId(), $orderId) != null);
        $request = Request::create(
            sprintf("/kaya-marketplace/artifact/%s/order/%d",
                $this->webhookManager->all()[0]->getId(),
                $orderId
            ), 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'provisiona_already_exists'));

        $stock = $this->webhookManager->stock($artifact->getId());
        for ($i = 0; $i != $stock; $i++) {
            $request = Request::create(
                sprintf("/kaya-marketplace/artifact/%s/order/%d",
                    $artifact->getId(),
                    $i + 1000
                ), 'POST');
            $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $data = json_decode($response->getContent());
            $this->assertTrue(isset($data->artifact) && is_object($data->artifact));
            $this->assertTrue(isset($data->metadata) && is_object($data->metadata));

            $this->assertTrue($this->match_artifact($data->artifact));
            $this->assertTrue($this->match_metadata($data->metadata));
        }

        $request = Request::create(
            sprintf("/kaya-marketplace/artifact/%s/order/%d",
                $this->webhookManager->all()[0]->getId(),
                $orderId
            ), 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'artifact_out_of_stock'));
    }

    /**
     * @depends testArtifactOrderPostEndpoint
     * @return void
     * @throws Exception
     */
    public function testArtifactOrderPutEndpoint(): void
    {
        $id = 1;
        $orderId = 1;
        $provisionOrderId = 9999;
        $action = 'some_action';
        $artifact = $this->webhookManager->all()[0];
        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/order/%d/%s",
            $id,
            $orderId,
            $action,
        ), 'PUT');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'authorization_required'));

        $this->assertTrue($this->webhookManager->provision($artifact->getId(), $provisionOrderId) != null);
        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/order/%d/%s",
            $id,
            $orderId,
            $action,
        ), 'PUT');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'artifact_not_found'));

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/order/%d/%s",
            $artifact->getId(),
            $orderId,
            $action,
        ), 'PUT');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'artifact_provision_not_found'));

        $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/order/%d/%s",
            $artifact->getId(),
            $provisionOrderId,
            $action,
        ), 'PUT');
        $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
        $response = $this->app->handle($request);
        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent());
        $this->assertTrue($this->match_error($data, 'artifact_unsupported_action'));

        $actions = $this->publishManager->actions();
        foreach ($actions as $action) {
            $request = Request::create(sprintf("/kaya-marketplace/artifact/%s/order/%d/%s",
                $artifact->getId(),
                $provisionOrderId,
                $action,
            ), 'PUT');
            $request->headers->set('Authorization', 'Bearer ' . $this->tokenManager->access_token());
            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $data = json_decode($response->getContent());

            $this->assertTrue(isset($data->artifact) && is_object($data->artifact));
            $this->assertTrue(isset($data->metadata) && is_object($data->metadata));
            $this->assertTrue($this->match_artifact($data->artifact));
            $this->assertTrue($this->match_metadata($data->metadata));
        }
    }

    public function testToken(): void
    {
        $cred = $this->tokenManager->refresh();
        $this->assertTrue($cred != null);
        $this->assertTrue($this->match_token($cred));

        $cred = $this->tokenManager->refresh($cred->refresh_token);
        $this->assertTrue($cred != null);
        $this->assertTrue($this->match_token($cred));
    }
}