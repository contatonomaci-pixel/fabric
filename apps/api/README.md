# FactoryControl PRO — API

Laravel 13, PHP 8.3+, Sanctum por sessão e REST `/api/v1`. Dependências resolvidas para PHP 8.3 no Composer; PHPUnit 11. Sem emissão de tokens de API.

A configuração, criação da conta fictícia, worker, testes e execução no Windows/Unix estão em [Executar localmente](../../docs/17-executar-localmente.md). Contrato implementado: [OpenAPI](../../docs/api/openapi.json). Não executar o DemoSeeder fora de local/testing.

`composer test` usa SQLite isolado por padrão; a execução MySQL requer `TEST_DB_CONNECTION=mysql` e banco separado terminado em `_test`. A configuração nunca deve apontar testes ao banco de desenvolvimento/produção.
