<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    Auth::sair();
}
redirecionar('/login.php');
