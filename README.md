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

## Credencial inicial

- CPF: `11144477735`
- Senha: `admin123`

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
