# Diagrama ER — GADSAN

Todas as PKs são IDs internos BIGINT UNSIGNED. Relações sem dados confirmados admitem FK NULL. Exclusões de registros referenciados são restritas. Os campos abaixo destacam chaves e atributos do domínio; tipos, timestamps, índices e restrições completos estão em [schema.sql](../database/schema.sql).

```mermaid
erDiagram
    COLABORADORES ||--o{ ALOCACOES_COLABORADOR : possui
    CARGOS o|--o{ ALOCACOES_COLABORADOR : define
    PROJETOS o|--o{ ALOCACOES_COLABORADOR : participa
    LOTACOES o|--o{ ALOCACOES_COLABORADOR : localiza
    INSTITUICOES o|--o{ ALOCACOES_COLABORADOR : pertence
    VINCULOS o|--o{ ALOCACOES_COLABORADOR : classifica
    NATUREZAS_CONTRATACAO o|--o{ ALOCACOES_COLABORADOR : caracteriza
    TURNOS o|--o{ ALOCACOES_COLABORADOR : organiza
    MUNICIPIOS o|--o{ LOTACOES : situa
    COLABORADORES ||--o{ COLABORADOR_FORMACAO : possui
    FORMACOES ||--o{ COLABORADOR_FORMACAO : identifica
    COLABORADORES ||--o{ COLABORADOR_ESCOLARIDADE : possui
    ESCOLARIDADES ||--o{ COLABORADOR_ESCOLARIDADE : identifica
    IMPORTACOES ||--o{ IMPORTACAO_ORIGENS : preserva
    COLABORADORES o|--o{ IMPORTACAO_ORIGENS : provem
    ALOCACOES_COLABORADOR o|--o{ IMPORTACAO_ORIGENS : provem
    IMPORTACOES ||--o{ IMPORTACAO_INCONSISTENCIAS : registra
    IMPORTACAO_ORIGENS o|--o{ IMPORTACAO_INCONSISTENCIAS : apresenta

    COLABORADORES {
        bigint id PK
        varchar nome
        varchar cpf
        boolean cpf_validado
        varchar cpf_unico UK "gerado somente para CPF valido"
        varchar rg
        date data_nascimento
        varchar email
        varchar telefone
        text endereco
        varchar tipo_sanguineo
        boolean ativo
    }
    ALOCACOES_COLABORADOR {
        bigint id PK
        bigint colaborador_id FK
        bigint cargo_id FK
        bigint projeto_id FK
        bigint lotacao_id FK
        bigint instituicao_id FK
        bigint vinculo_id FK
        bigint natureza_contratacao_id FK
        bigint turno_id FK
        varchar matricula
        smallint carga_horaria_semanal
        date data_admissao
        date data_inicio
        date data_fim
        boolean ativo
    }
    CARGOS {
        bigint id PK
        varchar nome UK
        boolean ativo
    }
    INSTITUICOES {
        bigint id PK
        varchar nome UK
        boolean ativo
    }
    PROJETOS {
        bigint id PK
        varchar nome UK
        boolean ativo
    }
    VINCULOS {
        bigint id PK
        varchar nome UK
        boolean ativo
    }
    NATUREZAS_CONTRATACAO {
        bigint id PK
        varchar nome UK
        boolean ativo
    }
    TURNOS {
        bigint id PK
        varchar nome UK
        boolean ativo
    }
    ESCOLARIDADES {
        bigint id PK
        varchar nome UK
        boolean ativo
    }
    FORMACOES {
        bigint id PK
        varchar nome UK
        boolean ativo
    }
    MUNICIPIOS {
        bigint id PK
        varchar nome "UNIQUE nome e uf"
        char uf
        varchar codigo_ibge UK
        boolean ativo
    }
    LOTACOES {
        bigint id PK
        varchar nome UK
        varchar tipo
        bigint municipio_id FK
        boolean ativo
    }
    COLABORADOR_FORMACAO {
        bigint id PK
        bigint colaborador_id FK "UNIQUE com formacao_id"
        bigint formacao_id FK
    }
    COLABORADOR_ESCOLARIDADE {
        bigint id PK
        bigint colaborador_id FK "UNIQUE com nivel e situacao"
        bigint escolaridade_id FK
        varchar situacao
    }
    IMPORTACOES {
        bigint id PK
        varchar nome_arquivo
        char sha256 "UNIQUE com versao_importador"
        varchar versao_importador
        timestamp data_importacao
        int total_linhas
        int total_importados
        int total_atualizados
        int total_ignorados
        int total_inconsistencias
        varchar status
        timestamp created_at
    }
    IMPORTACAO_ORIGENS {
        bigint id PK
        bigint importacao_id FK "UNIQUE com aba e linha"
        bigint colaborador_id FK
        bigint alocacao_id FK
        varchar arquivo
        varchar aba
        int linha
        json dados_originais
        varchar resultado
        timestamp data_importacao
        timestamp created_at
    }
    IMPORTACAO_INCONSISTENCIAS {
        bigint id PK
        bigint importacao_id FK
        bigint origem_id FK
        varchar arquivo
        varchar aba
        int linha
        varchar campo
        text valor_original
        varchar tipo_inconsistencia
        text descricao
        boolean resolvido
        timestamp created_at
    }
```

`alocacoes_colaborador` conserva a combinação profissional concreta e permite mais de uma alocação por pessoa. Datas de início/fim não são inferidas da admissão. Os dois relacionamentos educacionais representam nível/situação e formação separadamente. Origem sem colaborador preserva linhas ainda não aptas a formar um cadastro.
