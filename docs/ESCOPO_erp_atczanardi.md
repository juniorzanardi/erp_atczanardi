# ERP Financeiro ATC Zanardi — Documento de Escopo

**Projeto:** erp_atczanardi
**Versão do documento:** 1.0 — 07/11/2026
**Status:** fase de especificação concluída para o MVP; tela de login construída e testada.

Convenção usada neste documento:

- **[DECIDIDO]**: definido explicitamente nas conversas de especificação.
- **[PROPOSTA]**: sugestão técnica ou de negócio ainda não confirmada pelo responsável do projeto.
- **[EM ABERTO]**: depende de informação ou decisão pendente.

---

## 1. Visão geral e objetivo

### 1.1 O que é
Um ERP **financeiro**, simples e prático, oferecido pela ATC Zanardi Contabilidade aos seus clientes pessoa jurídica. O sistema cobre o controle financeiro do dia a dia da empresa: contas a pagar, contas a receber, caixa, bancos e fluxo de caixa. **[DECIDIDO]**

### 1.2 O que NÃO é
- **Não emite nota fiscal.** A emissão de NF-e/NFS-e acontece sempre no app já existente da ATC Zanardi Contabilidade, nunca dentro do ERP. **[DECIDIDO]**
- Não é sistema contábil: o plano de contas do ERP é **gerencial/financeiro**, não o plano de contas contábil da escrituração. **[DECIDIDO]**
- Não gera nem transmite obrigações acessórias (SPED, EFD, DCTFWeb etc.). Essas continuam com a contabilidade. **[DECIDIDO por escopo; ver seção 4]**
- Não apura tributos. **[DECIDIDO por escopo]**

### 1.3 Objetivo de negócio
- Entregar ao cliente da contabilidade uma ferramenta financeira integrada ao ecossistema que ele já usa (app de impostos e emissão de notas).
- Fazer o cliente controlar o financeiro dentro do ambiente da ATC Zanardi, aumentando a retenção e o valor percebido do serviço contábil.
- Permitir que a equipe da ATC Zanardi dê suporte enxergando os dados do cliente.
- No futuro, usar a integração com o app (notas emitidas e guias de impostos) como diferencial que um ERP genérico não oferece. **[PROPOSTA]**

### 1.4 Relação com o app existente
- O app ATC Zanardi Contabilidade já existe, em PHP + MySQL na Hostinger, e é usado pelos clientes para gerenciar impostos e emitir notas. **[DECIDIDO]**
- O ERP é um **sistema separado**, com banco de dados próprio. **[DECIDIDO]**
- O ERP **lê** o banco do app (usuários, empresas, futuramente notas e guias), mas **nunca cria tabelas, nunca insere, nunca altera e nunca apaga nada nele**. **[DECIDIDO]**
- O usuário logado no app pode ser redirecionado ao ERP e entrar já com a empresa dele carregada. **[DECIDIDO]**

---

## 2. Público-alvo e perfis de usuário

### 2.1 Público-alvo
- Empresas clientes da ATC Zanardi Contabilidade, de qualquer regime tributário, que querem controlar contas a pagar e a receber, caixa e bancos sem a complexidade de um ERP completo. **[DECIDIDO]**
- Inclui empresas com **matriz e filiais** (seção 3.3). **[DECIDIDO]**
- Uso predominante pelo celular, mas também pelo computador. **[DECIDIDO: versão web mobile/PWA]**

### 2.2 Tipos de conta
Toda pessoa que acessa o ERP tem um registro único em `fin_usuarios`, com um destes tipos:

| Tipo de conta | Origem do login | Quem é |
|---|---|---|
| Cliente com conta no app | `origem = app` | Usuário que já tem acesso ao app ATC Zanardi Contabilidade (normalmente o dono ou responsável pela empresa). |
| Cliente só do ERP | `origem = erp` | Pessoa que acessa apenas o ERP (ex.: funcionário do financeiro do cliente). Não tem conta no app. |
| Equipe ATC Zanardi | `tipo_conta = equipe_atc` (origem app ou erp) | Colaboradores da ATC Zanardi que dão suporte e podem acessar qualquer empresa cliente. |

**[DECIDIDO]**

### 2.3 Perfis do cliente só do ERP
Definidos por empresa, na tabela `fin_usuario_empresas`:

| Perfil | Pode |
|---|---|
| `admin` | Tudo do perfil financeiro, mais convidar, alterar e desativar usuários só do ERP **nas empresas em que é admin**. |
| `financeiro` | Cadastrar e editar títulos, registrar baixas, transferências, cadastros financeiros. |
| `consulta` | Apenas visualizar. |

**[DECIDIDO: os três perfis foram propostos e aceitos implicitamente; confirmar permissões finas na construção]**

### 2.4 Acesso do cliente com conta no app
- As empresas que ele acessa no ERP **vêm das permissões que ele já tem no app** (lidas do banco do app). **[DECIDIDO]**
- Pode criar e gerenciar usuários só do ERP **apenas para as empresas que ele mesmo acessa no app**. **[DECIDIDO]**
- Se o registro dele no ERP for desativado, o acesso ao ERP é negado, mesmo que a senha do app esteja correta. **[DECIDIDO, implementado]**
- O nível de permissão dentro do ERP para esse usuário (equivalente a admin?) **[EM ABERTO: assumido como admin das próprias empresas]**

### 2.5 Perfis da equipe ATC Zanardi

| Perfil | Pode |
|---|---|
| `suporte_leitura` | Ver qualquer empresa cliente, sem alterar nada. Padrão para novos membros. |
| `suporte_edicao` | Tudo do anterior, mais lançar, baixar e corrigir em nome do cliente; criar e gerenciar usuários só do ERP de qualquer cliente. |
| `superadmin` | Tudo do anterior, mais cadastrar e gerenciar os usuários da própria equipe ATC. |

Regras da equipe **[DECIDIDO]**:
- Só `superadmin` cria ou altera usuários da equipe.
- O **primeiro superadmin** é criado por script de linha de comando (`php bin/criar-superadmin.php`), nunca por tela pública.
- **2FA obrigatório** (TOTP, app autenticador) para toda conta `equipe_atc`.
- Depois do login, a equipe vê uma **busca de empresas** (nome, CNPJ, código do cliente) em vez do seletor de grupo.
- Ao abrir uma empresa, o sistema carrega o grupo dela (com visão consolidada disponível) e registra o acesso em `fin_acessos_suporte` (quem, qual empresa, quando, motivo opcional).
- Enquanto estiver dentro de um cliente, aparece uma **faixa fixa "Modo suporte ATC Zanardi – [empresa]"** com o botão "Sair da empresa".
- Toda alteração feita pela equipe grava em `fin_auditoria` e aparece no histórico como **"Suporte ATC Zanardi"**.
- Usuários da equipe **não aparecem** na lista de usuários do cliente e não ocupam vaga ou limite de usuários dele.
- A sessão da equipe expira mais cedo (30 minutos de inatividade).
- Como identificar no app quem é da equipe ATC: **[EM ABERTO: inspecionar o app; não criar marcação nova sem confirmar]**

