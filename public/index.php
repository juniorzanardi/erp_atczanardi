<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$u = exigir_login();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>ERP Financeiro ATC Zanardi</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<main class="painel">
    <h1>Olá, <?= e(explode(' ', (string) $u['nome'])[0]) ?></h1>
    <p>Login concluído. A escolha da empresa e o painel financeiro vêm nas próximas etapas.</p>

    <form method="post" action="/logout.php">
        <?= csrf_campo() ?>
        <button type="submit" class="botao-secundario">Sair</button>
    </form>
</main>
</body>
</html>
