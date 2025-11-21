<?php
session_start();
require_once '../conexao.php';
require_once '../lib/GoogleAuthenticator.php'; // Garanta que este arquivo esteja em /lib/

// Proteção: Somente usuários que acabaram de por a senha podem acessar
if (!isset($_SESSION['2fa_verifying_user_id'])) {
    header("location: index.php");
    exit;
}

$id_usuario = $_SESSION['2fa_verifying_user_id'];
$mensagem_erro = '';
$mensagem_sucesso = '';

$ga = new PHPGangsta_GoogleAuthenticator();

// Busca o usuário no banco
$stmt = $conexao->prepare("SELECT email, 2fa_secret, 2fa_enabled FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

$secret = $usuario['2fa_secret'];

// 1. Se o usuário ainda não tem um segredo, gera um novo e salva
if (empty($secret)) {
    $secret = $ga->createSecret();
    $stmt_update = $conexao->prepare("UPDATE usuarios SET 2fa_secret = ? WHERE id_usuario = ?");
    $stmt_update->bind_param("si", $secret, $id_usuario);
    $stmt_update->execute();
    $stmt_update->close();
}

// 2. Lógica para verificar o código e ATIVAR o 2FA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['code'])) {
    $code = $_POST['code'];
    $checkResult = $ga->verifyCode($secret, $code, 2); 
    
    if ($checkResult) {
        // --- MUDANÇA: ATUALIZA O TIMESTAMP E FAZ O LOGIN ---
        
        // 1. Ativa o 2FA e define o timestamp da verificação
        $stmt_enable = $conexao->prepare("UPDATE usuarios SET 2fa_enabled = 1, 2fa_last_verified_at = NOW() WHERE id_usuario = ?");
        $stmt_enable->bind_param("i", $id_usuario);
        $stmt_enable->execute();
        $stmt_enable->close();
        
        // 2. Pega todos os dados para a sessão
        $stmt_data = $conexao->prepare("SELECT nome_completo, matricula, id_tipo FROM usuarios WHERE id_usuario = ?");
        $stmt_data->bind_param("i", $id_usuario);
        $stmt_data->execute();
        $usuario_data = $stmt_data->get_result()->fetch_assoc();
        $stmt_data->close();

        // 3. Define as sessões de login
        unset($_SESSION['2fa_verifying_user_id']); // Limpa a sessão temporária
        $_SESSION["loggedin"] = true;
        $_SESSION["id_usuario"] = $id_usuario;
        $_SESSION["nome_completo"] = $usuario_data['nome_completo'];
        $_SESSION["matricula"] = $usuario_data['matricula'];
        
        $stmt_tipo = $conexao->prepare("SELECT nome_tipo FROM tipos_usuario WHERE id_tipo = ?");
        $stmt_tipo->bind_param("i", $usuario_data['id_tipo']);
        $stmt_tipo->execute();
        $perfil = $stmt_tipo->get_result()->fetch_assoc()['nome_tipo'];
        $stmt_tipo->close();
        $_SESSION["perfil"] = $perfil;

        // 4. Redireciona para o dashboard
        $mensagem_sucesso = "2FA ativado com sucesso! Redirecionando...";
        switch ($perfil) {
            case 'Diretor': header("refresh:2;url=../visao_do_diretor/index.php"); break;
            case 'Secretario': header("refresh:2;url=../visao_secretario/Telas_Secretario/index.php"); break;
            case 'Professor': header("refresh:2;url=../Visao_do_Professor/tela_principal_professor.php"); break;
            case 'Bercarista': header("refresh:2;url=../visao_bercarista/index.php"); break;
            case 'Responsavel': header("refresh:2;url=../Visao_do_responsavel/index.php"); break;
            default: header("refresh:2;url=index.php"); break;
        }
        // --- FIM DA MUDANÇA ---
        
    } else {
        $mensagem_erro = "Código inválido. Tente novamente.";
    }
}

// 3. Gera o QR Code
$qrCodeUrl = $ga->getQRCodeGoogleUrl($usuario['email'], $secret, 'CrecheMundoMagico');

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ativar 2FA</title>
    <link rel="stylesheet" href="CSS_login/style_login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container" style="max-width: 500px;">
        <h1>Ativar Autenticação de Dois Fatores (2FA)</h1>

        <?php if ($mensagem_sucesso): ?>
            <div style="color: #d4edda; background-color: #155724; padding: 10px; border-radius: 5px; margin: 15px 0;"><?php echo $mensagem_sucesso; ?></div>
        <?php else: ?>
            <p style="color: white;">Este é seu primeiro login ou sua conta não tem 2FA. Escaneie o QR Code abaixo com seu app autenticador (Google Authenticator, etc.).</p>
            
            <div style="background: white; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code">
            </div>
            
            <p style="color: white; font-size: 0.9em;">Ou insira manualmente o código: <strong><?php echo $secret; ?></strong></p>
            
            <hr style="border-color: rgba(255,255,255,0.2); margin: 20px 0;">
            
            <p style="color: white;">Insira o código de 6 dígitos gerado pelo app para confirmar e completar seu login.</p>

            <?php if ($mensagem_erro) echo '<div style="color: #ffcdd2; background-color: #c62828; padding: 10px; border-radius: 5px; margin: 15px 0;">'.$mensagem_erro.'</div>'; ?>

            <form action="habilitar_2fa.php" method="post">
                <div class="input-group">
                    <label for="code"><i class="fas fa-key"></i> Código de 6 dígitos</label>
                    <input type="text" id="code" name="code" required maxlength="6" style="text-align: center; font-size: 1.2em; letter-spacing: 5px;">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Ativar e Entrar
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>