### 2.6 Regras gerais de gestão de usuários **[DECIDIDO]**
- Quem pode criar usuários só do ERP: a **equipe ATC Zanardi** (perfis suporte_edicao e superadmin) e o **próprio cliente** (via conta do app ou perfil admin do ERP).
- **Ninguém concede acesso a uma empresa que não acessa**, nem perfil acima do próprio.
- Ninguém pode desativar a si mesmo nem remover o próprio perfil admin.
- Criação sempre por **convite**: quem cria informa nome, e-mail, empresas e perfil; o sistema envia por e-mail um link de uso único para a pessoa **definir a própria senha**. Ninguém define a senha de outra pessoa.
- Usuário criado pela equipe aparece na listagem do cliente como "criado pela ATC Zanardi".
- Toda ação de gestão de usuários (convidar, alterar perfil ou empresas, desativar) grava em `fin_auditoria`.

---

## 3. Módulos e funcionalidades

Legenda de prioridade: **MVP** (primeira entrega), **Fase 2**, **Futuro**.

### 3.1 Autenticação e acesso — MVP

#### 3.1.1 Tela de login (construída e testada)
Duas opções na mesma tela, em um seletor:
1. **"Conta do app ATC Zanardi"**: o usuário digita o mesmo login e senha do app. O ERP confere lendo a tabela de usuários do app (somente leitura) e validando o hash conforme o algoritmo que o app usa (`APP_PASSWORD_ALGO`: `password_hash`, `md5` ou `sha1`). No primeiro acesso, cria o registro correspondente em `fin_usuarios` (`origem = app`, `app_user_id` preenchido, sem senha). A cada login, sincroniza nome e e-mail com o app. **[DECIDIDO, implementado]**
2. **"Acesso exclusivo do ERP"**: e-mail e senha próprios do ERP. **[DECIDIDO, implementado]**

Regras do login **[DECIDIDO, implementado]**:
- Senhas do ERP com `password_hash` (Argon2id quando disponível, senão o padrão do PHP); rehash automático quando o algoritmo evoluir.
- Mínimo de 8 caracteres para senhas do ERP. **[DECIDIDO; validar no cadastro de senha, ainda não construído]**
- **Proteção contra força bruta:** bloqueio de 15 minutos após 5 falhas para o mesmo login, ou 20 falhas do mesmo IP (`fin_login_tentativas`).
- Mensagem de erro genérica ("Usuário ou senha incorretos"), sem revelar se o usuário existe.
- Tempo de resposta equalizado quando o usuário não existe (não permite descobrir usuários válidos).
- `session_regenerate_id(true)` em todo login.
- Proteção CSRF em todos os formulários.
- Sessão expira após inatividade (120 minutos para clientes, 30 para a equipe).
- Usuário desativado perde o acesso imediatamente.
- Recuperação de senha do app: feita pelo próprio app (o ERP apenas orienta).

#### 3.1.2 "Esqueci minha senha" (usuários só do ERP) — MVP
Token por e-mail, validade de 1 hora, uso único (`fin_senha_tokens`, tipo `reset`). **[DECIDIDO]**

#### 3.1.3 Convite de usuário — MVP
Token por e-mail, uso único (`fin_senha_tokens`, tipo `convite`), para a pessoa definir a primeira senha. Validade **[EM ABERTO: sugerido 72 horas]**. E-mail via SMTP da Hostinger. **[DECIDIDO]**

#### 3.1.4 Entrada direta a partir do app (SSO) — MVP
Para quem já está logado no app e toca em "Abrir ERP". Como os bancos são separados, o token é **assinado**, não gravado no banco do app. **[DECIDIDO]**
1. No app: monta o payload `{app_user_id, empresa_id, exp = agora + 60 s, jti = UUID aleatório}`, assina com HMAC-SHA256 usando `SSO_SECRET` (mesmo valor nos `.env` do app e do ERP, 64+ caracteres aleatórios) e redireciona para `https://erp.atczanardicontabilidade.com.br/sso.php?t=PAYLOAD.ASSINATURA`.
2. No ERP: confere a assinatura com `hash_equals`, confere a expiração, grava o `jti` em `fin_sso_usados` (se já existir, o token já foi usado e é recusado), localiza ou cria o usuário em `fin_usuarios`, regenera a sessão e grava usuário, grupo e empresas. **A visão inicial é a empresa que veio do app.**
3. Qualquer falha leva a uma tela "acesse pelo app da contabilidade", sem detalhar o motivo.
4. O app só pode redirecionar para o domínio oficial do ERP (lista fixa, sem redirecionamento aberto).

Requer uma **pequena alteração no código do app** (o botão "Abrir ERP" e a geração do token), **nunca no banco do app**. **[DECIDIDO]**

#### 3.1.5 2FA para a equipe ATC — MVP
TOTP com app autenticador, obrigatório para `equipe_atc`. Segredo guardado criptografado em `fin_usuarios.totp_secret`. **[DECIDIDO]** Biblioteca ou implementação **[EM ABERTO]**.

### 3.2 Seleção de empresa e contexto da sessão — MVP
- Depois do login, a sessão guarda: `usuario_id`, `grupo_id`, `empresas_permitidas` e `empresas_visiveis`. **[DECIDIDO]**
- Usuário com mais de uma empresa (ou de mais de um grupo) escolhe com qual vai trabalhar. **[DECIDIDO]**
- Equipe ATC: busca de empresas em vez de seletor (seção 2.5). **[DECIDIDO]**

