# ERP Financeiro – ATC Zanardi Contabilidade

> O escopo completo do projeto (regras de negócio, decisões e pontos em aberto) está em `docs/ESCOPO_erp_atczanardi.md`. Consulte-o antes de implementar qualquer funcionalidade.

## Contexto
ERP financeiro simples e prático para os clientes (empresas) da ATC Zanardi Contabilidade.
Escopo: contas a pagar, contas a receber, fluxo de caixa.
Cadastros: plano de contas, contas financeiras (caixinha e bancos), clientes/fornecedores.
Fora do escopo: emissão de notas fiscais (feita SEMPRE no app existente da contabilidade).

O usuário já logado no app da contabilidade é redirecionado ao ERP, que abre com a empresa dele carregada.

## Stack
- PHP 8.x + MySQL 8 (InnoDB, utf8mb4_unicode_ci), hospedado na Hostinger.
- PHP puro, sem framework e sem Composer por enquanto (deploy simples na Hostinger).

## Bancos de dados
- **Banco do ERP** (próprio, separado): todas as tabelas do ERP, prefixo `fin_`. Leitura e escrita.
- **Banco do app ATC Zanardi**: SOMENTE LEITURA. O ERP nunca cria, altera, insere, atualiza ou apaga
  nada nele: nem tabelas, nem dados, nem índices, nem migrations.
- Duas conexões PDO separadas (`Database::erp()` e `Database::app()`), com credenciais próprias no `.env`.
- Proteções da conexão com o app, em camadas:
  1. Se a Hostinger permitir, usuário MySQL com permissão apenas de SELECT no banco do app.
  2. `SET SESSION TRANSACTION READ ONLY` ao abrir a conexão (o MySQL recusa escrita nessa sessão).
  3. Classe `AppReader`: único ponto de acesso ao banco do app, só aceita consultas que começam com SELECT.
- Não existe JOIN entre os dois bancos: buscar no app, depois no ERP, e cruzar em PHP pelos IDs.
- IDs vindos do app (empresa_id, app_user_id) são guardados no ERP sem chave estrangeira.
- Antes de ler qualquer tabela do app, inspecionar a estrutura dela (nomes de tabelas e colunas,
  como as senhas são guardadas, como se identifica a equipe ATC e o acesso do usuário às empresas)
  e registrar o mapeamento no `.env` ou em `config/app_map.php`, sem adivinhar nomes.
- Interface web responsiva, mobile first (o cliente usa muito pelo celular).

## Domínio e hospedagem
- Domínio oficial (canônico): https://erp.atczanardicontabilidade.com.br
- https://erp.atczanardi.com.br é apenas redirecionamento 301 para o domínio oficial, preservando caminho
  e parâmetros. O sistema nunca roda nem cria sessão nesse domínio (cookie, PWA e login só no oficial).
- Arquivos na hospedagem de atczanardicontabilidade.com.br, pasta `public_html/erp`.
- Estrutura obrigatória (só a pasta public fica acessível pela web):
  ```
  public_html/erp/
  ├── public/        <- raiz do subdomínio (index.php, sso.php, assets, manifest.json, sw.js)
  ├── src/           <- código PHP (controllers, models, serviços)
  ├── config/
  ├── bin/           <- scripts de linha de comando (criar-superadmin.php, cron)
  ├── storage/       <- logs e arquivos temporários
  ├── vendor/
  └── .env
  ```
  No hPanel, apontar o subdomínio erp para `public_html/erp/public`.
- Como `public_html/erp` fica dentro da pasta do site principal, incluir `.htaccess` em `public_html/erp`
  negando acesso direto (`Require all denied`) e, em `public/`, redirecionar para o domínio oficial
  qualquer requisição cujo Host não seja erp.atczanardicontabilidade.com.br.
  Assim www.atczanardicontabilidade.com.br/erp/... nunca expõe o sistema, o .env ou o código.
