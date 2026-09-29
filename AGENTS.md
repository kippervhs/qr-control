# QR Control

- Frontend: Vite + React + TypeScript em `frontend/`.
- Backend: PHP 8.2+ em `backend/`; toda persistência usa PDO/MySQL.
- Banco: migrações SQL versionadas em `database/migrations/`.
- Preserve os contratos HTTP, o fluxo público `/q/{code}` e todas as validações de segurança.
- Configuração específica de ambiente pertence ao `.env`; não codifique domínio, provedor ou credenciais na aplicação.
- Antes de entregar, execute `npm run lint`, `npm test`, `npm run build` e `composer test` em `backend/`.
