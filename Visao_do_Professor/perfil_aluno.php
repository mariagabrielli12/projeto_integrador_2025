<?php
// Garante que o header e a conexão com o banco sejam incluídos
define('ROOT_PATH', dirname(__DIR__));
require_once(ROOT_PATH . '/Visao_do_Professor/templates/header_professor.php');

// --- ATENÇÃO: Funções de criptografia removidas daqui pois já estão no header ---

$page_title = 'Perfil do Aluno';
$page_icon = 'fas fa-user';
$breadcrumb = 'Portal do Professor > Perfil do Aluno';

// Validação do ID
$aluno_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($aluno_id === 0) {
    echo "<div class='card'><div class='card-body'><p class='alert error'>ID do aluno não fornecido.</p></div></div>";
    require_once 'templates/footer_professor.php';
    exit();
}

// --- CONSULTA AO BANCO ---
// Busca dados do aluno + dados do responsável vinculado (tabela usuarios)
$aluno = null;
$stmt_aluno = $conexao->prepare(
    "SELECT 
        a.*, 
        t.nome_turma,
        u.nome_completo as nome_responsavel_vinculado,
        u.telefone as telefone_responsavel_vinculado,
        u.email as email_responsavel_vinculado
     FROM alunos a
     LEFT JOIN turmas t ON a.id_turma = t.id_turma
     LEFT JOIN usuarios u ON a.id_responsavel_principal = u.id_usuario
     WHERE a.id_aluno = ?"
);
$stmt_aluno->bind_param("i", $aluno_id);
$stmt_aluno->execute();
$result_aluno = $stmt_aluno->get_result();

if ($result_aluno->num_rows > 0) {
    $aluno = $result_aluno->fetch_assoc();
}
$stmt_aluno->close();

if (!$aluno) {
    echo "<div class='card'><div class='card-body'><p class='alert error'>Aluno não encontrado.</p></div></div>";
    require_once 'templates/footer_professor.php';
    exit();
}

// Busca ocorrências
$ocorrencias = [];
$stmt_ocorrencias = $conexao->prepare(
    "SELECT data_ocorrencia, tipo, descricao FROM ocorrencias WHERE id_aluno = ? ORDER BY data_ocorrencia DESC"
);
$stmt_ocorrencias->bind_param("i", $aluno_id);
$stmt_ocorrencias->execute();
$result_ocorrencias = $stmt_ocorrencias->get_result();
if ($result_ocorrencias) {
    $ocorrencias = $result_ocorrencias->fetch_all(MYSQLI_ASSOC);
}
$stmt_ocorrencias->close();
?>

<div class="card">
    <div class="card-header">
        <h3>Dossiê de <?php echo htmlspecialchars($aluno['nome_completo']); ?></h3>
    </div>
    <div class="card-body">
        
        <h4 class="section-title">Dados Pessoais</h4>
        <div class="form-row">
            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" value="<?php echo htmlspecialchars($aluno['nome_completo']); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Data de Nascimento</label>
                <input type="text" value="<?php echo date('d/m/Y', strtotime($aluno['data_nascimento'])); ?>" readonly>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Turma</label>
                <input type="text" value="<?php echo htmlspecialchars($aluno['nome_turma'] ?? 'Não matriculado'); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Gênero</label>
                <input type="text" value="<?php echo htmlspecialchars($aluno['genero'] ?? 'Não informado'); ?>" readonly>
            </div>
        </div>

        <h4 class="section-title" style="margin-top: 25px; color: #c0392b;"><i class="fas fa-heartbeat"></i> Ficha Médica e Emergência</h4>
        <div class="form-row">
            <div class="form-group">
                <label>Tipo Sanguíneo</label>
                <input type="text" value="<?php echo htmlspecialchars($aluno['tipo_sanguineo'] ?? 'Não informado'); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Pediatra</label>
                <input type="text" value="<?php echo htmlspecialchars($aluno['nome_pediatra'] ?? 'Não informado'); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Tel. Pediatra</label>
                <input type="text" value="<?php echo htmlspecialchars(decodificar_dado($aluno['telefone_pediatra_criptografado'] ?? '')); ?>" readonly>
            </div>
        </div>
        <div class="form-group">
            <label>Alergias</label>
            <textarea rows="2" readonly style="background-color: #fff5f5; border-color: #fc8181; color: #c53030; font-weight: 500;"><?php echo htmlspecialchars(decodificar_dado($aluno['alergias_criptografadas'] ?? '')); ?></textarea>
        </div>
        <div class="form-group">
            <label>Condições Pré-existentes / Cuidados</label>
            <textarea rows="2" readonly><?php echo htmlspecialchars(decodificar_dado($aluno['condicoes_criptografadas'] ?? '')); ?></textarea>
        </div>

        <h4 class="section-title" style="margin-top: 25px;">Dados do Responsável Principal</h4>
        <div class="form-row">
            <div class="form-group">
                <label>Nome do Responsável</label>
                <input type="text" value="<?php echo htmlspecialchars(decodificar_dado($aluno['nome_responsavel_vinculado'] ?? 'Não vinculado')); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Telefone de Contato</label>
                <input type="text" value="<?php echo htmlspecialchars(decodificar_dado($aluno['telefone_responsavel_vinculado'] ?? '')); ?>" readonly>
            </div>
        </div>

        <h4 class="section-title" style="margin-top: 25px;">Endereço</h4>
        <div class="form-group">
            <label>Endereço Completo</label>
            <input type="text" value="<?php echo htmlspecialchars(($aluno['logradouro'] ?? '') . ', ' . ($aluno['numero'] ?? '') . ' - ' . ($aluno['bairro'] ?? '')); ?>" readonly>
        </div>
        
        <h4 class="section-title" style="margin-top: 25px;">Histórico de Ocorrências</h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ocorrencias)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #666;">Nenhuma ocorrência registrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ocorrencias as $oc): ?>
                        <tr>
                            <td>
                                <?php 
                                    $data_ocorrencia = $oc['data_ocorrencia'] ?? '';
                                    echo ($data_ocorrencia && $data_ocorrencia != '0000-00-00 00:00:00') ? 
                                         htmlspecialchars(date('d/m/Y H:i', strtotime($data_ocorrencia)), ENT_QUOTES, 'UTF-8') : 
                                         'Data inválida';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($oc['tipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($oc['descricao'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions" style="margin-top: 20px; justify-content: flex-start;">
            <?php 
                $id_turma = isset($aluno['id_turma']) ? (int)$aluno['id_turma'] : 0;
                if ($id_turma > 0): 
            ?>
                <a href="detalhes_turma.php?id=<?php echo $id_turma; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar para a Turma
                </a>
            <?php else: ?>
                <a href="minhas_turmas.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar para Turmas
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'templates/footer_professor.php'; ?>