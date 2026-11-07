# ERP Financeiro – ATC Zanardi

## Instalação (primeira vez)
1. No hPanel, crie um banco MySQL novo para o ERP.
2. Importe `database/schema.sql` NESSE banco novo (nunca no banco do app).
3. Copie `.env.example` para `.env` e preencha as credenciais dos dois bancos e o mapeamento da tabela de usuários do app.
4. Envie a pasta inteira para `public_html/erp` e aponte o subdomínio `erp` para `public_html/erp/public`.

## Rodar localmente
    php -S localhost:8000 -t public

## Estrutura
- `public/`   única pasta acessível pela web
- `src/`      código PHP (Auth, conexões, AppReader somente leitura)
- `database/` schema do banco do ERP
- `storage/`  logs