### 3.3 Matriz e filiais (visão individual ou consolidada) — MVP
**[DECIDIDO]**
- Cada estabelecimento (matriz ou filial) é uma `empresa_id` própria, com seus próprios títulos, baixas, caixinha e contas bancárias.
- Um **grupo** (`fin_grupos`) une as empresas do mesmo **CNPJ raiz** (8 primeiros dígitos). `fin_grupo_empresas` liga cada empresa ao grupo, indicando se é matriz ou filial e um apelido (ex.: "Filial Centro").
- O grupo é **preenchido automaticamente** a partir do CNPJ das empresas lidas do app.
- Empresa sem filial é tratada como grupo de uma empresa só, com a mesma lógica para todos.
- **Compartilhados pelo grupo:** plano de contas e cadastro de clientes/fornecedores. Isso garante que a consolidação some pelos mesmos códigos.
- **Seletor fixo no topo:** "Matriz", "Filial X"... e "Consolidado (todas)". A escolha fica na sessão (`empresas_visiveis`).
- O consolidado **só inclui empresas que o usuário pode acessar**. Quem só acessa a filial não vê a matriz.
- **Leitura** (listas, saldos, fluxo de caixa, relatórios) usa `empresas_visiveis`. No consolidado, cada linha mostra a qual empresa pertence e é possível filtrar por empresa.
- **Gravação é sempre de uma empresa:** na visão consolidada, o formulário exige escolher o estabelecimento.
- **Transferência entre matriz e filial:** registrada com empresa de origem e de destino. Na visão individual aparece como saída numa e entrada na outra; na visão consolidada é **eliminada** (não altera o saldo do grupo). **Nunca** é receita nem despesa, em nenhuma das visões.

### 3.4 Cadastros — MVP

#### 3.4.1 Plano de contas financeiro
- **Hierárquico:** contas sintéticas agrupam; contas analíticas recebem lançamentos. **[DECIDIDO]**
- Campos: código, descrição, natureza (receita ou despesa), conta pai, analítica (sim/não), ativo. **[DECIDIDO]**
- Existe um **plano modelo da ATC Zanardi** (`grupo_id = NULL`), que é **copiado para o grupo no primeiro acesso**. Cada grupo pode então personalizar o seu. **[DECIDIDO]**
- O plano é **por grupo** (compartilhado entre matriz e filiais). **[DECIDIDO]**
- Lançamentos só podem usar **contas analíticas** do grupo da empresa. **[DECIDIDO]**
- Código único por grupo. **[DECIDIDO]**
- O plano modelo será fornecido pelo responsável do projeto (já está pronto). **[EM ABERTO: envio previsto para segunda-feira, 09/11/2026, para montar o seed]**
- Regras para editar ou excluir conta já usada em lançamentos **[PROPOSTA]**: não permitir exclusão de conta com lançamentos, apenas inativação; não permitir transformar analítica com lançamentos em sintética.

#### 3.4.2 Categorias
- Pedido original: cadastro de categorias. **[DECIDIDO como necessidade]**
- Decisão provisória: **categoria = conta analítica do plano de contas**, para evitar dois cadastros que se sobrepõem e para o DRE gerencial sair pronto. **[EM ABERTO: confirmar, ou definir categoria como cadastro separado ou como etiqueta]**

#### 3.4.3 Contas financeiras (caixinha e contas bancárias)
**[DECIDIDO]**
- Caixinha (dinheiro) e contas bancárias são **uma única entidade** (`fin_contas_financeiras`), com o campo tipo `caixa` ou `banco`.
- Campos: nome, tipo, código do banco, agência, conta, **saldo inicial e data do saldo inicial**, ativo.
- Pertencem a **cada estabelecimento** (não ao grupo).
- Futuro: tipos `cartão` e `aplicação` **[PROPOSTA]**.

#### 3.4.4 Clientes e fornecedores (pessoas)
**[DECIDIDO]**
- Cadastro único com tipo `cliente`, `fornecedor` ou `ambos`; nome, CPF/CNPJ (só números), e-mail, telefone, ativo.
- **Compartilhado pelo grupo.**
- Pode ser alimentado **lendo o cadastro do app** (tomadores das notas), sem alterá-lo. **[PROPOSTA: importar ou sincronizar; definir na construção]**

### 3.5 Contas a pagar e a receber (títulos e baixas) — MVP
**[DECIDIDO]**

#### 3.5.1 Conceitos
- **Título:** o compromisso financeiro (conta a pagar ou a receber). Tem valor, data de competência e data de vencimento.
- **Baixa:** o pagamento ou recebimento de um título. **Um título pode ter várias baixas** (pagamento parcial).
- A baixa registra: conta financeira usada, data do pagamento, valor principal, juros, multa, desconto e valor total (`principal + juros + multa − desconto`).

#### 3.5.2 As três datas
- **Competência:** a que mês o fato pertence (base para visão por competência / DRE gerencial).
- **Vencimento:** quando deveria ser pago (base para fluxo projetado e cobrança).
- **Pagamento:** quando efetivamente foi pago (fica na baixa; base para o fluxo realizado).

#### 3.5.3 Status do título
`aberto` → `parcial` → `pago`, ou `cancelado`. **Calculado a partir das baixas não estornadas.**

Regra de cálculo **[PROPOSTA detalhada]**:
- Soma do principal das baixas válidas = 0 → `aberto`.
- 0 < soma < valor do título → `parcial`.
- Soma ≥ valor do título → `pago`.
- `cancelado` é definido manualmente e só é permitido sem baixas válidas.
- Juros e multa não abatem o saldo devedor; desconto abate (ou seja, o título é quitado quando principal + desconto ≥ valor). **[EM ABERTO: confirmar o tratamento do desconto na quitação]**

#### 3.5.4 Parcelamento e recorrência
- Títulos gerados em série compartilham um identificador de série (`fin_titulos.grupo_id`, CHAR(36) — não confundir com o grupo de empresas), com número da parcela e total de parcelas.
- Uso típico: aluguel, mensalidades, compras parceladas.
- Arredondamento de parcelas **[PROPOSTA]**: diferença de centavos na última parcela.
- Recorrência por tempo indeterminado (ex.: aluguel sem fim definido) **[EM ABERTO: gerar N meses à frente ou por rotina mensal via cron]**.

#### 3.5.5 Origem do título
`manual`, `nota` (gerado a partir de nota emitida no app) ou `guia` (imposto), com `origem_ref` guardando o identificador no app. No MVP só existe `manual`; `nota` e `guia` são da Fase 2.

#### 3.5.6 Exclusão e estorno
- **Não existe DELETE** de títulos e baixas: título é **cancelado**; baixa é **estornada** (`estornada = 1`). Tudo fica no histórico. **[DECIDIDO]**
- Estorno de baixa recalcula o status do título. **[DECIDIDO]**