- Forçar HTTPS e enviar HSTS. SSL ativo nos dois subdomínios (o de redirecionamento também precisa,
  senão o https://erp.atczanardi.com.br dá erro antes de redirecionar).
- Cookie de sessão: sem atributo Domain (vale só para erp.atczanardicontabilidade.com.br), Secure, HttpOnly, SameSite=Lax.
- URL base em `.env` (`APP_URL`); links de convite, redefinição de senha e e-mails sempre com o domínio oficial.
- SSO: o app só aceita URL de retorno que comece exatamente com o domínio oficial (lista fixa, sem open redirect).
- PWA: `start_url` e `scope` no domínio oficial.

## PWA (app instalável, sem loja)
- O ERP é uma PWA: instalável na tela inicial do Android e do iPhone, e usável também no computador.
- `manifest.json`: name/short_name, ícones 192x192 e 512x512 (incluir maskable), display "standalone",
  theme_color e background_color, start_url apontando para o painel.
- Service worker: cache SOMENTE de assets estáticos (CSS, JS, ícones, fontes) e de uma página offline simples.
  Dados financeiros NUNCA ficam em cache: sempre vêm do servidor (segurança e consistência dos saldos).
- HTTPS obrigatório (SSL da Hostinger ativo).
- Incluir meta tags para iOS (apple-touch-icon, apple-mobile-web-app-capable) e uma tela
  explicando ao usuário do iPhone como usar "Adicionar à Tela de Início".
- Usabilidade mobile: navegação inferior com as principais seções, botões grandes,
  `inputmode="decimal"` nos campos de valor, máscara de moeda pt-BR, datas com input nativo.
- Futuro: notificações de vencimento via Web Push (VAPID, biblioteca minishlink/web-push)
  disparadas por cron da Hostinger. No iPhone só funciona com o app instalado e iOS 16.4+.
- Se um dia for preciso publicar nas lojas, empacotar esta mesma PWA com Capacitor, sem reescrever.

## Regras inegociáveis
1. Multiempresa: dados de movimento têm `empresa_id`; cadastros compartilhados têm `grupo_id`.
   Toda leitura filtra por `empresa_id IN (lista da sessão)` ou `grupo_id = grupo da sessão`.
   A lista de empresas vem SEMPRE da sessão, montada no servidor. Nunca aceitar empresa_id
   ou grupo_id vindo de GET/POST sem conferir que pertence à lista permitida da sessão.
2. Valores monetários: DECIMAL(15,2) no banco; nunca float. Cálculos com bcmath ou em centavos (int).
3. SQL sempre com PDO e prepared statements. Nada de concatenação de variáveis em SQL.
4. Formulários com token CSRF. Saída HTML sempre com htmlspecialchars.
5. Fuso horário America/Sao_Paulo. Datas no banco em DATE/DATETIME.
6. Exclusão de títulos e baixas: cancelamento/estorno lógico, não DELETE.
7. Credenciais em `.env` fora do diretório público; `.env` no .gitignore.

## Glossário e regras de negócio
- **Título**: o compromisso (conta a pagar ou a receber). Tem valor, competência e vencimento.
- **Baixa**: o pagamento/recebimento de um título. Um título pode ter várias baixas (pagamento parcial).
  A baixa registra conta financeira, data, principal, juros, multa e desconto.
- **Status do título**: aberto → parcial → pago, ou cancelado. Calculado a partir das baixas não estornadas.
- **Três datas**: competência (a que mês pertence), vencimento (quando deveria ser pago), pagamento (na baixa).
- **Conta financeira**: caixa (caixinha) ou banco. Tem saldo inicial com data.
- **Transferência**: movimento entre duas contas financeiras da mesma empresa
  (ex.: depósito do caixinha no banco). NÃO é receita nem despesa e não entra no DRE.
- **Saldo da conta** = saldo inicial + recebimentos − pagamentos + transferências recebidas − transferências enviadas.
- **Fluxo de caixa realizado**: baseado nas baixas (data de pagamento).
- **Fluxo de caixa projetado**: saldo atual + títulos em aberto pelo vencimento.
- **Plano de contas**: hierárquico (sintéticas agrupam, analíticas recebem lançamentos).
  Existe um plano modelo da ATC Zanardi (grupo_id NULL) que é copiado para o grupo no primeiro acesso.
  O plano é por grupo (compartilhado entre matriz e filiais). Lançamentos só podem usar contas
  analíticas do grupo da empresa.
- **Categoria**: por enquanto, categoria = conta analítica do plano de contas (decisão a confirmar).
- **Parcelamento/recorrência**: títulos gerados em série compartilham `grupo_id`.
- **Origem do título**: manual, nota (gerado a partir de nota emitida no app) ou guia (imposto) – integração futura.

## Matriz e filiais (visão individual ou consolidada)
- Cada estabelecimento (matriz ou filial) é uma `empresa_id` própria, com seus títulos, baixas e contas financeiras.
- `fin_grupos` une as empresas do mesmo CNPJ raiz (8 primeiros dígitos); `fin_grupo_empresas` liga cada
  empresa ao grupo. Empresa sem filial = grupo com uma empresa só (mesma lógica para todos).
- Ao entrar, preencher automaticamente o grupo pelo CNPJ raiz das empresas do app.
- Compartilhados pelo grupo: plano de contas e clientes/fornecedores (consolidação usa os mesmos códigos).
- Seletor fixo no topo: "Matriz", "Filial X"... e "Consolidado (todas)". A escolha fica na sessão:
  `$_SESSION['empresas_visiveis']` = [uma empresa] ou [todas do grupo que o usuário pode acessar].
- Consolidado só inclui empresas às quais o usuário tem acesso no app existente.
- Leitura (listas, saldos, fluxo, relatórios) usa `empresas_visiveis`. No consolidado, mostrar
  coluna/etiqueta da empresa em cada linha e permitir filtrar por empresa.
- Gravação é SEMPRE de uma empresa: na visão consolidada, o formulário exige escolher o estabelecimento.
- Transferência entre matriz e filial: registrada com empresa de origem e de destino.
  Na visão individual aparece como saída/entrada; na consolidada é eliminada (não altera o saldo do grupo).
  Não é receita nem despesa em nenhuma das visões.

## Login: duas formas de acesso
Tela de login com duas opções:
- **"Conta do app ATC Zanardi"**: o usuário digita o mesmo login e senha do app na tela do ERP.
  O ERP confere lendo a tabela de usuários do app (somente leitura) e validando o hash da senha
  conforme o algoritmo que o app usa (`APP_PASSWORD_ALGO` no `.env`). O ERP nunca grava nem altera a senha do app.
  Quem já está logado no app e clica para abrir o ERP entra pelo fluxo SSO abaixo, sem digitar senha.
- **"E-mail e senha"**: para usuários que só têm acesso ao ERP (ex.: funcionário do financeiro do cliente).

Regras:
- `fin_usuarios` é a identidade única do ERP. Usuário do app ganha um registro no 1º acesso via SSO
  (origem = app, app_user_id preenchido, sem senha). Se esse registro estiver
  desativado no ERP, o acesso ao ERP é negado mesmo com a senha do app correta. Usuário só do ERP tem origem = erp e senha própria.
- Usuário do app: empresas acessíveis vêm das permissões do app. Usuário só do ERP: vêm de `fin_usuario_empresas`.
- Perfis do usuário só do ERP: admin (gerencia usuários da empresa), financeiro (lança e baixa), consulta (só visualiza).
- Usuários só do ERP são criados por convite: quem cria informa nome, e-mail, empresas e perfil;
  o sistema envia por e-mail (SMTP da Hostinger) um link de uso único para a pessoa definir a senha.
  Ninguém define a senha de outra pessoa.
- Quem pode criar e gerenciar usuários só do ERP (convidar, alterar perfil/empresas, desativar):
  - **Equipe da ATC Zanardi** (tipo_conta = equipe_atc, perfil suporte_edicao ou superadmin): qualquer empresa cliente.
    Na listagem de usuários, mostrar "criado pela ATC Zanardi".
  - **Cliente que acessa pelo app**: somente as empresas que ele mesmo acessa no app.
  - **Usuário só do ERP com perfil admin**: somente as empresas em que ele é admin.
- Ninguém concede acesso a empresa que não acessa, nem perfil acima do próprio.
- Usuário não pode desativar a si mesmo nem remover o próprio perfil admin.
- Toda ação de gestão de usuários grava em `fin_auditoria` (quem fez, o quê, quando, em qual empresa).
- Senha: `password_hash` com PASSWORD_ARGON2ID, mínimo de 8 caracteres; `password_verify` no login.
- Força bruta: bloquear por 15 minutos após 5 falhas por e-mail ou 20 por IP (`fin_login_tentativas`).
- Mensagem de erro genérica ("e-mail ou senha inválidos"), sem dizer qual dos dois está errado.
- "Esqueci minha senha": token por e-mail, validade de 1 hora, uso único (`fin_senha_tokens`).
- Usuário desativado perde o acesso na hora (checar `ativo` a cada requisição ou ao renovar a sessão).
- Sessão expira após inatividade (ex.: 2 horas). `session_regenerate_id(true)` em todo login.
- Depois do login, as duas formas caem na mesma sessão: usuario_id (de fin_usuarios), grupo_id,
  empresas permitidas e empresas_visiveis.

## Equipe ATC Zanardi (suporte)
- Usuários da equipe têm `tipo_conta = equipe_atc` em `fin_usuarios` e acessam QUALQUER empresa cliente
  para dar suporte. Podem entrar pelo app (se o app já marca quem é da equipe, sincronizar) ou por e-mail e senha.
- Perfis da equipe:
  - `suporte_leitura`: vê tudo, não altera nada (padrão para novos membros).
  - `suporte_edicao`: pode lançar, baixar e corrigir em nome do cliente.
  - `superadmin`: tudo acima + cadastra e gerencia a equipe ATC.
- Só superadmin cria ou altera usuários da equipe. O primeiro superadmin é criado por script de linha de
  comando (`php bin/criar-superadmin.php`), nunca por tela pública.
- 2FA (TOTP, app autenticador) OBRIGATÓRIO para equipe_atc, pois a conta enxerga todos os clientes.
- Após o login, a equipe vê uma busca de empresas (nome, CNPJ, código do cliente) em vez do seletor do grupo.
  Ao abrir uma empresa, carregar o grupo dela (permitindo a visão consolidada) e registrar em
  `fin_acessos_suporte` (quem, qual empresa, quando, motivo opcional).
- Enquanto estiver dentro de uma empresa cliente, mostrar faixa fixa "Modo suporte ATC Zanardi – [empresa]"
  com botão "Sair da empresa".
- Toda alteração feita pela equipe grava em `fin_auditoria` e aparece como "Suporte ATC Zanardi" no histórico.
- Usuários da equipe não aparecem na lista de usuários do cliente e não ocupam vaga/limite de usuários dele.
- Sessão da equipe expira mais cedo (ex.: 30 minutos de inatividade).

## Login por redirecionamento (SSO, a partir do app)
Como os bancos são separados, o token é assinado e não gravado no banco do app.
Isso exige uma pequena alteração no CÓDIGO do app (botão "Abrir ERP"), nunca no banco dele.
1. No app: montar o payload {app_user_id, empresa_id, exp = agora + 60s, jti = UUID aleatório},
   assinar com HMAC-SHA256 usando `SSO_SECRET` (o mesmo valor nos `.env` do app e do ERP, 64+ caracteres
   aleatórios) e redirecionar para `/sso.php?t=PAYLOAD_BASE64URL.ASSINATURA`.
2. No ERP: conferir a assinatura com `hash_equals`, conferir `exp`, gravar o `jti` em `fin_sso_usados`
   (chave primária: se já existir, o token já foi usado e é recusado),
   localizar ou criar o registro em `fin_usuarios` (origem = app),
   chamar `session_regenerate_id(true)`, gravar usuario_id, empresa_id, grupo_id e a lista de empresas
   do grupo que o usuário pode acessar. A visão inicial é a empresa que veio do app.
3. Qualquer falha: tela de "acesse pelo app da contabilidade", sem detalhar o motivo.
4. Sessão com cookie HttpOnly, Secure e SameSite=Lax.

## Ordem de desenvolvimento
1. Migrations (schema.sql) e seed do plano de contas modelo.
2. Login (tela com conta do app + acesso exclusivo do ERP, depois SSO do app), usuários e convites, controle de sessão por empresa e base PWA (manifest, service worker, layout mobile com navegação inferior).
3. Cadastro de contas financeiras e de clientes/fornecedores.
4. Contas a pagar com baixa (parcial, juros, multa, desconto, estorno).
5. Contas a receber (mesma lógica).
6. Transferências entre contas.
7. Fluxo de caixa realizado e projetado + saldos por conta.
8. Futuro: integração notas/guias do app, conciliação OFX, DRE gerencial, push de vencimentos.

## Ambiente
- Desenvolvimento local; nunca editar direto em produção.
- Deploy na Hostinger via Git (hPanel) ou SFTP.
