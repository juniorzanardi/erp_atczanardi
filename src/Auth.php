<?php
declare(strict_types=1);

final class Auth
{
    private const MAX_FALHAS_LOGIN = 5;
    private const MAX_FALHAS_IP    = 20;
    private const JANELA_MIN       = 15;

    private const ERRO_CREDENCIAIS = 'Usuário ou senha incorretos.';
    private const ERRO_BLOQUEIO    = 'Muitas tentativas sem sucesso. Aguarde 15 minutos e tente de novo.';
    private const ERRO_INDISPONIVEL = 'Não foi possível entrar agora. Tente novamente em alguns minutos.';

    /**
     * @param string $modo 'app' (conta do app ATC Zanardi) ou 'erp' (acesso exclusivo do ERP)
     * @return array{ok: bool, erro?: string}
     */
    public static function tentarLogin(string $modo, string $login, string $senha, string $ip): array
    {
        $login = mb_strtolower(trim($login));
        if ($login === '' || $senha === '') {
            return ['ok' => false, 'erro' => 'Preencha usuário e senha.'];
        }
        $chave = $modo . ':' . $login;

        try {
            if (self::bloqueado($chave, $ip)) {
                return ['ok' => false, 'erro' => self::ERRO_BLOQUEIO];
            }

            $usuario = $modo === 'app'
                ? self::autenticarPeloApp($login, $senha)
                : self::autenticarPeloErp($login, $senha);

            self::registrarTentativa($chave, $ip, $usuario !== null);

            if ($usuario === null) {
                return ['ok' => false, 'erro' => self::ERRO_CREDENCIAIS];
            }

            self::iniciarSessao($usuario);
            return ['ok' => true];
        } catch (Throwable $ex) {
            error_log('[login] ' . $ex->getMessage());
            return ['ok' => false, 'erro' => self::ERRO_INDISPONIVEL];
        }
    }

