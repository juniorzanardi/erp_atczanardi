<?php
declare(strict_types=1);

/**
 * Único ponto de acesso ao banco do app ATC Zanardi.
 * Só executa SELECT. Qualquer outra instrução é recusada antes de chegar ao MySQL.
 */
final class AppReader
{
    public static function buscarUm(string $sql, array $params = []): ?array
    {
        $linha = self::executar($sql, $params)->fetch();
        return $linha === false ? null : $linha;
    }

    public static function buscarTodos(string $sql, array $params = []): array
    {
        return self::executar($sql, $params)->fetchAll();
    }

    private static function executar(string $sql, array $params): PDOStatement
    {
        $limpo = ltrim($sql);
        if (!preg_match('/^SELECT\s/i', $limpo) || str_contains($limpo, ';')) {
            throw new LogicException('AppReader aceita apenas um SELECT por chamada.');
        }
        $stmt = Database::app()->prepare($limpo);
        $stmt->execute($params);
        return $stmt;
    }

    /** Nome de tabela/coluna vindo do .env: só letras, números e _ (não dá para usar parâmetro em identificador). */
    public static function identificador(string $nome): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $nome)) {
            throw new InvalidArgumentException("Identificador inválido no mapeamento do app: {$nome}");
        }
        return "`{$nome}`";
    }
}
