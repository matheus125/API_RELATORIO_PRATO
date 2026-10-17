# Portal de Funcionários

Projeto enxuto baseado na mesma estrutura do sistema original, mantendo:

- Slim 2
- RainTPL
- Hcode classes
- Login
- Dashboard
- Funcionários
- ACL por perfil
- Logs de acesso e auditoria

## Estrutura principal

- `public/index.php`: bootstrap do sistema
- `app/routes/admin.php`: login, dashboard e segurança
- `app/routes/admin-funcionarios.php`: CRUD de funcionários
- `app/views/admin/*`: telas do painel
- `database/sql/portal_funcionarios.sql`: script mínimo de banco

## Módulos mantidos

- Dashboard
- Funcionários
- Permissões
- Usuários e status
- Acessos negados
- Auditoria

## Primeiro acesso

Não há credencial administrativa fixa no código ou na documentação. Crie o
primeiro usuário administrador diretamente no banco de dados com uma senha
gerada por `password_hash()` e troque a senha imediatamente após o primeiro
acesso. Nunca publique credenciais reais no repositório.

## Banco de dados

Importe:

- `database/sql/portal_funcionarios.sql`

Depois ajuste as credenciais de conexão conforme seu ambiente.

## Observações

Este projeto foi derivado do original, mas removendo módulos de:

- clientes
- dependentes
- vendas
- relatórios operacionais
- notificações e backup do dashboard antigo