### 3.6 Transferências entre contas — MVP
**[DECIDIDO]**
- Movimento entre duas contas financeiras: da mesma empresa (ex.: depósito do dinheiro do caixinha no banco) ou entre matriz e filial do mesmo grupo.
- **Não é receita nem despesa** e não entra no DRE.
- Registra empresa e conta de origem, empresa e conta de destino, data, valor, descrição.
- Pode ser estornada (sem DELETE).
- Transferência entre empresas de grupos diferentes **não é permitida** **[PROPOSTA]**.

### 3.7 Fluxo de caixa e saldos — MVP
**[DECIDIDO]**
- **Saldo da conta** = saldo inicial + recebimentos − pagamentos + transferências recebidas − transferências enviadas (considerando apenas movimentos a partir da data do saldo inicial e não estornados).
- **Fluxo de caixa realizado:** baseado nas baixas, pela data de pagamento.
- **Fluxo de caixa projetado:** saldo atual + títulos em aberto (ou o saldo aberto dos parciais), pela data de vencimento.
- Respeita a visão individual ou consolidada (seção 3.3).
- Títulos vencidos e não pagos no projetado **[PROPOSTA]**: mostrar em linha separada "vencidos" para não distorcer o dia atual.
- Períodos e agrupamento (diário, semanal, mensal) **[PROPOSTA: diário e mensal no MVP]**.

### 3.8 Painel inicial — MVP **[PROPOSTA]**
Saldo total e por conta, a pagar e a receber hoje e nos próximos 7 dias, vencidos. Navegação inferior no celular com as seções principais.

### 3.9 Auditoria — MVP
**[DECIDIDO]**
- `fin_auditoria` registra quem fez, o quê, quando, em qual empresa, com dados de antes e depois (JSON) e IP.
- No MVP: obrigatória para gestão de usuários e para toda ação da equipe ATC.
- Estender para títulos, baixas e estornos **[PROPOSTA: recomendado já no MVP, pois responde à pergunta "quem pagou isso?"]**.
- `fin_acessos_suporte` registra cada entrada da equipe numa empresa cliente.

### 3.10 Fase 2

| Funcionalidade | Descrição | Status |
|---|---|---|
| Notas emitidas → contas a receber | Ler as notas emitidas no app (somente leitura) e gerar títulos a receber (`origem = nota`), evitando duplicidade por `origem_ref`. | [PROPOSTA aceita como direção] |
| Guias de impostos → contas a pagar | Ler as guias disponíveis no app (DAS, DARF etc.) e gerar títulos a pagar com o vencimento correto (`origem = guia`). | [PROPOSTA aceita como direção] |
| DRE gerencial | Por competência, a partir do plano de contas (sintéticas como subtotais). | [DECIDIDO como futuro; posicionado na Fase 2 por proposta] |
| Relatórios | Contas a pagar/receber por período, por pessoa, por conta do plano; exportação em PDF/Excel. | [PROPOSTA] |
| Anexos | Comprovantes e boletos anexados a títulos e baixas. | [PROPOSTA] |

### 3.11 Futuro

| Funcionalidade | Descrição | Status |
|---|---|---|
| Conciliação bancária por OFX | Importar extrato OFX e casar com baixas e transferências. | [DECIDIDO como futuro] |
| Notificações de vencimento | Web Push (VAPID, biblioteca `minishlink/web-push`) disparado por cron da Hostinger. No iPhone só funciona com o app instalado na tela inicial e iOS 16.4+. | [DECIDIDO como futuro] |
| Publicação nas lojas | Empacotar a mesma PWA com Capacitor, sem reescrever. Só se houver motivo de marca. | [DECIDIDO como possibilidade] |
| Adaptação à Reforma Tributária | Tratamento de split payment e IBS/CBS no financeiro (seção 4.4). | [PROPOSTA] |
| Integração bancária por API | Boletos, Pix, extrato automático. | [PROPOSTA] |

---

## 4. Regras de negócio fiscais e tributárias

### 4.1 Posição do ERP em relação ao fiscal
**[DECIDIDO]**
- O ERP **não emite** documentos fiscais, **não apura** tributos e **não gera** obrigações acessórias. Tudo isso continua no app e na contabilidade.
- O ERP **não depende do regime tributário** da empresa para funcionar: o controle de pagar, receber e caixa é o mesmo para Simples Nacional, Lucro Presumido, Lucro Real e MEI.
- A dimensão fiscal entra no ERP de duas formas: (1) pelo **plano de contas**, que separa as despesas com tributos; (2) pelas **integrações de leitura** com o app (notas e guias, Fase 2).
- Nenhuma regra de cálculo tributário foi definida para o ERP. **As regras abaixo são de contexto e de integração, não de apuração.**

### 4.2 Regras de integração com notas fiscais (Fase 2) **[PROPOSTA]**
- **Fonte:** notas emitidas no app (NF-e e NFS-e), lidas do banco do app.
- **Geração do título a receber:**
  - Valor: valor líquido a receber da nota (valor total menos retenções destacadas na própria nota, como ISS retido, IRRF, PIS/Cofins/CSLL retidos e INSS retido, quando houver).
  - Pessoa: tomador/destinatário da nota (cadastro compartilhado do grupo).
  - Competência: data de emissão da nota.
  - Vencimento: condição de pagamento informada na nota (duplicatas/parcelas, se existirem); sem condição, vencimento padrão configurável.
  - Conta do plano: conta de receita definida por padrão para o grupo (ou por tipo de serviço/CFOP, a definir).
  - `origem = nota`, `origem_ref` = identificador da nota no app (garante que a mesma nota não gera dois títulos).
- **Cancelamento da nota no app** → o título vinculado é cancelado no ERP, se não tiver baixas; se tiver, gera alerta para tratamento manual.
- **Regras exatas de retenção e de qual valor vira título: [EM ABERTO — depende de como o app guarda as notas e da validação do responsável]**.

### 4.3 Regras de integração com guias de impostos (Fase 2) **[PROPOSTA]**
- **Fonte:** guias disponíveis no app (o que o app guarda precisa ser inspecionado).
- **Geração do título a pagar:** valor da guia, vencimento da guia, competência = período de apuração, conta do plano específica por tributo, `origem = guia`, `origem_ref` = identificador da guia.
- Vencimentos usuais como referência de conferência (a guia gerada pela contabilidade prevalece sempre):
  - **Simples Nacional:** DAS mensal, vencimento no dia 20 do mês seguinte ao da apuração.
  - **MEI:** DAS-MEI mensal, vencimento no dia 20.
  - **Encargos sobre folha:** FGTS Digital (GFD) e DARF previdenciário da DCTFWeb, vencimento no dia 20 do mês seguinte.
  - **Lucro Presumido / Real:** DARFs de IRPJ, CSLL, PIS e Cofins conforme a periodicidade de cada tributo; ICMS e ISS conforme a legislação estadual e municipal do contribuinte.
