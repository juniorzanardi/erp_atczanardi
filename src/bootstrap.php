<?php
declare(strict_types=1);

define('ERP_ROOT', dirname(__DIR__));

require ERP_ROOT . '/src/helpers.php';
require ERP_ROOT . '/src/Database.php';
require ERP_ROOT . '/src/AppReader.php';
require ERP_ROOT . '/src/Auth.php';

carregar_env(ERP_ROOT . '/.env');

date_default_timezone_set('America/Sao_Paulo');
$producao = env('APP_ENV') === 'production';

error_reporting(E_ALL);
ini_set('display_errors', $producao ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', ERP_ROOT . '/storage/logs/php-error.log');

// Scripts de linha de comando (bin/) não usam domínio, sessão nem cookies.
if (PHP_SAPI === 'cli') {
    return;
}

// Em produção, só atende pelo domínio oficial (o de redirecionamento nunca cria sessão).
if ($producao && ($_SERVER['HTTP_HOST'] ?? '') !== env('APP_HOST')) {
    header('Location: ' . rtrim((string) env('APP_URL'), '/') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

// Sessão
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('ERPSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $producao,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Expira a sessão por inatividade
$timeout = (int) env('SESSION_TIMEOUT_MIN', '120') * 60;
if (isset($_SESSION['ultimo_acesso']) && time() - $_SESSION['ultimo_acesso'] > $timeout) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['aviso'] = 'Sua sessão expirou. Entre novamente.';
}
if (isset($_SESSION['usuario'])) {
    $_SESSION['ultimo_acesso'] = time();
}
