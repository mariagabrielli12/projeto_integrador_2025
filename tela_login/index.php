<?php

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) {
    ini_set('session.cookie_secure', 1);
}

// Cabeçalhos de segurança HTTP
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header("Referrer-Policy: no-referrer");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; script-src 'self' https://cdnjs.cloudflare.com;");
session_start();

// Se o utilizador já estiver logado, redireciona.
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $perfil = $_SESSION["perfil"];
    switch ($perfil) {
        case 'Diretor': header("Location: ../visao_do_diretor/index.php"); break;
        case 'Secretario': header("Location: ../visao_secretario/Telas_Secretario/index.php"); break;
        case 'Professor': header("Location: ../Visao_do_Professor/tela_principal_professor.php"); break;
        case 'Bercarista': header("Location: ../visao_bercarista/index.php"); break;
        case 'Responsavel': header("Location: ../Visao_do_responsavel/index.php"); break;
    }
    exit;
}

// Se o usuário estiver no meio de uma verificação 2FA, manda ele para a tela certa
if (isset($_SESSION['2fa_verifying_user_id'])) {
    header("Location: verificar_2fa.php");
    exit;
}

require_once '../conexao.php';
$login_err = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {
   
    $matricula = trim($_POST['matricula']);
    $senha = $_POST['senha'];


    if (empty($matricula) || empty($senha) || strlen($matricula) > 50) {
        $login_err = "Dados inválidos.";
    } else {
        $sql = "SELECT id_usuario, nome_completo, matricula, senha_hash, id_tipo, 
                       2fa_enabled, 2fa_last_verified_at 
                FROM usuarios WHERE matricula = ?";

        if ($stmt = $conexao->prepare($sql)) {
            $stmt->bind_param("s", $matricula);

            try {
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $usuario = $result->fetch_assoc();

                    if (md5($senha) === $usuario['senha_hash']) {

                        // --- LÓGICA DE 2FA A CADA 30 DIAS ---
                        if ($usuario['2fa_enabled'] == 0) {
                            // NUNCA ATIVOU O 2FA → vai para ativação
                            $_SESSION['2fa_verifying_user_id'] = $usuario['id_usuario'];
                            header("Location: habilitar_2fa.php");
                            exit;
                        } else {
                            // JÁ ATIVOU: checa validade (30 dias)
                            $last_check_time = strtotime($usuario['2fa_last_verified_at']);
                            $thirty_days_ago = time() - (30 * 24 * 60 * 60);

                            if (empty($usuario['2fa_last_verified_at']) || $last_check_time < $thirty_days_ago) {
                                // Precisa verificar novamente
                                $_SESSION['2fa_verifying_user_id'] = $usuario['id_usuario'];
                                header("Location: verificar_2fa.php");
                                exit;
                            } else {
                                // Ainda válido → login direto
                                session_regenerate_id(true);
                                $_SESSION["loggedin"] = true;
                                $_SESSION["id_usuario"] = $usuario['id_usuario'];
                                $_SESSION["nome_completo"] = $usuario['nome_completo'];
                                $_SESSION["matricula"] = $usuario['matricula'];

                                $stmt_tipo = $conexao->prepare("SELECT nome_tipo FROM tipos_usuario WHERE id_tipo = ?");
                                $stmt_tipo->bind_param("i", $usuario['id_tipo']);
                                $stmt_tipo->execute();
                                $perfil_row = $stmt_tipo->get_result()->fetch_assoc();
                                $stmt_tipo->close();

                                $_SESSION["perfil"] = $perfil_row['nome_tipo'] ?? 'Usuario';

                                switch ($_SESSION["perfil"]) {
                                    case 'Diretor': header("Location: ../visao_do_diretor/index.php"); break;
                                    case 'Secretario': header("Location: ../visao_secretario/Telas_Secretario/index.php"); break;
                                    case 'Professor': header("Location: ../Visao_do_Professor/tela_principal_professor.php"); break;
                                    case 'Bercarista': header("Location: ../visao_bercarista/index.php"); break;
                                    case 'Responsavel': header("Location: ../Visao_do_responsavel/index.php"); break;
                                    default: $login_err = "Perfil de utilizador desconhecido."; break;
                                }
                                exit;
                            }
                        }
                    } else {
                        $login_err = "Matrícula ou senha inválida.";
                    }
                } else {
                    $login_err = "Matrícula ou senha inválida.";
                }
            } catch (Exception $e) {
                error_log("Erro no login: " . $e->getMessage());
                $login_err = "Ops! Erro interno. Tente novamente mais tarde.";
            }
            $stmt->close();
        }
        $conexao->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mundo Mágico</title>
    <link rel="stylesheet" href="CSS_login/style_login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-graduation-cap" style="font-size: 3em; color: white;"></i>
        </div>
        <h1>Mundo Mágico</h1>
        <h2>Sistema Educacional Unificado</h2>
        
        <?php if (!empty($login_err)): ?>
            <div style="color: #c62828; background-color: #ffcdd2; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?= htmlspecialchars($login_err, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8'); ?>" method="post" autocomplete="off">
            <div class="input-group">
                <label for="matricula"> Matrícula ou Utilizador</label>
                <input type="text" id="matricula" name="matricula" placeholder="Digite sua matrícula ou utilizador" required maxlength="50">
            </div>
            
            <div class="input-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="senha" placeholder="Digite sua senha" required maxlength="64">
            </div>
            
            <button type="submit" class="btn btn-primary"> Entrar 
            </button> 
        </form>
        
        <div class="links" style="justify-content: center; margin-top: 20px;">
            <a href="esqueci_senha.php">Esqueci minha senha</a>
        </div>
    </div>
</body>
</html>