- Vencimento em dia não útil: segue a regra de cada tributo (antecipa ou prorroga). Por isso **o ERP não calcula vencimento de tributo**, só usa o que vem na guia. **[PROPOSTA]**
- **Quais guias o app tem disponíveis: [EM ABERTO]**.

### 4.4 Reforma Tributária (IBS, CBS e IS) **[PROPOSTA — contexto e preparação]**
O ERP não apura IBS, CBS ou IS. Os impactos relevantes para um sistema financeiro são:

**Cronograma de transição (LC 214/2025), resumido:**
- **2026:** ano de teste, com CBS de 0,9% e IBS de 0,1% destacados nos documentos fiscais, compensáveis com PIS/Cofins.
- **2027:** CBS passa a vigorar plenamente; PIS e Cofins são extintos; início do Imposto Seletivo (IS); IPI zerado, salvo para produtos da Zona Franca de Manaus.
- **2029 a 2032:** redução gradual de ICMS e ISS, com aumento proporcional do IBS.
- **2033:** regime pleno; ICMS e ISS extintos.
- **Simples Nacional:** continua existindo; a partir de 2027 a empresa optante pode escolher recolher IBS/CBS fora do DAS (regime regular), o que afeta a geração de guias e o crédito dos clientes B2B.

**Split payment (pagamento fracionado):**
- Pela informação mais recente da Receita Federal, começa em **2027, de forma opcional e restrita às operações entre empresas (B2B)**, com implantação em fases e obrigatoriedade no B2B prevista para 2028. O cronograma ainda pode mudar.
- **Impacto direto no ERP:** quando houver split payment, o valor que entra na conta bancária será **menor que o valor do título**, porque a parcela de IBS/CBS vai direto ao Fisco no ato do pagamento.
- **Tratamento proposto:**
  - Na baixa de títulos a receber, registrar separadamente o **valor de tributo retido via split** (novo campo na baixa, ex.: `tributo_split`), para que o título seja quitado pelo valor bruto e o caixa reflita o líquido.
  - Na baixa de títulos a pagar, o fornecedor recebe o líquido e o tributo vai ao Fisco, mas o desembolso total da empresa é o mesmo: sem impacto no caixa, apenas informativo.
  - Plano de contas modelo com contas para IBS e CBS (a pagar, retidos e a recuperar).
  - Fluxo projetado considerando o recebimento líquido em operações B2B sujeitas a split.
- **Quando implementar:** acompanhar a regulamentação; estrutura de dados pronta antes de 2028. **[EM ABERTO]**

### 4.5 Obrigações acessórias e SPED
- **Fora do escopo.** O ERP não gera SPED Fiscal, EFD-Contribuições, ECD, ECF, DCTFWeb, EFD-Reinf nem outras obrigações. **[DECIDIDO por escopo]**
- Possível exportação dos movimentos financeiros para a contabilidade (ex.: lançamentos de pagamentos e recebimentos para importação no sistema contábil), reduzindo digitação no escritório. **[PROPOSTA — EM ABERTO]**

---

## 5. Integrações

| Integração | Situação | Detalhe |
|---|---|---|
| **Banco do app ATC Zanardi** | MVP — DECIDIDO | Somente leitura: usuários, senhas (hash), empresas, permissões, CNPJ. Fase 2: notas e guias. Nunca escrita. |
| **Código do app ATC Zanardi** | MVP — DECIDIDO | Pequena alteração: botão "Abrir ERP" e geração do token SSO assinado. |
| **SEFAZ / NF-e / NFS-e** | Não haverá integração direta — DECIDIDO | Emissão e comunicação com SEFAZ e prefeituras ficam no app. O ERP só lê as notas já emitidas (Fase 2). |
| **SPED e obrigações acessórias** | Fora do escopo — DECIDIDO | Ver seção 4.5. |
| **Bancos — OFX** | Futuro — DECIDIDO | Importação de extrato para conciliação. |
| **Bancos — API (boleto, Pix, extrato)** | Futuro — PROPOSTA | Não discutido em detalhe. |
| **Split payment (PSPs/Receita)** | Futuro — PROPOSTA | Ver seção 4.4. |
| **Contabilidade (exportação de lançamentos)** | EM ABERTO | Ver seção 4.5. |
| **E-mail (SMTP Hostinger)** | MVP — DECIDIDO | Convites e recuperação de senha. |
| **Web Push** | Futuro — DECIDIDO | Notificações de vencimento. |

---

## 6. Stack tecnológica e arquitetura

### 6.1 Stack **[DECIDIDO]**
- **Back-end:** PHP 8.x, puro, sem framework e sem Composer por enquanto (deploy simples na Hostinger).
- **Banco:** MySQL 8 (InnoDB, `utf8mb4_unicode_ci`).
- **Front-end:** HTML + CSS próprios, renderizados no servidor; JavaScript mínimo. Visual provisório, sem imagens, com modo claro e escuro automático; a identidade visual oficial do app será aplicada depois, alterando apenas o CSS.
- **Formato:** **web mobile instalável (PWA)**, não app nativo. Motivos: um único código para Android, iPhone e computador; encaixa na stack atual; o redirecionamento a partir do app é natural na web; atualização imediata sem revisão das lojas; sem taxas de loja.
- **Desenvolvimento:** Claude Code, com `CLAUDE.md` na raiz do projeto como fonte das regras.
- **Controle de versão:** Git (repositório privado).

### 6.2 Hospedagem e domínio **[DECIDIDO]**
- **Domínio oficial:** `https://erp.atczanardicontabilidade.com.br`.
- **`https://erp.atczanardi.com.br`:** apenas redirecionamento 301 para o oficial, preservando caminho e parâmetros. Nunca cria sessão. Precisa de SSL ativo (senão quem digita https vê erro antes de ser redirecionado). Se o DNS desse domínio estiver no Cloudflare, pode ser feito com uma Redirect Rule.
- **Hospedagem:** Hostinger, na conta de `atczanardicontabilidade.com.br`, pasta `public_html/erp`.
- **Raiz do subdomínio:** `public_html/erp/public` (só a pasta `public` fica acessível pela web).
- Como `public_html/erp` fica dentro da pasta do site principal, um `.htaccess` em `public_html/erp` **nega todo acesso que não venha pelo subdomínio do ERP**, impedindo que `www.atczanardicontabilidade.com.br/erp/.env` ou o código fiquem expostos.
- HTTPS forçado, HSTS ativo.
- Cookie de sessão sem atributo `Domain` (vale só para o subdomínio oficial), `Secure`, `HttpOnly`, `SameSite=Lax`.
- Links de convite, recuperação de senha e e-mails sempre com o domínio oficial (`APP_URL`).

