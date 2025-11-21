<?php
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}
$page_title = 'Listagem de Alunos';
$page_icon = 'fas fa-user-graduate';
require_once PROJECT_ROOT . '/visao_secretario/templates/header_secretario.php';

// --- LÓGICA DE FILTRO ---
$filtro_turma_id = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0;

// --- LÓGICA DE EXCLUSÃO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
    $id_para_deletar = $_POST['delete_id'];
    $stmt_delete = $conexao->prepare("DELETE FROM alunos WHERE id_aluno = ?");
    $stmt_delete->bind_param("i", $id_para_deletar);
    if ($stmt_delete->execute()) {
        echo "<script>window.location.href='Listagem_Alunos.php?msg=sucesso';</script>";
    } else {
        echo "<div class='alert error'>Erro ao excluir o aluno.</div>";
    }
    $stmt_delete->close();
    exit();
}

if (isset($_GET['msg']) && $_GET['msg'] == 'sucesso') {
    echo "<div class='alert success'>Aluno excluído com sucesso!</div>";
}

// --- LÓGICA DE CONSULTA ---
$sql = "SELECT a.id_aluno, a.nome_completo, a.cpf, t.nome_turma 
        FROM alunos a 
        LEFT JOIN turmas t ON a.id_turma = t.id_turma";

if ($filtro_turma_id > 0) {
    $sql .= " WHERE a.id_turma = $filtro_turma_id";
}

$sql .= " ORDER BY a.nome_completo";
$resultado = $conexao->query($sql);
$turmas = $conexao->query("SELECT id_turma, nome_turma FROM turmas ORDER BY nome_turma");
?>

<div class="card">
    <div class="card-header">
        <h3 class="section-title">Alunos Matriculados</h3>
    </div>
    <div class="card-body">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap;">
            <form method="GET" action="Listagem_Alunos.php" style="display: flex; align-items: center; gap: 10px;">
                <label for="turma_id" style="white-space: nowrap;">Filtrar por Turma:</label>
                <select name="turma_id" id="turma_id" class="form-control" onchange="this.form.submit()">
                    <option value="0">Todas as Turmas</option>
                    <?php while($t = $turmas->fetch_assoc()): ?>
                        <option value="<?php echo $t['id_turma']; ?>" <?php echo ($filtro_turma_id == $t['id_turma']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['nome_turma']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
            
            <a href="Cadastro_Alunos.php" class="btn btn-primary"><i class="fas fa-plus"></i> Matricular Aluno</a>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome Completo</th>
                        <th>CPF</th>
                        <th>Turma</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>
                        <?php while($aluno = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($aluno['nome_completo']); ?></td>
                                <td><?php echo htmlspecialchars(decodificar_dado($aluno['cpf'])); ?></td>
                                <td><?php echo htmlspecialchars($aluno['nome_turma'] ?? 'Sem turma'); ?></td>
                                <td class="action-buttons">
                                    <a href="Cadastro_Alunos.php?id=<?php echo $aluno['id_aluno']; ?>" class="btn-icon" title="Editar"><i class="fas fa-edit"></i></a>
                                    
                                    <form method="POST" action="Listagem_Alunos.php" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este aluno?');">
                                        <input type="hidden" name="delete_id" value="<?php echo $aluno['id_aluno']; ?>">
                                        <button type="submit" class="btn-icon" title="Excluir" style="border:none; background:none; cursor:pointer;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center;">Nenhum aluno encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once PROJECT_ROOT . '/visao_secretario/templates/footer_secretario.php'; ?>