<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $erp = null;
    private static ?PDO $app = null;

    /** Banco próprio do ERP: leitura e escrita. */
    public static function erp(): PDO
    {
        return self::$erp ??= self::conectar('ERP');
    }

    /**
     * Banco do app ATC Zanardi: SOMENTE LEITURA.
     * Não use diretamente: acesse sempre pela classe AppReader.
     */
    public static function app(): PDO
    {
        if (self::$app === null) {
            $pdo = self::conectar('APP');
            // O MySQL passa a recusar qualquer escrita nesta conexão.
            $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            self::$app = $pdo;
        }
        return self::$app;
    }

    private static function conectar(string $prefixo): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            env("{$prefixo}_DB_HOST", 'localhost'),
            env("{$prefixo}_DB_NAME", '')
        );
        $pdo = new PDO($dsn, env("{$prefixo}_DB_USER", ''), env("{$prefixo}_DB_PASS", ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '-03:00'");
        return $pdo;
    }
}