### 6.3 Estrutura de pastas **[DECIDIDO, implementada]**
```
public_html/erp/
├── public/        raiz do subdomínio (login.php, index.php, logout.php, sso.php, assets, manifest.json, sw.js)
├── src/           código PHP (bootstrap, helpers, Database, AppReader, Auth)
├── database/      schema.sql do banco do ERP
├── bin/           scripts de linha de comando (criar-superadmin.php, cron)
├── storage/logs/  logs
├── .env           credenciais (fora do Git e fora da pasta pública)
├── .htaccess      bloqueio de acesso fora do subdomínio
└── CLAUDE.md      regras do projeto para o Claude Code
```

### 6.4 Bancos de dados **[DECIDIDO, implementado]**
- **Banco do ERP:** `u476271452_erp_atczanardi` (criado na Hostinger). Leitura e escrita. Todas as tabelas com prefixo `fin_`.
- **Banco do app:** somente leitura, com três camadas de proteção:
  1. Se a Hostinger permitir, um usuário MySQL com permissão **apenas de SELECT** no banco do app.
  2. `SET SESSION TRANSACTION READ ONLY` ao abrir a conexão (o MySQL recusa qualquer escrita nessa sessão; testado).
  3. Classe `AppReader`: único ponto de acesso ao banco do app; só aceita um `SELECT` por chamada (testado).
- Duas conexões PDO separadas: `Database::erp()` e `Database::app()`.
- **Não há JOIN entre os bancos:** busca no app, depois no ERP, e cruza em PHP pelos IDs.
- IDs vindos do app (`empresa_id`, `app_user_id`) ficam no ERP **sem chave estrangeira**.
- O mapeamento das tabelas e colunas do app (usuários, login, senha, nome, e-mail, ativo, algoritmo de senha) fica no `.env`, validado por expressão regular antes de entrar em SQL (identificadores não podem ser parâmetros).
- Antes de ler qualquer tabela do app, inspecionar a estrutura real; não adivinhar nomes.

### 6.5 PWA **[DECIDIDO]**
- `manifest.json` com nome, ícones 192 e 512 (incluindo maskable), `display: standalone`, cores de tema, `start_url` e `scope` no domínio oficial.
- **Service worker guarda em cache apenas arquivos estáticos** (CSS, JS, ícones, fontes) e uma página offline simples. **Dados financeiros nunca ficam em cache**: sempre vêm do servidor, para o saldo nunca aparecer desatualizado nem ficar exposto no aparelho.
- Meta tags para iOS e uma tela explicando "Adicionar à Tela de Início" no iPhone.
- Usabilidade mobile: navegação inferior, botões grandes, `inputmode="decimal"` nos valores, máscara de moeda pt-BR, campos de data nativos, campos com fonte de 16 px (evita zoom automático no iPhone).

### 6.6 Ambientes **[DECIDIDO]**
- Desenvolvimento local (PHP + MySQL; no Windows, Laragon como sugestão); nunca editar direto em produção.
- Deploy na Hostinger via Git (hPanel) ou SFTP.
- `APP_ENV=local` ou `production` no `.env` (controla exibição de erros, cookie seguro e verificação de domínio).

---

## 7. Modelo de dados — entidades principais

Todas as tabelas no banco do ERP, com prefixo `fin_`. O arquivo `database/schema.sql` é a referência definitiva.

### 7.1 Diagrama lógico (resumo)
```
fin_grupos 1──N fin_grupo_empresas (empresa_id do app)
fin_grupos 1──N fin_plano_contas (hierárquico; grupo_id NULL = modelo)
fin_grupos 1──N fin_pessoas
empresa    1──N fin_contas_financeiras
empresa    1──N fin_titulos ──N:1 fin_plano_contas / fin_pessoas
fin_titulos 1──N fin_baixas ──N:1 fin_contas_financeiras
fin_transferencias: conta origem (empresa A) → conta destino (empresa A ou B do mesmo grupo)
fin_usuarios 1──N fin_usuario_empresas (perfil por empresa)
fin_usuarios 1──N fin_senha_tokens / fin_acessos_suporte / fin_auditoria
```

### 7.2 Entidades

**`fin_grupos`** — grupo econômico (matriz + filiais).
`id`, `cnpj_raiz` (CHAR 8, único), `nome`, `created_at`.

**`fin_grupo_empresas`** — liga cada empresa do app a um grupo.
`empresa_id` (PK, ID do app), `grupo_id`, `tipo` (`matriz`/`filial`), `apelido`.

**`fin_plano_contas`** — plano de contas gerencial.
`id`, `grupo_id` (NULL = modelo ATC), `conta_pai_id`, `codigo`, `descricao`, `natureza` (`receita`/`despesa`), `analitica`, `ativo`, `created_at`. Único: (`grupo_id`, `codigo`).

**`fin_contas_financeiras`** — caixinha e bancos.
`id`, `empresa_id`, `nome`, `tipo` (`caixa`/`banco`), `banco_codigo`, `agencia`, `conta`, `saldo_inicial` DECIMAL(15,2), `data_saldo_inicial`, `ativo`, `created_at`.

**`fin_pessoas`** — clientes e fornecedores.
`id`, `grupo_id`, `tipo` (`cliente`/`fornecedor`/`ambos`), `nome`, `documento` (CPF/CNPJ só números), `email`, `telefone`, `ativo`, `created_at`.

**`fin_titulos`** — contas a pagar e a receber.
`id`, `empresa_id`, `tipo` (`pagar`/`receber`), `pessoa_id`, `plano_conta_id`, `descricao`, `documento`, `valor` DECIMAL(15,2), `data_competencia`, `data_vencimento`, `status` (`aberto`/`parcial`/`pago`/`cancelado`), `grupo_id` (CHAR 36, série de parcelas/recorrência), `parcela_num`, `parcela_total`, `origem` (`manual`/`nota`/`guia`), `origem_ref`, `observacao`, `created_by`, `created_at`, `updated_at`.

