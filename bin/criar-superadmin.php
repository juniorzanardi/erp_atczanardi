<?php
declare(strict_types=1);

/**
 * Cria o PRIMEIRO superadmin da equipe ATC Zanardi.
 * Uso (no terminal/SSH, dentro da pasta do ERP):  php bin/criar-superadmin.php
 *
 * Só funciona enquanto não existir nenhum superadmin. Os próximos membros da equipe
 * serão cadastrados pela tela de gestão da equipe, por um superadmin logado.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

function perguntar(string $rotulo): string
{
    echo $rotulo;
    return trim((string) fgets(STDIN));
}

function perguntar_senha(string $rotulo): string
{
    echo $rotulo;
    $ocultar = DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec') && @shell_exec('stty -echo 2>/dev/null') !== false;
    $senha = rtrim((string) fgets(STDIN), "\r\n");
    if ($ocultar) {
        @shell_exec('stty echo 2>/dev/null');
        echo PHP_EOL;
    }
    return $senha;
}

function falhar(string $msg): never
{
    fwrite(STDERR, "Erro: {$msg}" . PHP_EOL);
    exit(1);
}

$db = Database::erp();

$existe = $db->query(
    "SELECT COUNT(*) FROM fin_usuarios WHERE tipo_conta = 'equipe_atc' AND perfil_equipe = 'superadmin'"
)->fetchColumn();
if ((int) $existe > 0) {
    falhar('já existe um superadmin. Os demais usuários da equipe são criados pela tela do sistema.');
}

echo "== Criar o primeiro superadmin da equipe ATC Zanardi ==" . PHP_EOL;

$nome = perguntar('Nome completo: ');
if (mb_strlen($nome) < 3) {
    falhar('informe o nome completo.');
}

$email = mb_strtolower(perguntar('E-mail (será o login): '));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    falhar('e-mail inválido.');
}

$senha = perguntar_senha('Senha (mínimo 8 caracteres): ');
if (mb_strlen($senha) < 8) {
    falhar('a senha precisa ter pelo menos 8 caracteres.');
}
if (perguntar_senha('Repita a senha: ') !== $senha) {
    falhar('as senhas não conferem.');
}

$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

try {
    $db->prepare(
        "INSERT INTO fin_usuarios (origem, nome, email, senha_hash, tipo_conta, perfil_equipe, ativo)
         VALUES ('erp', ?, ?, ?, 'equipe_atc', 'superadmin', 1)"
    )->execute([$nome, $email, password_hash($senha, $algo)]);
} catch (PDOException $ex) {
    falhar(str_contains($ex->getMessage(), 'Duplicate') ? 'já existe um usuário do ERP com esse e-mail.' : $ex->getMessage());
}

$id = (int) $db->lastInsertId();
$db->prepare(
    "INSERT INTO fin_auditoria (usuario_id, acao, entidade, entidade_id, dados)
     VALUES (?, 'superadmin_criado_cli', 'usuario', ?, ?)"
)->execute([$id, $id, json_encode(['nome' => $nome, 'email' => $email], JSON_UNESCAPED_UNICODE)]);

echo PHP_EOL . "Superadmin criado (id {$id}). Entre no ERP em \"Acesso exclusivo do ERP\" com {$email}." . PHP_EOL;
