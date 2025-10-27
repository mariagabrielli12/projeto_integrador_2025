<?php
session_start();
require_once '../conexao.php';
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    
    $stmt = $conexao->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $usuario = $result->fetch_assoc();
        $id_usuario = $usuario['id_usuario'];
        
        // Gerar um token seguro e único
        $token = bin2hex(random_bytes(50));
        // Definir tempo de expiração (ex: 1 hora a partir de agora)
        $expira_em = date("Y-m-d H:i:s", time() + 3600);
        
        // Armazenar o token no banco de dados
        $stmt_update = $conexao->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expires_at = ? WHERE id_usuario = ?");
        $stmt_update->bind_param("ssi", $token, $expira_em, $id_usuario);
        $stmt_update->execute();
        
        // --- LÓGICA DE ENVIO DE E-MAIL (A SER IMPLEMENTADA) ---
        // Aqui você integraria uma biblioteca como PHPMailer para enviar o e-mail
        // $link = "http://localhost/seu_projeto/tela_login/redefinir_senha.php?token=" . $token;
        // mail($email, "Redefinição de Senha", "Clique aqui para redefinir sua senha: " . $link);
        
        $mensagem = "Se o e-mail estiver cadastrado, um link de redefinição de senha foi enviado.";
        $tipo_mensagem = "success";
    } else {
        $mensagem = "Se o e-mail estiver cadastrado, um link de redefinição de senha foi enviado.";
        $tipo_mensagem = "success"; // Mostramos a mesma mensagem para não confirmar se um e-mail existe ou não (segurança)
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Senha</title>
    <link rel="stylesheet" href="CSS_login/style_login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <h1>Recuperar Senha</h1>
        <p style="color: white; margin-bottom: 20px;">Digite seu e-mail para receber as instruções de redefinição de senha.</p>
        
        <?php if (!empty($mensagem)): ?>
            <div style="color: #d4edda; background-color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="input-group">
                <label for="email"><i class="fas fa-envelope"></i> E-mail</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary">Enviar Link de Recuperação</button>
        </form>
        <div class="links" style="justify-content: center; margin-top: 20px;">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Voltar para o Login</a>
        </div>
    </div>
</body>
</html>