**`fin_baixas`** — pagamentos e recebimentos.
`id`, `empresa_id`, `titulo_id`, `conta_financeira_id`, `data_pagamento`, `valor_principal`, `juros`, `multa`, `desconto`, `valor_total`, `estornada`, `observacao`, `created_by`, `created_at`.
Futuro: `tributo_split` **[PROPOSTA, seção 4.4]**.

**`fin_transferencias`** — movimentos entre contas.
`id`, `empresa_origem_id`, `empresa_destino_id`, `conta_origem_id`, `conta_destino_id`, `data`, `valor`, `descricao`, `estornada`, `created_by`, `created_at`.

**`fin_usuarios`** — identidade única do ERP.
`id`, `origem` (`app`/`erp`), `app_user_id` (único), `nome`, `email` (obrigatório para origem erp; único por origem), `senha_hash` (NULL para origem app), `tipo_conta` (`cliente`/`equipe_atc`), `perfil_equipe` (`suporte_leitura`/`suporte_edicao`/`superadmin`), `totp_secret`, `ativo`, `ultimo_login_at`, `created_by`, `created_at`, `updated_at`.

**`fin_usuario_empresas`** — acesso dos usuários só do ERP.
(`usuario_id`, `empresa_id`) PK, `perfil` (`admin`/`financeiro`/`consulta`), `created_by`, `created_at`.

**`fin_login_tentativas`** — proteção contra força bruta.
`id`, `email` (chave `modo:login`), `ip`, `sucesso`, `created_at`.

**`fin_senha_tokens`** — convites e recuperação de senha.
`token_hash` (SHA-256), `usuario_id`, `tipo` (`convite`/`reset`), `expires_at`, `used_at`, `created_at`.

**`fin_sso_usados`** — tokens SSO já consumidos.
`jti` (PK), `app_user_id`, `expires_at`, `used_at`.

**`fin_auditoria`** — histórico de ações.
`id`, `usuario_id`, `empresa_id`, `acao`, `entidade`, `entidade_id`, `dados` (JSON antes/depois), `ip`, `created_at`.

**`fin_acessos_suporte`** — entradas da equipe ATC em empresas clientes.
`id`, `usuario_id`, `empresa_id`, `motivo`, `inicio_at`, `fim_at`, `ip`.

### 7.3 Regras de dados **[DECIDIDO]**
- Valores monetários sempre DECIMAL(15,2); nunca float. Cálculos em PHP com bcmath ou em centavos inteiros.
- Datas em DATE/DATETIME, fuso America/Sao_Paulo.
- Sem exclusão física de títulos, baixas e transferências (cancelamento e estorno).
- Dados de movimento têm `empresa_id`; cadastros compartilhados têm `grupo_id`.

---

## 8. Requisitos não funcionais

### 8.1 Multiempresa e isolamento **[DECIDIDO]**
- Toda leitura filtra por `empresa_id IN (empresas da sessão)` ou `grupo_id = grupo da sessão`.
- A lista de empresas vem **sempre da sessão**, montada no servidor. Nunca aceitar `empresa_id` ou `grupo_id` vindo da requisição sem conferir que pertence à lista permitida.
- Como o MySQL não tem Row Level Security, o isolamento é responsabilidade do código; toda funcionalidade nova deve ser revisada contra essa regra.

