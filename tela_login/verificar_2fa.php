<?php
session_start();
require_once '../conexao.php';
require_once '../lib/GoogleAuthenticator.php'; // Garanta que este arquivo esteja em /lib/

// Se o usuário não passou pela tela de login (index.php), manda ele de volta.
if (!isset($_SESSION['2fa_verifying_user_id'])) {
    header("location: index.php");
    exit;
}

$login_err = "";
$ga = new PHPGangsta_GoogleAuthenticator();
$id_usuario_verificando = $_SESSION['2fa_verifying_user_id'];

// Processa o código
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = $_POST['code'];

    // 1. Pega o segredo do usuário no banco
    $stmt = $conexao->prepare("SELECT 2fa_secret, nome_completo, matricula, id_tipo FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario_verificando);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();

    if ($usuario) {
        $secret = $usuario['2fa_secret'];
        
        // 2. Verifica o código
        $checkResult = $ga->verifyCode($secret, $code, 2); // 2 = 1min de tolerância

        if ($checkResult) {
            // SUCESSO! Loga o usuário
            
            // --- MUDANÇA: ATUALIZA O TIMESTAMP ---
            $stmt_update = $conexao->prepare("UPDATE usuarios SET 2fa_last_verified_at = NOW() WHERE id_usuario = ?");
            $stmt_update->bind_param("i", $id_usuario_verificando);
            $stmt_update->execute();
            $stmt_update->close();
            // --- FIM DA MUDANÇA ---

            // Limpa a sessão temporária
            unset($_SESSION['2fa_verifying_user_id']);
            
            // Define as sessões de login permanentes
            $_SESSION["loggedin"] = true;
            $_SESSION["id_usuario"] = $id_usuario_verificando;
            $_SESSION["nome_completo"] = $usuario['nome_completo'];
            $_SESSION["matricula"] = $usuario['matricula'];
            
            $stmt_tipo = $conexao->prepare("SELECT nome_tipo FROM tipos_usuario WHERE id_tipo = ?");
            $stmt_tipo->bind_param("i", $usuario['id_tipo']);
            $stmt_tipo->execute();
            $perfil = $stmt_tipo->get_result()->fetch_assoc()['nome_tipo'];
            $stmt_tipo->close();
            $_SESSION["perfil"] = $perfil;

            // Redireciona para o dashboard correto
            switch ($perfil) {
                case 'Diretor': header("location: ../visao_do_diretor/index.php"); break;
                case 'Secretario': header("location: ../visao_secretario/Telas_Secretario/index.php"); break;
                case 'Professor': header("location: ../Visao_do_Professor/tela_principal_professor.php"); break;
                case 'Bercarista': header("location: ../visao_bercarista/index.php"); break;
                case 'Responsavel': header("location: ../Visao_do_responsavel/index.php"); break;
                default: $login_err = "Perfil de utilizador desconhecido."; break;
            }
            exit;
        } else {
            $login_err = "Código inválido. Tente novamente.";
        }
    } else {
        $login_err = "Erro ao encontrar usuário. Tente fazer o login novamente.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Verificação de 2 Fatores</title>
    <link rel="stylesheet" href="CSS_login/style_login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <h1>Verificação de 2 Fatores</h1>
        <h2 style="font-size: 1em;">Sua sessão expirou. Abra seu app autenticador e digite o código de 6 dígitos.</h2>
        
        <?php
        if (!empty($login_err)) {
            echo '<div style="color: #ffcdd2; background-color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 15px;">' . htmlspecialchars($login_err) . '</div>';
        }
        ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="input-group">
                <label for="code"><i class="fas fa-key"></i> Código de 6 dígitos</label>
                <input type="text" id="code" name="code" required maxlength="6" style="text-align: center; font-size: 1.2em; letter-spacing: 5px;">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Verificar
            </button> 
        </form>
        
        <div class="links" style="justify-content: center; margin-top: 20px;">
            <a href="logout.php"><i class="fas fa-arrow-left"></i> Voltar ao Login</a>
        </div>
    </div>
</body>
</html>