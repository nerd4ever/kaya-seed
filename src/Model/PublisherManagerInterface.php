<?php

namespace Nerd4ever\Kaya\Seed\Model;

interface PublisherManagerInterface
{

    /**
     * responsável por enviar uma requisição ao Kaya-Marketplace informando que o microserviço está ativo, e então
     * aguardar a consulta do Marketplace para ver quais são os artifacts disponíveis nesse microserviço. Esse método
     * retorna um booleano indicando se a instalação foi bem sucedida.
     *
     * @param string $name
     *
     * @return bool
     */
    public function install(string $name): bool;

    /**
     * Responsável por informar ao Kaya-Marketplace que houve uma alteração de estado em um artifact associado a um
     * order item (do Kaya-Marketplace). Esse método recebe como parâmetros o id do pedido e o novo estado do artifact,
     * e retorna um booleano indicando se a operação foi bem sucedida.
     *
     * @param int $orderId
     * @param string $state
     *
     * @return bool
     */
    public function state(int $orderId, string $state): bool;

    /**
     * Responsável por retornar uma lista de todas as ações que o Kaya-Marketplace pode executar para os artifacts
     * desse microserviço, como iniciar, parar, criar, terminar, entre outras.
     *
     * @return array
     */
    public function actions(): array;

    /**
     * Responsável por retornar uma lista com todos os estados disponíveis para um artifact nesse microserviço.
     *
     * @return array
     */
    public function states(): array;
}