<?php

namespace Nerd4ever\Kaya\Seed\Model;

use stdClass;

interface WebhookManagerInterface
{
    /**
     * Adiciona um novo artefato à lista de artefatos disponíveis.
     *
     * @param Artifact $artifact
     *
     * @return bool
     */
    public function add(Artifact $artifact): bool;

    /**
     * Retorna todos os artefatos disponíveis.
     *
     * @return array
     */
    public function all(): array;

    /**
     * Retorna um artefato específico com base em seu ID
     *
     * @param $id
     *
     * @return Artifact|null
     */
    public function get($id): ?Artifact;

    /**
     * Recupera os logs de um artefato específico
     *
     * @param $id
     *
     * @return array
     */
    public function log($id): array;

    /**
     * Verifica a quantidade de um artefato específico que está disponível em estoque
     *
     * @param $id
     *
     * @return int
     */
    public function stock($id): int;

    /**
     * Usado quando o kaya-marketplace realiza uma venda de um produto associado ao artefato, criando uma instância
     * interna do artefato com base em seu ID e executando as demais funções para disponibilizá-lo. É necessário passar
     * o ID do item do pedido, pois ele será associado a esse provisionamento.
     *
     * @param $id
     * @param $orderId
     *
     * @return stdClass|null
     */
    public function provision($id, $orderId): ?stdClass;

    /**
     * Verifica se existe (foi provisionado) um artefato para um determinado ID de item do pedido.
     *
     * @param $id
     * @param $orderId
     *
     * @return bool
     */
    public function exists($id, $orderId): bool;

    /**
     * Recupera informações de metadados do artefato, que podem variar de artefato para artefato. Pode incluir desde
     * apenas informações de estado e criação até configurações e credenciais internas.
     *
     * @param $id
     * @param $orderId
     *
     * @return array
     */
    public function metadata($id, $orderId): array;

    /**
     * Usado pelo kaya-marketplace para executar uma ação que irá alterar o estado de um artefato associado a um ID de
     * item do pedido.
     *
     * @param $id
     * @param $orderId
     * @param $action
     *
     * @return array
     */
    public function execute($id, $orderId, $action): array;

    /**
     * Método usado para formatar a saída de erro. Recebe como parâmetros o endereço, o erro e a descrição do erro.
     *
     * @param $address
     * @param $error
     * @param $errorDescription
     *
     * @return stdClass
     */
    public function error($address, $error, $errorDescription): stdClass;

}