<?php
define('VIEW_ROOT', __DIR__); 
define('PROJECT_ROOT', dirname(VIEW_ROOT)); 

$page_title = 'Editar Usuário';
$page_icon = 'fas fa-edit';
// Inclui o header, que já tem a conexão e o session_start()
require_once VIEW_ROOT . '/templates/header_diretor.php';

// --- 1. LÓGICA PARA BUSCAR DADOS DO USUÁRIO ---

$id_usuario_para_editar = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$usuario = null;
$endereco = null;

if ($id_usuario_para_editar > 0) {
    // Busca dados das tabelas 'usuarios' e 'enderecos'
    $stmt = $conexao->prepare(
        "SELECT u.*, e.* FROM usuarios u 
         LEFT JOIN enderecos e ON u.id_usuario = e.id_usuario 
         WHERE u.id_usuario = ?"
    );
    $stmt->bind_param("i", $id_usuario_para_editar);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $data = $result->fetch_assoc();
        
        // Separa os dados em dois arrays para facilitar o uso no formulário
        $usuario = [
            'nome_completo' => $data['nome_completo'],
            'cpf' => $data['cpf'],
            'email' => $data['email'],
            'telefone' => $data['telefone'],
            'matricula' => $data['matricula'],
            'id_tipo' => $data['id_tipo'],
            'ativo' => $data['ativo']
        ];
        
        $endereco = [
            'cep' => $data['cep'],
            'logradouro' => $data['logradouro'],
            'numero' => $data['numero'],
            'bairro' => $data['bairro'],
            'cidade' => $data['cidade'],
            'estado' => $data['estado']
        ];
        
    } else {
        $_SESSION['mensagem_erro'] = "Usuário não encontrado.";
        header("Location: listagem_funcionarios.php");
        exit;
    }
    $stmt->close();
} else {
    $_SESSION['mensagem_erro'] = "ID de usuário inválido.";
    header("Location: listagem_funcionarios.php");
    exit;
}

// Busca os tipos de usuário (Diretor, Professor, etc.) para o <select>
$tipos_usuario = $conexao->query("SELECT * FROM tipos_usuario ORDER BY nome_tipo");

?>

<div class="card">
    <div class="card-header">
        <h3 class="section-title">Editar Dados de <?php echo htmlspecialchars($usuario['nome_completo']); ?></h3>
    </div>
    
    <div class="card-body">
        <form method="POST" action="processa_edicao_usuario.php">
            <input type="hidden" name="id_usuario" value="<?php echo $id_usuario_para_editar; ?>">
            
            <h3 class="section-title">Informações Pessoais</h3>
            <div class="form-row">
                <div class="form-group"><label for="nome_completo">Nome Completo*</label><input type="text" id="nome_completo" name="nome_completo" value="<?php echo htmlspecialchars($usuario['nome_completo'] ?? ''); ?>" required></div>
                <div class="form-group"><label for="cpf">CPF</label><input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($usuario['cpf'] ?? ''); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="email">E-mail*</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" required></div>
                <div class="form-group"><label for="telefone">Telefone</label><input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>"></div>
            </div>

            <h3 class="section-title" style="margin-top: 20px;">Informações de Acesso</h3>
            <div class="form-row">
                <div class="form-group"><label for="matricula">Matrícula*</label><input type="text" id="matricula" name="matricula" value="<?php echo htmlspecialchars($usuario['matricula'] ?? ''); ?>" required></div>
                <div class="form-group">
                    <label for="id_tipo">Perfil de Usuário*</label>
                    <select id="id_tipo" name="id_tipo" required>
                        <option value="">Selecione o perfil</option>
                        <?php while($tipo = $tipos_usuario->fetch_assoc()): ?>
                            <option value="<?php echo $tipo['id_tipo']; ?>" <?php echo ($usuario['id_tipo'] == $tipo['id_tipo']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tipo['nome_tipo']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
             <div class="form-row">
                <div class="form-group">
                    <label for="ativo">Status da Conta*</label>
                    <select id="ativo" name="ativo" required>
                        <option value="1" <?php echo ($usuario['ativo'] == 1) ? 'selected' : ''; ?>>Ativo</option>
                        <option value="0" <?php echo ($usuario['ativo'] == 0) ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>
                 <div class="form-group">
                    <label for="senha">Nova Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Deixe em branco para não alterar">
                </div>
            </div>

            <h3 class="section-title" style="margin-top: 20px;">Endereço</h3>
            <div class="form-row">
                <div class="form-group"><label for="cep">CEP</label><input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($endereco['cep'] ?? ''); ?>"></div>
                <div class="form-group"><label for="logradouro">Logradouro</label><input type="text" id="logradouro" name="logradouro" value="<?php echo htmlspecialchars($endereco['logradouro'] ?? ''); ?>"></div>
                <div class="form-group"><label for="numero">Número</label><input type="text" id="numero" name="numero" value="<?php echo htmlspecialchars($endereco['numero'] ?? ''); ?>"></div>
                <div class="form-group"><label for="bairro">Bairro</label><input type="text" id="bairro" name="bairro" value="<?php echo htmlspecialchars($endereco['bairro'] ?? ''); ?>"></div>
                <div class="form-group"><label for="cidade">Cidade</label><input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($endereco['cidade'] ?? ''); ?>"></div>
                <div class="form-group"><label for="estado">Estado</EStado></label><input type="text" id="estado" name="estado" value="<?php echo htmlspecialchars($endereco['estado'] ?? ''); ?>"></div>
            </div>
            
            <div class="form-actions">
                <a href="listagem_funcionarios.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/imask"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        IMask(document.getElementById('cpf'), { mask: '000.000.000-00' });
        IMask(document.getElementById('cep'), { mask: '00000-000' });
        IMask(document.getElementById('telefone'), {
            mask: [
                { mask: '(00) 0000-0000' },
                { mask: '(00) 00000-0000' }
            ]
        });
    });
</script>

<?php
require_once VIEW_ROOT . '/templates/footer.php';
?>