<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (usuario_logado()) {
    redirecionar('/');
}

$modo  = 'app';
$login = '';
$erro  = $_SESSION['aviso'] ?? null;
unset($_SESSION['aviso']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modo  = ($_POST['modo'] ?? '') === 'erp' ? 'erp' : 'app';
    $login = trim((string) ($_POST['login'] ?? ''));

    if (!csrf_valido()) {
        $erro = 'A página ficou aberta por muito tempo. Tente entrar de novo.';
    } else {
        $r = Auth::tentarLogin($modo, $login, (string) ($_POST['senha'] ?? ''), ip_cliente());
        if ($r['ok']) {
            redirecionar('/');
        }
        $erro = $r['erro'];
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Entrar · ERP Financeiro ATC Zanardi</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="tela-login">
<main class="login">
    <header class="login-cabecalho">
        <h1>ERP Financeiro</h1>
        <p>ATC Zanardi Contabilidade</p>
    </header>

    <form method="post" action="/login.php" class="login-form">
        <?= csrf_campo() ?>

        <fieldset class="modo">
            <legend>Como você quer entrar?</legend>
            <div class="modo-opcoes">
                <input type="radio" id="modo-app" name="modo" value="app" <?= $modo === 'app' ? 'checked' : '' ?>>
                <label for="modo-app">Conta do app ATC Zanardi</label>

                <input type="radio" id="modo-erp" name="modo" value="erp" <?= $modo === 'erp' ? 'checked' : '' ?>>
                <label for="modo-erp">Acesso exclusivo do ERP</label>
            </div>
            <p class="dica dica-app">Use o mesmo login e senha do app da contabilidade.</p>
            <p class="dica dica-erp">Use o e-mail e a senha que você criou pelo convite do ERP.</p>
        </fieldset>

        <?php if ($erro): ?>
            <p class="alerta" role="alert"><?= e($erro) ?></p>
        <?php endif; ?>

        <label class="campo">
            <span>Usuário ou e-mail</span>
            <input type="text" name="login" value="<?= e($login) ?>" autocomplete="username"
                   autocapitalize="none" spellcheck="false" required <?= $login === '' ? 'autofocus' : '' ?>>
        </label>

        <label class="campo">
            <span>Senha</span>
            <input type="password" name="senha" autocomplete="current-password" required
                   <?= $login !== '' ? 'autofocus' : '' ?>>
        </label>

        <button type="submit" class="botao-principal">Entrar</button>
    </form>

    <p class="rodape-login">
        Esqueceu a senha do app? Recupere pelo próprio app ATC Zanardi.
    </p>
</main>
</body>
</html>
