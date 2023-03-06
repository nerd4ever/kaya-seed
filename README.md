FORMAT: 1A

# Kaya-Seed

O Kaya-Seed é um microserviço escrito em PHP que tem como objetivo fornecer uma interface para que microserviços se
comuniquem com o Kaya-Marketplace. Ele é composto por três interfaces: PublisherManager, TokenManager e WebhookManager.

## Descrição

O Kaya-Seed é um microserviço que fornece uma interface para que os microserviços se comuniquem com o Kaya-Marketplace.
Ele permite que os microserviços informem ao Kaya-Marketplace sobre seus recursos disponíveis e seus status, bem como
permitir que o Kaya-Marketplace execute ações em um recurso específico. O Kaya-Seed também lida com autenticação e
autorização, garantindo que apenas usuários autorizados possam acessar os recursos do microserviço.

## Introdução

Kaya-Seed é uma biblioteca em PHP que possibilita a integração de microserviços com o Kaya-Marketplace. Através dela, é
possível realizar a comunicação com o Kaya-Marketplace, publicar seus serviços, autenticar e validar tokens de acesso e
receber notificações via webhook.

Para utilizar o Kaya-Seed, é necessário fazer a instalação via composer:

```shell
composer require nerd4ever/kaya-seed
```

## Webhook

### Descoberta dos Endpoints

Este endpoint é usado pelo Kaya Marketplace para descobrir todos os endpoints disponíveis no microserviço.

# GET /kaya-marketplace/discovery

### Informações de um Artifact

Este endpoint retorna informações sobre um artifact específico.

# GET /kaya-marketplace/artifact/{id}

### Detalhes do Pedido

Este endpoint retorna detalhes sobre um pedido de um artifact específico.

# GET /kaya-marketplace/artifact/{id}/order/{orderId}

### Verificar Estoque

Este endpoint retorna a quantidade de um artifact disponível em estoque.

# GET /kaya-marketplace/artifact/{id}/stock

### Ver Logs

Este endpoint retorna logs do artifact.

# GET /kaya-marketplace/artifact/{id}/log

### Criar Pedido

Este endpoint é usado para criar um pedido de um artifact.

# POST /kaya-marketplace/artifact/{id}/order/{orderId}

### Executar Ação

Este endpoint é usado para executar uma ação em um artifact.

# PUT /kaya-marketplace/artifact/{id}/order/{orderId}/{action}

Para cada endpoint acima, abaixo está a tabela de possíveis códigos de erro:

| Código de erro |Descrição|
|----------------|-------------------------|
| 200            |Sucesso|
| 400            |Requisição inválida|
| 401            |Autenticação inválida|
| 403            |Acesso negado|
| 404            |Recurso não encontrado|
| 409            |Recurso não encontrado|
| 422            |Recurso não encontrado|
| 500            |Erro interno do servidor|

Para os parâmetros, temos:

|Parâmetro|Descrição|
|---------|---------|
|id|Id do artifact|
|orderId|Id do pedido|
|action|Ação a ser executada no artifact|


