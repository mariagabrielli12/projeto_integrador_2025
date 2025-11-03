<?php
session_start();
require_once '../conexao.php';
$mensagem = '';
$tipo_mensagem = '';
$token_valido = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verifica se o token existe e não expirou
    $stmt = $conexao->prepare("SELECT id_usuario FROM usuarios WHERE reset_token = ? AND reset_token_expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $token_valido = true;
        $usuario = $result->fetch_assoc();
        $id_usuario = $usuario['id_usuario'];
    } else {
        $mensagem = "Token inválido ou expirado. Por favor, solicite um novo link de redefinição.";
        $tipo_mensagem = "error";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_usuario'])) {
    $id_usuario = $_POST['id_usuario'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    if ($nova_senha !== $confirmar_senha) {
        $mensagem = "As senhas não coincidem.";
        $tipo_mensagem = "error";
        $token_valido = true; // Mantém o formulário visível
   } else {
        // Atualiza a senha e invalida o token
        // --- ALTERAÇÃO PARA MD5 ---
        $senha_hash = md5($nova_senha);
        $stmt = $conexao->prepare("UPDATE usuarios SET senha_hash = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id_usuario = ?");
        $stmt->bind_param("si", $senha_hash, $id_usuario);
        
        if ($stmt->execute()) {
            $mensagem = "Sua senha foi redefinida com sucesso! Você já pode fazer o login.";
            $tipo_mensagem = "success";
            $token_valido = false; // Esconde o formulário após o sucesso
        } else {
            $mensagem = "Ocorreu um erro ao atualizar sua senha.";
            $tipo_mensagem = "error";
            $token_valido = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Redefinir Senha</title>
    <link rel="stylesheet" href="CSS_login/style_login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <h1>Redefinir Nova Senha</h1>
        
        <?php if (!empty($mensagem)): ?>
            <div style="color: #fff; background-color: <?php echo $tipo_mensagem == 'success' ? '#155724' : '#c62828'; ?>; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($token_valido): ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="id_usuario" value="<?php echo $id_usuario; ?>">
            <div class="input-group">
                <label for="nova_senha"><i class="fas fa-lock"></i> Nova Senha</label>
                <input type="password" name="nova_senha" required>
            </div>
            <div class="input-group">
                <label for="confirmar_senha"><i class="fas fa-lock"></i> Confirmar Nova Senha</label>
                <input type="password" name="confirmar_senha" required>
            </div>
            <button type="submit" class="btn btn-primary">Salvar Nova Senha</button>
        </form>
        <?php else: ?>
        <div class="links" style="justify-content: center; margin-top: 20px;">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Voltar para a tela  de Login</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>