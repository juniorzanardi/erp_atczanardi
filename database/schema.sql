-- ERP Financeiro ATC Zanardi – schema inicial (MySQL 8, InnoDB)
-- Rodar SOMENTE no banco próprio do ERP. Nunca no banco do app ATC Zanardi.
-- empresa_id e app_user_id são IDs vindos do app (bancos separados: sem chave estrangeira).
-- Matriz e filiais: cada estabelecimento é uma empresa_id; o grupo une empresas do mesmo CNPJ raiz.

SET NAMES utf8mb4;

-- Grupo econômico (matriz + filiais). Empresa sem filial = grupo com uma empresa só.
CREATE TABLE fin_grupos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cnpj_raiz   CHAR(8)      NOT NULL COMMENT '8 primeiros dígitos do CNPJ',
  nome        VARCHAR(150) NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_grupo_raiz (cnpj_raiz)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fin_grupo_empresas (
  empresa_id  INT UNSIGNED NOT NULL PRIMARY KEY COMMENT 'empresa do app existente',
  grupo_id    INT UNSIGNED NOT NULL,
  tipo        ENUM('matriz','filial') NOT NULL,
  apelido     VARCHAR(60)  NULL COMMENT 'ex.: Matriz, Filial Centro',
  KEY idx_ge_grupo (grupo_id),
  CONSTRAINT fk_ge_grupo FOREIGN KEY (grupo_id) REFERENCES fin_grupos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plano de contas compartilhado pelo grupo (garante consolidação pelo mesmo código).
CREATE TABLE fin_plano_contas (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  grupo_id        INT UNSIGNED NULL COMMENT 'NULL = plano modelo ATC Zanardi',
  conta_pai_id    INT UNSIGNED NULL,
  codigo          VARCHAR(30)  NOT NULL,
  descricao       VARCHAR(150) NOT NULL,
  natureza        ENUM('receita','despesa') NOT NULL,
  analitica       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = recebe lançamentos',
  ativo           TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_plano_codigo (grupo_id, codigo),
  KEY idx_plano_pai (conta_pai_id),
  CONSTRAINT fk_plano_pai   FOREIGN KEY (conta_pai_id) REFERENCES fin_plano_contas(id),
  CONSTRAINT fk_plano_grupo FOREIGN KEY (grupo_id) REFERENCES fin_grupos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contas financeiras pertencem a cada estabelecimento.
CREATE TABLE fin_contas_financeiras (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empresa_id          INT UNSIGNED NOT NULL,
  nome                VARCHAR(100) NOT NULL,
  tipo                ENUM('caixa','banco') NOT NULL,
  banco_codigo        VARCHAR(5)   NULL,
  agencia             VARCHAR(10)  NULL,
  conta               VARCHAR(20)  NULL,
  saldo_inicial       DECIMAL(15,2) NOT NULL DEFAULT 0,
  data_saldo_inicial  DATE NOT NULL,
  ativo               TINYINT(1) NOT NULL DEFAULT 1,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cf_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clientes/fornecedores compartilhados pelo grupo. Pode ser alimentado lendo o cadastro do app (só leitura).
CREATE TABLE fin_pessoas (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  grupo_id    INT UNSIGNED NOT NULL,
  tipo        ENUM('cliente','fornecedor','ambos') NOT NULL,
  nome        VARCHAR(150) NOT NULL,
  documento   VARCHAR(14)  NULL COMMENT 'CPF/CNPJ só números',
  email       VARCHAR(150) NULL,
  telefone    VARCHAR(20)  NULL,
  ativo       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pessoas_grupo (grupo_id),
  KEY idx_pessoas_doc (grupo_id, documento),
  CONSTRAINT fk_pessoas_grupo FOREIGN KEY (grupo_id) REFERENCES fin_grupos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Títulos e baixas sempre de UM estabelecimento.
CREATE TABLE fin_titulos (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empresa_id        INT UNSIGNED NOT NULL,
  tipo              ENUM('pagar','receber') NOT NULL,
  pessoa_id         INT UNSIGNED NULL,
  plano_conta_id    INT UNSIGNED NOT NULL,
  descricao         VARCHAR(200) NOT NULL,
  documento         VARCHAR(60)  NULL,
  valor             DECIMAL(15,2) NOT NULL,
  data_competencia  DATE NOT NULL,
  data_vencimento   DATE NOT NULL,
  status            ENUM('aberto','parcial','pago','cancelado') NOT NULL DEFAULT 'aberto',
  grupo_id          CHAR(36) NULL COMMENT 'parcelamento/recorrência (não confundir com fin_grupos)',
  parcela_num       SMALLINT UNSIGNED NULL,
  parcela_total     SMALLINT UNSIGNED NULL,
  origem            ENUM('manual','nota','guia') NOT NULL DEFAULT 'manual',
  origem_ref        VARCHAR(60) NULL COMMENT 'id da nota/guia no app',
  observacao        TEXT NULL,
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tit_venc (empresa_id, tipo, status, data_vencimento),
  KEY idx_tit_comp (empresa_id, data_competencia),
  KEY idx_tit_serie (grupo_id),
  CONSTRAINT fk_tit_pessoa FOREIGN KEY (pessoa_id) REFERENCES fin_pessoas(id),
  CONSTRAINT fk_tit_plano  FOREIGN KEY (plano_conta_id) REFERENCES fin_plano_contas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fin_baixas (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empresa_id           INT UNSIGNED NOT NULL,
  titulo_id            INT UNSIGNED NOT NULL,
  conta_financeira_id  INT UNSIGNED NOT NULL,
  data_pagamento       DATE NOT NULL,
  valor_principal      DECIMAL(15,2) NOT NULL,
  juros                DECIMAL(15,2) NOT NULL DEFAULT 0,
  multa                DECIMAL(15,2) NOT NULL DEFAULT 0,
  desconto             DECIMAL(15,2) NOT NULL DEFAULT 0,
  valor_total          DECIMAL(15,2) NOT NULL COMMENT 'principal + juros + multa - desconto',
  estornada            TINYINT(1) NOT NULL DEFAULT 0,
  observacao           VARCHAR(255) NULL,
  created_by           INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_baixa_data (empresa_id, data_pagamento),
  KEY idx_baixa_conta (conta_financeira_id),
  CONSTRAINT fk_baixa_titulo FOREIGN KEY (titulo_id) REFERENCES fin_titulos(id),
  CONSTRAINT fk_baixa_conta  FOREIGN KEY (conta_financeira_id) REFERENCES fin_contas_financeiras(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transferências podem ser entre contas da mesma empresa ou entre matriz e filial do mesmo grupo.
CREATE TABLE fin_transferencias (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empresa_origem_id   INT UNSIGNED NOT NULL,
  empresa_destino_id  INT UNSIGNED NOT NULL,
  conta_origem_id     INT UNSIGNED NOT NULL,
  conta_destino_id    INT UNSIGNED NOT NULL,
  data                DATE NOT NULL,
  valor               DECIMAL(15,2) NOT NULL,
  descricao           VARCHAR(200) NULL,
  estornada           TINYINT(1) NOT NULL DEFAULT 0,
  created_by          INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_transf_origem  (empresa_origem_id, data),
  KEY idx_transf_destino (empresa_destino_id, data),
  CONSTRAINT fk_transf_origem  FOREIGN KEY (conta_origem_id)  REFERENCES fin_contas_financeiras(id),
  CONSTRAINT fk_transf_destino FOREIGN KEY (conta_destino_id) REFERENCES fin_contas_financeiras(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens SSO já usados (o token é assinado por HMAC no app; aqui só se impede reutilização).
CREATE TABLE fin_sso_usados (
  jti          CHAR(36) PRIMARY KEY,
  app_user_id  INT UNSIGNED NOT NULL,
  expires_at   DATETIME NOT NULL,
  used_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sso_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ USUÁRIOS E ACESSO ============
-- Identidade única do ERP. Usuário do app ganha um registro no 1º acesso via SSO (app_user_id preenchido,
-- sem senha). Usuário só do ERP tem e-mail + senha_hash. Todos os created_by apontam para fin_usuarios.id.
CREATE TABLE fin_usuarios (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  origem           ENUM('app','erp') NOT NULL,
  app_user_id      INT UNSIGNED NULL COMMENT 'id do usuário no app (origem = app)',
  nome             VARCHAR(150) NOT NULL,
  email            VARCHAR(150) NULL COMMENT 'obrigatório para origem = erp',
  senha_hash       VARCHAR(255) NULL COMMENT 'password_hash ARGON2ID; NULL para origem = app',
  tipo_conta       ENUM('cliente','equipe_atc') NOT NULL DEFAULT 'cliente',
  perfil_equipe    ENUM('suporte_leitura','suporte_edicao','superadmin') NULL COMMENT 'só para equipe_atc',
  totp_secret      VARCHAR(64) NULL COMMENT '2FA obrigatório para equipe_atc (criptografado)',
  ativo            TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_login_at  DATETIME NULL,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_usuario_email (origem, email),
  UNIQUE KEY uk_usuario_app (app_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acesso de usuários só do ERP às empresas, com perfil.
-- Usuários do app herdam o acesso às empresas pelas permissões do próprio app.
CREATE TABLE fin_usuario_empresas (
  usuario_id  INT UNSIGNED NOT NULL,
  empresa_id  INT UNSIGNED NOT NULL,
  perfil      ENUM('admin','financeiro','consulta') NOT NULL DEFAULT 'financeiro',
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, empresa_id),
  KEY idx_ue_empresa (empresa_id),
  CONSTRAINT fk_ue_usuario FOREIGN KEY (usuario_id) REFERENCES fin_usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proteção contra força bruta no login individual.
CREATE TABLE fin_login_tentativas (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(150) NOT NULL,
  ip          VARCHAR(45)  NOT NULL,
  sucesso     TINYINT(1)   NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lt_email (email, created_at),
  KEY idx_lt_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Convite (definir 1ª senha) e "esqueci minha senha".
CREATE TABLE fin_senha_tokens (
  token_hash  CHAR(64) PRIMARY KEY COMMENT 'SHA-256 do token enviado por e-mail',
  usuario_id  INT UNSIGNED NOT NULL,
  tipo        ENUM('convite','reset') NOT NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_st_usuario FOREIGN KEY (usuario_id) REFERENCES fin_usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoria: gestão de usuários agora; pode ser usada depois para títulos, baixas e estornos.
CREATE TABLE fin_auditoria (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL COMMENT 'quem fez (fin_usuarios.id)',
  empresa_id  INT UNSIGNED NULL,
  acao        VARCHAR(40)  NOT NULL COMMENT 'ex.: usuario_convidado, perfil_alterado, usuario_desativado',
  entidade    VARCHAR(40)  NOT NULL COMMENT 'ex.: usuario, titulo, baixa',
  entidade_id INT UNSIGNED NULL,
  dados       JSON NULL COMMENT 'antes/depois da alteração',
  ip          VARCHAR(45) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_aud_empresa (empresa_id, created_at),
  KEY idx_aud_entidade (entidade, entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de cada vez que alguém da equipe ATC abre os dados de uma empresa cliente.
CREATE TABLE fin_acessos_suporte (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL,
  empresa_id  INT UNSIGNED NOT NULL,
  motivo      VARCHAR(200) NULL COMMENT 'ex.: chamado WhatsApp, dúvida sobre saldo',
  inicio_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fim_at      DATETIME NULL,
  ip          VARCHAR(45) NULL,
  KEY idx_as_empresa (empresa_id, inicio_at),
  KEY idx_as_usuario (usuario_id, inicio_at),
  CONSTRAINT fk_as_usuario FOREIGN KEY (usuario_id) REFERENCES fin_usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
