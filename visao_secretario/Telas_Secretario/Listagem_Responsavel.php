<?php
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}
$page_title = 'Listagem de Responsáveis';
$page_icon = 'fas fa-user-tie';
// O header inclui a conexão e as funções codificar_dado/decodificar_dado
require_once PROJECT_ROOT . '/visao_secretario/templates/header_secretario.php';

// --- LÓGICA DE EXCLUSÃO ---
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $id_para_deletar = $_GET['delete_id'];
    $stmt = $conexao->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_para_deletar);
    if ($stmt->execute()) {
        echo "<script>window.location.href='Listagem_Responsavel.php?sucesso=Responsável excluído com sucesso!';</script>";
        exit;
    } else {
        $erro_msg = "Erro ao excluir responsável.";
    }
    $stmt->close();
}

// Feedback Visual
if (isset($_GET['sucesso'])) {
    echo '<div class="alert success" style="margin-top: 15px;">' . htmlspecialchars($_GET['sucesso']) . '</div>';
}
if (isset($erro_msg)) {
    echo '<div class="alert error" style="margin-top: 15px;">' . htmlspecialchars($erro_msg) . '</div>';
}

// --- LÓGICA DE CONSULTA E TRATAMENTO DOS DADOS ---
$filtro_busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// 1. Busca os dados (criptografados no banco)
$sql = "SELECT id_usuario, nome_completo, cpf, email, telefone FROM usuarios WHERE id_tipo = 5 ORDER BY id_usuario DESC";
$result = $conexao->query($sql);

$responsaveis_exibir = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // 2. DESCRIPTOGRAFA OS DADOS AQUI
        // Se a função decodificar falhar ou o dado for antigo (texto plano), ela deve retornar o original.
        $nome_real = decodificar_dado($row['nome_completo']);
        $cpf_real = decodificar_dado($row['cpf']);
        $telefone_real = decodificar_dado($row['telefone']);
        
        // Atualiza o array temporário com os dados legíveis
        $row['nome_completo'] = $nome_real;
        $row['cpf'] = $cpf_real;
        $row['telefone'] = $telefone_real;

        // 3. Aplica o filtro de busca APÓS descriptografar
        // Isso permite buscar pelo nome real ou CPF real ("João" ou "123.456")
        if (!empty($filtro_busca)) {
            if (stripos($row['nome_completo'], $filtro_busca) !== false || stripos($row['cpf'], $filtro_busca) !== false) {
                $responsaveis_exibir[] = $row;
            }
        } else {
            // Se não tem busca, adiciona à lista
            $responsaveis_exibir[] = $row;
        }
    }
}
?>

<div class="card">
    <div class="card-header"><h3 class="section-title">Filtro de Busca</h3></div>
    <div class="card-body">
        <form method="GET" action="Listagem_Responsavel.php">
            <div class="form-row">
                <div class="form-group">
                    <label for="busca">Buscar por Nome ou CPF</label>
                    <input type="text" id="busca" name="busca" class="form-control" value="<?php echo htmlspecialchars($filtro_busca); ?>" placeholder="Digite o nome ou CPF...">
                </div>
                 <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
                    <?php if(!empty($filtro_busca)): ?>
                        <a href="Listagem_Responsavel.php" class="btn btn-secondary"><i class="fas fa-times"></i> Limpar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="table-container" style="margin-top: 20px;">
    <div class="table-settings">
        <a href="Cadastro_Responsavel.php" class="btn-cadastrar"><i class="fas fa-plus"></i> Cadastrar Novo Responsável</a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>CPF</th>
                <th>E-mail</th>
                <th>Telefone</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($responsaveis_exibir) > 0): ?>
                <?php foreach ($responsaveis_exibir as $responsavel): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($responsavel['nome_completo']); ?></td>
                        <td><?php echo htmlspecialchars($responsavel['cpf']); ?></td>
                        <td><?php echo htmlspecialchars($responsavel['email']); ?></td>
                        <td><?php echo htmlspecialchars($responsavel['telefone'] ?? 'N/D'); ?></td>
                        <td class="action-buttons">
                            <a href="Cadastro_Responsavel.php?id=<?php echo $responsavel['id_usuario']; ?>" class="btn-icon" title="Editar"><i class="fas fa-edit"></i></a>
                            <a href="Listagem_Responsavel.php?delete_id=<?php echo $responsavel['id_usuario']; ?>" class="btn-icon" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este responsável?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center;">Nenhum responsável encontrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once PROJECT_ROOT . '/visao_secretario/templates/footer_secretario.php'; ?>