<?php

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}

$page_title = 'Lista de Alunos';
$page_icon = 'fas fa-print';
require_once PROJECT_ROOT . '/visao_secretario/templates/header_secretario.php';

// 1. Buscar todas as turmas para o filtro
$turmas = [];
$result_turmas = $conexao->query("SELECT id_turma, nome_turma FROM turmas ORDER BY nome_turma");
if ($result_turmas) {
    $turmas = $result_turmas->fetch_all(MYSQLI_ASSOC);
}

// 2. Verificar se uma turma foi selecionada
$lista_alunos = [];
$turma_selecionada_id = null;
$turma_selecionada_nome = "";

if (isset($_GET['turma_id']) && !empty($_GET['turma_id'])) {
    $turma_selecionada_id = (int)$_GET['turma_id'];

    // 3. Buscar nome da turma selecionada
    foreach ($turmas as $turma) {
        if ($turma['id_turma'] == $turma_selecionada_id) {
            $turma_selecionada_nome = $turma['nome_turma'];
            break;
        }
    }

    // 4. Buscar alunos da turma e seus responsáveis principais (usando a função de decodificação)
    $stmt = $conexao->prepare(
        "SELECT 
            a.nome_completo as nome_aluno,
            u.nome_completo as nome_responsavel,
            u.telefone
         FROM alunos a
         LEFT JOIN usuarios u ON a.id_responsavel_principal = u.id_usuario
         WHERE a.id_turma = ?
         ORDER BY a.nome_completo"
    );
    $stmt->bind_param("i", $turma_selecionada_id);
    $stmt->execute();
    $result_alunos = $stmt->get_result();
    if ($result_alunos) {
        $lista_alunos = $result_alunos->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="section-title">Gerar Relatório de Alunos por Turma</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="relatorio_lista_alunos.php">
            <div class="form-row">
                <div class="form-group" style="flex-grow: 1;">
                    <label for="turma_id">Selecione a Turma</label>
                    <select name="turma_id" id="turma_id" class="form-control" required>
                        <option value="">-- Selecione --</option>
                        <?php foreach ($turmas as $turma): ?>
                            <option value="<?php echo $turma['id_turma']; ?>" <?php echo ($turma['id_turma'] == $turma_selecionada_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($turma['nome_turma']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions" style="margin-top: 0;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Gerar Lista</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($lista_alunos)): ?>
    <div class="table-container" style="margin-top: 20px;">
        
        <div class="table-settings"> <h3 class="section-title">
                Lista de Alunos - <?php echo htmlspecialchars($turma_selecionada_nome); ?>
            <!-- </h3>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Imprimir
            </button>
            -->
        </div> 



        <table class="table">
            <thead>
                <tr>
                    <th>Nome do Aluno</th>
                    <th>Responsável Principal</th>
                    <th>Telefone de Contato</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista_alunos as $aluno): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($aluno['nome_aluno']); ?></td>
                        <td><?php echo htmlspecialchars(decodificar_dado($aluno['nome_responsavel'])); ?></td>
                        <td><?php echo htmlspecialchars(decodificar_dado($aluno['telefone'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
require_once PROJECT_ROOT . '/visao_secretario/templates/footer_secretario.php';
?>