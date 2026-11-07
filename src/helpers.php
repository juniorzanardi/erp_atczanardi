<?php
declare(strict_types=1);

function carregar_env(string $arquivo): void
{
    if (!is_file($arquivo)) {
        return;
    }
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        $linha = trim($linha);
        if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
            continue;
        }
        [$chave, $valor] = array_map('trim', explode('=', $linha, 2));
        $valor = trim(preg_replace('/(^|\s+)#.*$/', '', $valor), "\"'");
        $_ENV[$chave] = $valor;
    }
}

function env(string $chave, ?string $padrao = null): ?string
{
    $v = $_ENV[$chave] ?? null;
    return ($v === null || $v === '') ? $padrao : $v;
}

/** Escapa texto para HTML. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirecionar(string $caminho): never
{
    header('Location: ' . $caminho, true, 303);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valido(): bool
{
    $enviado = $_POST['csrf'] ?? '';
    return is_string($enviado) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $enviado);
}

function ip_cliente(): string
{
    if (env('TRUST_CLOUDFLARE') === 'true' && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function usuario_logado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function exigir_login(): array
{
    $u = usuario_logado();
    if (!$u) {
        redirecionar('/login.php');
    }
    return $u;
}
