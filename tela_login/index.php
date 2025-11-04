<?php
session_start();

// Se o utilizador já estiver logado, redireciona.
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $perfil = $_SESSION["perfil"];
    switch ($perfil) {
        case 'Diretor': header("location: ../visao_do_diretor/index.php"); break;
        case 'Secretario': header("location: ../visao_secretario/Telas_Secretario/index.php"); break;
        case 'Professor': header("location: ../Visao_do_Professor/tela_principal_professor.php"); break;
        case 'Bercarista': header("location: ../visao_bercarista/index.php"); break;
        case 'Responsavel': header("location: ../Visao_do_responsavel/index.php"); break;
    }
    exit;
}

// Se o usuário estiver no meio de uma verificação 2FA, manda ele para a tela certa
if (isset($_SESSION['2fa_verifying_user_id'])) {
    header("location: verificar_2fa.php");
    exit;
}

require_once '../conexao.php';
$login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $matricula = trim($_POST['matricula']);
    $senha = $_POST['senha'];

    // --- MUDANÇA: Buscar as 3 colunas de 2FA ---
    $sql = "SELECT id_usuario, nome_completo, matricula, senha_hash, id_tipo, 
                   2fa_enabled, 2fa_last_verified_at 
            FROM usuarios WHERE matricula = ?";

    if ($stmt = $conexao->prepare($sql)) {
        $stmt->bind_param("s", $matricula);

        if ($stmt->execute()) {
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                // --- MUDANÇA: bind_result para as novas colunas ---
                $stmt->bind_result($id_usuario, $nome_completo, $matricula_db, $senha_hash_db, $id_tipo, $two_fa_enabled, $two_fa_last_verified_at);
                
                if ($stmt->fetch()) {
                    
                    if (md5($senha) === $senha_hash_db) {
                        
                        // --- LÓGICA DE 2FA A CADA 30 DIAS ---
                        
                        if ($two_fa_enabled == 0) {
                            // 1. NUNCA ATIVOU O 2FA
                            // Força o usuário a se registrar no 2FA
                            $_SESSION['2fa_verifying_user_id'] = $id_usuario;
                            header("location: habilitar_2fa.php");
                            exit;
                        
                        } else {
                            // 2. 2FA JÁ ESTÁ ATIVO. VERIFICAR A DATA.
                            $last_check_time = strtotime($two_fa_last_verified_at);
                            $thirty_days_ago = time() - (30 * 24 * 60 * 60); // 30 dias em segundos

                            if (empty($two_fa_last_verified_at) || $last_check_time < $thirty_days_ago) {
                                // 3. VERIFICAÇÃO EXPIRADA (passou 30 dias)
                                // Pede o código 2FA novamente
                                $_SESSION['2fa_verifying_user_id'] = $id_usuario;
                                header("location: verificar_2fa.php");
                                exit;
                            
                            } else {
                                // 4. AINDA CONFIÁVEL (dentro dos 30 dias)
                                // Loga o usuário diretamente
                                $_SESSION["loggedin"] = true;
                                $_SESSION["id_usuario"] = $id_usuario;
                                $_SESSION["nome_completo"] = $nome_completo;
                                $_SESSION["matricula"] = $matricula_db;
                                
                                $stmt_tipo = $conexao->prepare("SELECT nome_tipo FROM tipos_usuario WHERE id_tipo = ?");
                                $stmt_tipo->bind_param("i", $id_tipo);
                                $stmt_tipo->execute();
                                $perfil = $stmt_tipo->get_result()->fetch_assoc()['nome_tipo'];
                                $stmt_tipo->close();
                                $_SESSION["perfil"] = $perfil;

                                // Redirecionamento normal
                                switch ($perfil) {
                                    case 'Diretor': header("location: ../visao_do_diretor/index.php"); break;
                                    case 'Secretario': header("location: ../visao_secretario/Telas_Secretario/index.php"); break;
                                    case 'Professor': header("location: ../Visao_do_Professor/tela_principal_professor.php"); break;
                                    case 'Bercarista': header("location: ../visao_bercarista/index.php"); break;
                                    case 'Responsavel': header("location: ../Visao_do_responsavel/index.php"); break;
                                    default: $login_err = "Perfil de utilizador desconhecido."; break;
                                }
                                exit;
                            }
                        }
                        // --- FIM DA LÓGICA DE 2FA ---

                    } else {
                        $login_err = "Matrícula ou senha inválida.";
                    }
                }
            } else {
                $login_err = "Matrícula ou senha inválida.";
            }
        } else {
            $login_err = "Ops! Algo deu errado. Por favor, tente novamente.";
        }
        $stmt->close();
    }
    $conexao->close();
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
        
        <?php
        if (!empty($login_err)) {
            echo '<div style="color: #ffcdd2; background-color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 15px;">' . htmlspecialchars($login_err) . '</div>';
        }
        ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="input-group">
                <label for="matricula"><i class="fas fa-id-card"></i> Matrícula ou Utilizador</label>
                <input type="text" id="matricula" name="matricula" placeholder="Digite sua matrícula ou utilizador" required>
            </div>
            
            <div class="input-group">
                <label for="password"><i class="fas fa-lock"></i> Senha</label>
                <input type="password" id="password" name="senha" placeholder="Digite sua senha" required>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button> 
        </form>
        
        <div class="links" style="justify-content: center; margin-top: 20px;">
            <a href="esqueci_senha.php"><i class="fas fa-question-circle"></i> Esqueci minha senha</a>
        </div>
    </div>
</body>
</html>