    public static function sair(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ---------------------------------------------------------------- acesso exclusivo do ERP

    private static function autenticarPeloErp(string $email, string $senha): ?array
    {
        $stmt = Database::erp()->prepare(
            "SELECT id, nome, email, senha_hash, ativo, tipo_conta, perfil_equipe, app_user_id, origem
               FROM fin_usuarios WHERE origem = 'erp' AND email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if (!$u || empty($u['senha_hash'])) {
            self::tempoConstante($senha);
            return null;
        }
        if (!password_verify($senha, $u['senha_hash']) || (int) $u['ativo'] !== 1) {
            return null;
        }
        if (password_needs_rehash($u['senha_hash'], self::algoritmo())) {
            Database::erp()->prepare('UPDATE fin_usuarios SET senha_hash = ? WHERE id = ?')
                ->execute([password_hash($senha, self::algoritmo()), $u['id']]);
        }
        unset($u['senha_hash']);
        return $u;
    }

    // ---------------------------------------------------------------- conta do app (somente leitura)

    private static function autenticarPeloApp(string $login, string $senha): ?array
    {
        $t      = AppReader::identificador((string) env('APP_USERS_TABLE'));
        $cId    = AppReader::identificador((string) env('APP_COL_ID'));
        $cLogin = AppReader::identificador((string) env('APP_COL_LOGIN'));
        $cNome  = AppReader::identificador((string) env('APP_COL_NAME'));
        $cEmail = AppReader::identificador((string) env('APP_COL_EMAIL'));
        $cSenha = AppReader::identificador((string) env('APP_COL_PASSWORD'));
        $ativo  = env('APP_COL_ACTIVE');
        $cAtivo = $ativo ? AppReader::identificador($ativo) : null;

        $sql = "SELECT {$cId} AS id, {$cNome} AS nome, {$cEmail} AS email, {$cSenha} AS senha"
             . ($cAtivo ? ", {$cAtivo} AS ativo" : '')
             . " FROM {$t} WHERE LOWER({$cLogin}) = ? LIMIT 1";
        $appUser = AppReader::buscarUm($sql, [$login]);

        if (!$appUser || empty($appUser['senha'])) {
            self::tempoConstante($senha);
            return null;
        }
        if (!self::senhaDoAppConfere($senha, (string) $appUser['senha'])) {
            return null;
        }
        if ($cAtivo && (int) $appUser['ativo'] !== 1) {
            return null;
        }

        // Identidade no banco do ERP (o banco do app não é alterado).
        $erp = Database::erp();
        $stmt = $erp->prepare(
            "SELECT id, nome, email, ativo, tipo_conta, perfil_equipe, app_user_id, origem
               FROM fin_usuarios WHERE origem = 'app' AND app_user_id = ? LIMIT 1"
        );
        $stmt->execute([(int) $appUser['id']]);
        $u = $stmt->fetch();

        if (!$u) {
            $erp->prepare("INSERT INTO fin_usuarios (origem, app_user_id, nome, email) VALUES ('app', ?, ?, ?)")
                ->execute([(int) $appUser['id'], $appUser['nome'], $appUser['email'] ?: null]);
            $stmt->execute([(int) $appUser['id']]);
            $u = $stmt->fetch();
        } elseif ($u['nome'] !== $appUser['nome'] || $u['email'] !== $appUser['email']) {
            // Mantém nome e e-mail iguais aos do app.
            $erp->prepare('UPDATE fin_usuarios SET nome = ?, email = ? WHERE id = ?')
                ->execute([$appUser['nome'], $appUser['email'] ?: null, $u['id']]);
            $u['nome'] = $appUser['nome'];
            $u['email'] = $appUser['email'];
        }

        // Acesso ao ERP desativado para este usuário do app.
        return (int) $u['ativo'] === 1 ? $u : null;
    }

    private static function senhaDoAppConfere(string $senha, string $hash): bool
    {
        return match (env('APP_PASSWORD_ALGO', 'password_hash')) {
            'password_hash' => password_verify($senha, $hash),
            // Algoritmos antigos: só se o app ainda gravar assim. Recomendado migrar o app para password_hash.
            'md5'  => hash_equals(strtolower($hash), md5($senha)),
            'sha1' => hash_equals(strtolower($hash), sha1($senha)),
            default => false,
        };
    }

    // ---------------------------------------------------------------- sessão e proteção

    private static function iniciarSessao(array $u): void
    {
        session_regenerate_id(true);
        $_SESSION = [
            'usuario' => [
                'id'            => (int) $u['id'],
                'nome'          => $u['nome'],
                'email'         => $u['email'],
                'origem'        => $u['origem'],
                'app_user_id'   => $u['app_user_id'] !== null ? (int) $u['app_user_id'] : null,
                'tipo_conta'    => $u['tipo_conta'],
                'perfil_equipe' => $u['perfil_equipe'],
            ],
            'ultimo_acesso' => time(),
            // Próxima etapa: grupo_id, empresas_permitidas e empresas_visiveis.
            // Próxima etapa: equipe_atc passa pelo 2FA antes de liberar o acesso.
        ];
        Database::erp()->prepare('UPDATE fin_usuarios SET ultimo_login_at = NOW() WHERE id = ?')
            ->execute([$u['id']]);
    }

    private static function bloqueado(string $chave, string $ip): bool
    {
        $stmt = Database::erp()->prepare(
            'SELECT
                SUM(email = ?) AS falhas_login,
                SUM(ip = ?)    AS falhas_ip
               FROM fin_login_tentativas
              WHERE sucesso = 0 AND created_at >= NOW() - INTERVAL ' . self::JANELA_MIN . ' MINUTE
                AND (email = ? OR ip = ?)'
        );
        $stmt->execute([$chave, $ip, $chave, $ip]);
        $r = $stmt->fetch();
        return (int) $r['falhas_login'] >= self::MAX_FALHAS_LOGIN
            || (int) $r['falhas_ip'] >= self::MAX_FALHAS_IP;
    }

    private static function registrarTentativa(string $chave, string $ip, bool $sucesso): void
    {
        Database::erp()->prepare('INSERT INTO fin_login_tentativas (email, ip, sucesso) VALUES (?, ?, ?)')
            ->execute([mb_substr($chave, 0, 150), $ip, $sucesso ? 1 : 0]);
    }

    /** Gasta o mesmo tempo de um login real quando o usuário não existe (não revela quais usuários existem). */
    private static function tempoConstante(string $senha): void
    {
        static $hashFicticio = null;
        $hashFicticio ??= password_hash('senha-ficticia', self::algoritmo());
        password_verify($senha, $hashFicticio);
    }

    private static function algoritmo(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }
}
