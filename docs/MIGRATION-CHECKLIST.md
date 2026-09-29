# Revisão da migração Next/PostgreSQL → Vite/PHP/MySQL

| Comportamento original | Implementação atual | Estado |
| --- | --- | --- |
| Sessão administrativa de 12 horas | sessão PHP protegida + CSRF | preservado |
| Limite de 5 falhas por 15 minutos | tabela `login_attempts` | preservado e distribuído |
| Lotes contínuos sem colisão | sequência transacional bloqueada | preservado |
| Números apagados não retornam | `next_code` nunca retrocede | preservado |
| Paginação de 10 lotes | `LIMIT/OFFSET` e contagem | preservado |
| Pesquisa por parte do número | sugestão limitada a 6, exato primeiro | preservado |
| ZIP de um ou até 20 lotes | geração progressiva PHP | preservado |
| PNG 1600px e SVG 1200px, correção H | `endroid/qr-code` | preservado |
| Exclusão de lote e QR Codes | transação + `ON DELETE CASCADE` | preservado |
| Primeiro cadastro | atualização atômica apenas se destino nulo | preservado |
| Redirect 307 sem cache | rota pública PHP `/q/{code}` | preservado |
| Layout preto/laranja Arial responsivo | SPA Vite com os mesmos estados e controles | preservado |

Dados inventariados em 16/09/2026: 2 lotes, 55 QR Codes ativos sem destino, intervalos 0016–0020 e 0021–0070. A exportação está em `database/legacy-data.json`; o PostgreSQL original não é removido pelo processo.