### 8.2 Segurança **[DECIDIDO]**
- SQL sempre com PDO e prepared statements; sem concatenação de variáveis.
- CSRF em todos os formulários; saída HTML sempre escapada.
- Senhas com Argon2id; nunca armazenar senha em texto; ninguém define senha de outra pessoa.
- Força bruta: 5 falhas por login / 20 por IP em 15 minutos.
- 2FA obrigatório para a equipe ATC.
- Tokens (convite, reset, SSO) de uso único, com validade curta; guardados como hash (convite/reset) ou com `jti` consumido (SSO).
- Sessão: regeneração no login, expiração por inatividade, cookie `Secure`/`HttpOnly`/`SameSite=Lax`.
- Cabeçalhos: HSTS, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Content-Security-Policy` restritiva.
- Credenciais apenas no `.env`, fora da pasta pública e fora do Git.
- Banco do app em somente leitura (três camadas, seção 6.4).
- Logs de erro em `storage/logs`, sem exibir erros em produção.
- Mensagens de erro ao usuário sem detalhes técnicos.

### 8.3 LGPD **[DECIDIDO como necessidade; texto jurídico EM ABERTO]**
- A ATC Zanardi atua como **operadora** dos dados financeiros dos clientes no ERP; o acesso da equipe para suporte deve estar previsto **em contrato ou nos termos de uso** do ERP.
- `fin_acessos_suporte` e `fin_auditoria` comprovam que o acesso da equipe foi feito para suporte.
- Dados pessoais tratados: nome, e-mail, telefone e CPF/CNPJ de usuários, clientes e fornecedores.
- **[PROPOSTA]** política de retenção de logs (ex.: tentativas de login por 90 dias), exportação dos dados do cliente quando solicitado e exclusão/anonimização ao fim do contrato.

### 8.4 Performance **[PROPOSTA]**
- Índices já previstos para as consultas principais (vencimento, competência, data de pagamento, empresa).
- Paginação em todas as listas.
- Saldos calculados por consulta no MVP; se o volume crescer, saldo consolidado por dia/mês.
- Páginas leves (sem bibliotecas pesadas), adequadas a conexão móvel.

### 8.5 Usabilidade e acessibilidade **[DECIDIDO/PROPOSTA]**
- Mobile first; utilizável com uma mão (navegação inferior).
- Foco de teclado visível, contraste adequado, modo claro e escuro automático.
- Linguagem simples, voltada ao empresário, não ao contador.

### 8.6 Disponibilidade e backup **[PROPOSTA]**
- Backups automáticos da Hostinger mais exportação periódica do banco do ERP.
- Rotina de limpeza (cron) para tokens expirados e tentativas de login antigas.

---

## 9. Roadmap e fases de entrega

### Etapa 0 — Base (concluída)
- [x] Definição de escopo, `CLAUDE.md` e `schema.sql`.
- [x] Banco do ERP criado na Hostinger.
- [x] Estrutura do projeto, conexões separadas (ERP e app somente leitura), `AppReader`.
- [x] Tela de login com as duas formas de acesso, proteção contra força bruta, CSRF e sessão — **testada localmente**.

### MVP
1. Importar o `schema.sql` no banco do ERP e montar o seed do plano de contas modelo (depende do envio do plano).
2. Mapear o app: tabela e colunas de usuários, algoritmo de senha, empresas, CNPJ, vínculo usuário-empresa, identificação da equipe ATC. Preencher o `.env`.
3. Seleção de empresa, montagem automática dos grupos pelo CNPJ raiz, seletor individual/consolidado.
4. Usuários: convites, definir senha, esqueci minha senha, perfis, gestão pelo cliente e pela equipe; script do primeiro superadmin; 2FA da equipe; busca de empresas e modo suporte.
5. Base PWA (manifest, service worker, layout mobile com navegação inferior).
6. Cadastros: contas financeiras, clientes/fornecedores, plano de contas do grupo.
7. Contas a pagar com baixa (parcial, juros, multa, desconto, estorno), parcelamento e recorrência.
8. Contas a receber (mesma lógica).
9. Transferências (mesma empresa e matriz ↔ filial).
10. Fluxo de caixa realizado e projetado, saldos por conta, painel inicial.
11. SSO a partir do app (botão "Abrir ERP" no código do app).
12. Deploy em produção: subdomínio, SSL, redirecionamento do domínio secundário, `.htaccess`.

### Fase 2
- Notas emitidas no app → contas a receber.
- Guias de impostos do app → contas a pagar.
- DRE gerencial por competência.
- Relatórios e exportações; anexos.
- Identidade visual oficial do app.

### Futuro
- Conciliação bancária por OFX.
- Notificações de vencimento (Web Push).
- Adaptação a split payment e IBS/CBS.
- Integração bancária por API.
- Exportação de lançamentos para a contabilidade.
- Publicação nas lojas via Capacitor (se necessário).

---

## 10. Decisões tomadas e pontos em aberto

### 10.1 Decisões tomadas
1. ERP financeiro simples: contas a pagar, contas a receber, fluxo de caixa.
2. Emissão de notas sempre no app existente, nunca no ERP.
3. Cadastros: plano de contas (modelo próprio da ATC), caixinha, contas bancárias, categorias, clientes/fornecedores.
4. Caixinha e bancos na mesma entidade (`contas financeiras`).
5. Título separado da baixa; três datas (competência, vencimento, pagamento); pagamento parcial com juros, multa e desconto.
6. Transferências não são receita nem despesa.
7. Cancelamento e estorno em vez de exclusão.
8. Formato web mobile / PWA, não app nativo.
9. Stack PHP 8 puro + MySQL 8 na Hostinger.
10. Banco do ERP separado (`u476271452_erp_atczanardi`); banco do app somente leitura, sem nenhuma alteração.
11. Domínio oficial `erp.atczanardicontabilidade.com.br`; `erp.atczanardi.com.br` redireciona; arquivos em `public_html/erp`, raiz pública em `public_html/erp/public`.
12. Matriz e filiais: controle por estabelecimento, com visão consolidada opcional; grupo por CNPJ raiz; plano de contas e pessoas compartilhados pelo grupo.
13. Login com conta do app (validada lendo o banco do app) ou com usuário e senha exclusivos do ERP.
14. SSO a partir do app com token HMAC assinado (alteração só no código do app).
15. Usuários só do ERP criados por convite, pela equipe ATC e pelo próprio cliente.
16. Equipe ATC com acesso a qualquer empresa para suporte, em três perfis, com 2FA obrigatório, registro de acessos e faixa de modo suporte.
17. Auditoria de gestão de usuários e de ações da equipe.
18. Desenvolvimento no Claude Code, com `CLAUDE.md` como fonte das regras.

### 10.2 Pontos em aberto
| # | Ponto | Responsável / quando |
|---|---|---|
| 1 | Envio do plano de contas modelo para montar o seed. | Responsável do projeto — segunda-feira, 09/11/2026. |
| 2 | Categoria = conta analítica do plano, ou cadastro separado/etiqueta? | Responsável do projeto. |
| 3 | Estrutura do app: tabela e colunas de usuários, algoritmo de senha, empresas, CNPJ, vínculo usuário-empresa. | Inspeção pelo Claude Code. |
| 4 | Como o app identifica a equipe ATC. | Inspeção pelo Claude Code. |
| 5 | Se a Hostinger permite usuário MySQL só com SELECT no banco do app. | Verificar no hPanel. |
| 6 | **Trocar a senha do usuário do banco do ERP**, que foi compartilhada em conversa. | Responsável do projeto — antes de preencher o `.env`. |
| 7 | Nível de permissão, no ERP, do cliente que entra pela conta do app (assumido: admin das próprias empresas). | Responsável do projeto. |
| 8 | Tratamento do desconto na quitação do título. | Responsável do projeto. |
| 9 | Recorrência indeterminada: gerar N meses à frente ou rotina mensal. | Construção. |
| 10 | Validade do convite (sugerido 72 h). | Construção. |
| 11 | Biblioteca de 2FA (TOTP). | Construção. |
| 12 | Reaproveitamento do cadastro de tomadores do app em `fin_pessoas` (importar ou sincronizar). | Construção. |
| 13 | Regras exatas de integração de notas (retenções, conta do plano, vencimento padrão) e quais guias o app disponibiliza. | Fase 2. |
| 14 | Tratamento de split payment e IBS/CBS no financeiro. | Acompanhar regulamentação; antes de 2028. |
| 15 | Exportação de lançamentos para o sistema contábil. | Fase 2 ou futuro. |
| 16 | Termos de uso / contrato prevendo o acesso da equipe (LGPD) e política de retenção de dados. | Responsável do projeto com apoio jurídico. |
| 17 | Limite de usuários por cliente e eventual cobrança do ERP. | Não discutido. |
| 18 | Identidade visual oficial (cores, logo, tipografia do app). | Fase 2. |

---

## Referências
- Cronograma do split payment (Receita Federal, via imprensa especializada):
  - https://www.sindifisco-ms.org.br/reforma-tributaria-split-payment-fica-para-2027-e-sera-opcional-e-restrito-ao-b2b/
  - https://amdjus.com.br/split-payment-na-reforma-tributaria-implementacao-gradual-ate-2028/
  - https://www.contabeis.com.br/noticias/78421/split-payment-tera-testes-em-2026-mas-implantacao-sera-gradual/
- Lei Complementar nº 214/2025 (IBS, CBS e IS).
- Arquivos do projeto: `CLAUDE.md`, `database/schema.sql`, `README.md`.
