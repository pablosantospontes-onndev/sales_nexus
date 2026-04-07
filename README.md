# Vigg Nexus CRM

CRM web inicial para operacao de vendas Telecom em PHP 8.2 + MySQL/MariaDB, pensado para rodar direto no XAMPP.

## O que ja foi entregue

- Login com perfis `ADMINISTRADOR`, `BACKOFFICE` e `BACKOFFICE SUPERVISOR`
- Importacao de `.zip` contendo `analitico_vendas_fixa.csv`
- Filtro de importacao:
  - `Status do Servico = Aprovado`
  - `Status da Venda = Finalizada`
  - `Servico = BANDA LARGA`
- Deduplicacao por `Codigo da Venda`
- Fila de auditoria com status inicial `PENDENTE INPUT`
- Tela para pegar a venda, completar dados e salvar na `sales_nexus`
- Suporte a um ou mais produtos por venda com base na tabela `catalog_products`
- Modulo de hierarquia exclusivo do perfil `ADMINISTRADOR`
- Cruzamento da hierarquia pelo CPF do vendedor para pre-preencher dados da venda

## Usuarios iniciais

- `52998224725` / `admin123`
- `11144477735` / `backoffice123`
- `12345678909` / `supervisor123`

## Como preparar

1. Garanta que o MySQL do XAMPP esteja ativo e que o banco `vigg_nexus` exista.
2. Rode o setup:

```powershell
php .\scripts\setup_database.php
```

3. Abra no navegador:

```text
http://localhost/sales%20nexus/
```

## Observacao

Para suportar mais de um produto na mesma venda, a `sales_nexus` grava uma linha por produto selecionado e mantem o vinculo via `IMPORT_QUEUE_ID`.

## Modulo de hierarquia

- Rotas e tela web: `Hierarquia`
- Tabelas: `hierarchy_bases`, `hierarchy_base_groups`, `seller_hierarchies`
- Chave de cruzamento com a venda: `CPF do vendedor`
