# QR Control

Sistema privado para criar, cadastrar, editar, pesquisar e exportar QR Codes dinâmicos. O QR impresso contém apenas a URL permanente `APP_URL/q/{code}`; o destino final fica no MySQL e pode mudar sem reimpressão.

## Stack e estrutura

- `frontend/`: SPA React + TypeScript compilada pelo Vite.
- `backend/public/`: ponto de entrada HTTP e arquivos estáticos de produção.
- `backend/src/`: autenticação, serviços, validação e acesso PDO ao MySQL.
- `database/migrations/`: estrutura versionada e índices do banco.
- `database/legacy-data.json`: exportação compacta dos 2 lotes e 55 QR Codes existentes.
- `runtime/`: configuração exclusivamente local; não é exigida em produção.

O código não depende de Hostinger. Funciona em hospedagem compartilhada com PHP/MySQL, Apache, Nginx, contêiner ou servidor próprio.

## Requisitos

- Node.js 20 ou superior para compilar o frontend;
- PHP 8.2 ou superior com `pdo_mysql`, `gd`, `mbstring`, `openssl` e `zip`;
- Composer 2;
- MySQL 8.0 ou superior;
- HTTPS em produção.

## Configuração

Copie `.env.example` para `.env` e ajuste:

| Variável | Uso |
| --- | --- |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | conexão MySQL |
| `APP_URL` | origem pública gravada nas imagens de QR Code |
| `ADMIN_USERNAME` | usuário administrativo |
| `ADMIN_PASSWORD_HASH` | hash criado por `password_hash`, nunca a senha aberta |
| `SESSION_SECURE` | `true` quando houver HTTPS |
| `TRUST_PROXY_HEADERS` | habilite apenas atrás de proxy confiável |

Instalação e build:

```bash
npm ci
npm run build
cd backend && composer install --no-dev --optimize-autoloader && cd ..
php backend/bin/migrate.php
```

Na primeira migração deste projeto, execute uma única vez:

```bash
php backend/bin/import-legacy.php
```

O importador recusa bancos que já contenham lotes. Ele preserva os intervalos `0016–0020` e `0021–0070` e mantém `0071` como próximo número.

## Desenvolvimento local

Com MySQL configurado no `.env`, execute em terminais separados:

```bash
npm run backend:serve
npm run dev
```

Abra `http://127.0.0.1:3000`. O Vite encaminha `/api` e `/q` ao PHP em `127.0.0.1:8080`.

## Publicação genérica

1. Execute `npm run build` e `composer install --no-dev --optimize-autoloader`.
2. Configure o document root para `backend/public`.
3. Mantenha `.env`, `backend/src`, `backend/vendor` e `database` fora da área pública.
4. Aplique `php backend/bin/migrate.php` antes de publicar a nova versão.
5. Configure HTTPS, backups automáticos do MySQL e limites adequados de tempo/memória para exportações grandes.

Em Apache, o `.htaccess` já encaminha rotas ao PHP. Em Nginx, use `try_files $uri /index.php?$query_string`. O `Dockerfile` e o `docker-compose.yml` são opcionais e servem como ambiente reproduzível, não como dependência de hospedagem.

## Segurança e concorrência

- consultas PDO preparadas e validação de todas as entradas;
- sessão HTTP-only/SameSite, regeneração após login e CSRF em mutações;
- limite de login persistente no MySQL, válido entre processos PHP;
- CSP e cabeçalhos contra framing e MIME sniffing;
- destinos restritos a HTTP/HTTPS;
- exclusões atômicas por chave estrangeira;
- reserva de intervalos por transação e `SELECT ... FOR UPDATE`, sem colisão e sem reaproveitar números apagados;
- ZIP transmitido progressivamente para não manter o arquivo completo em memória.

## Funcionalidades preservadas

- login/logout e retorno à página solicitada;
- painel responsivo, paginação e pesquisa com sugestões;
- lotes sequenciais de 1 a 500 códigos;
- expansão, seleção múltipla, ZIP individual/combinado e exclusão confirmada;
- páginas de lote e QR Code;
- edição de destino e ativação/desativação;
- download e visualização SVG/PNG;
- primeiro cadastro protegido para QR sem destino;
- redirecionamento público direto depois do cadastro;
- páginas públicas de erro para código inválido, inexistente ou inativo.

## Verificação

```bash
npm run lint
npm test
npm run build
cd backend && composer test
```

Antes de trocar o domínio definitivo, configure `APP_URL` corretamente: QR Codes já impressos continuam contendo a origem usada no momento do download.
