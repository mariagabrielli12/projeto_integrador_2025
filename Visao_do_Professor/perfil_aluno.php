<?php
// Garante que o header e a conexão com o banco sejam incluídos
define('ROOT_PATH', dirname(__DIR__));
require_once(ROOT_PATH . '/Visao_do_Professor/templates/header_professor.php');

// Verificação de autenticação
if (!isset($_SESSION['id_professor']) || empty($_SESSION['id_professor'])) {
    header('Location: login.php');
    exit();
}

// Validação do ID do professor
$id_professor_logado = filter_var($_SESSION['id_professor'], FILTER_VALIDATE_INT);
if ($id_professor_logado === false || $id_professor_logado <= 0) {
    die("ID de professor inválido");
}

// --- FUNÇÕES DE CRIPTOGRAFIA (Copiadas da visao_secretario) ---
// O ideal seria mover isso para o 'conexao.php' futuramente
if (!defined('CODIFICACAO_SECRETA')) {
    define('CODIFICACAO_SECRETA', 'sua-chave-secreta-muito-forte-de-32-bytes');
}
if (!defined('ENCRYPTION_METHOD')) {
    define('ENCRYPTION_METHOD', 'AES-256-CBC');
}

function decodificar_dado($encoded_data) {
    if (empty($encoded_data)) return 'Não informado';
    
    $data_decoded = base64_decode($encoded_data, true);
    if ($data_decoded === false) {
        return 'Dado inválido';
    }
    
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    if (strlen($data_decoded) <= $iv_length) {
        return $encoded_data; 
    }
    
    $key = hash('sha256', CODIFICACAO_SECRETA, true);
    $iv = substr($data_decoded, 0, $iv_length);
    $ciphertext = substr($data_decoded, $iv_length);
    
    $decrypted_data = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted_data === false) {
        return "Erro ao decodificar";
    }
    
    return $decrypted_data;
}
// --- FIM DAS FUNÇÕES DE CRIPTOGRAFIA ---

$page_title = 'Perfil do Aluno';
$page_icon = 'fas fa-user';
$breadcrumb = 'Portal do Professor > Perfil do Aluno';

// --- VALIDAÇÃO E SANITIZAÇÃO DO ID DO ALUNO ---
$aluno_id = isset($_GET['id']) ? $_GET['id'] : 0;
$aluno_id = filter_var($aluno_id, FILTER_VALIDATE_INT);
if ($aluno_id === false || $aluno_id <= 0) {
    echo "<p class='alert error'>ID do aluno inválido.</p>";
    require_once 'templates/footer_professor.php';
    exit();
}

// --- LÓGICA DO BANCO DE DADOS ATUALIZADA COM VERIFICAÇÃO DE PERMISSÃO ---
$aluno = null;
$stmt_aluno = $conexao->prepare(
    "SELECT 
        a.id_aluno, a.nome_completo, a.data_nascimento, a.id_turma, t.nome_turma,
        u.nome_completo as nome_responsavel, u.telefone as telefone_responsavel,
        t.id_professor
     FROM alunos a
     LEFT JOIN turmas t ON a.id_turma = t.id_turma
     LEFT JOIN usuarios u ON a.id_responsavel_principal = u.id_usuario
     WHERE a.id_aluno = ? AND t.id_professor = ?"
);

if (!$stmt_aluno) {
    error_log("Erro ao preparar consulta do aluno: " . $conexao->error);
    die("Erro interno do sistema");
}

$stmt_aluno->bind_param("ii", $aluno_id, $id_professor_logado);
if (!$stmt_aluno->execute()) {
    error_log("Erro ao executar consulta do aluno: " . $stmt_aluno->error);
    die("Erro ao carregar dados do aluno");
}

$result_aluno = $stmt_aluno->get_result();
if ($result_aluno->num_rows > 0) {
    $aluno = $result_aluno->fetch_assoc();
}
$stmt_aluno->close();

if (!$aluno) {
    echo "<p class='alert error'>Aluno não encontrado ou você não tem permissão para acessar este perfil.</p>";
    require_once 'templates/footer_professor.php';
    exit();
}

// Busca o histórico de ocorrências do aluno com verificação de permissão
$ocorrencias = [];
$stmt_ocorrencias = $conexao->prepare(
    "SELECT o.data_ocorrencia, o.tipo, o.descricao
     FROM ocorrencias o
     INNER JOIN alunos a ON o.id_aluno = a.id_aluno
     INNER JOIN turmas t ON a.id_turma = t.id_turma
     WHERE o.id_aluno = ? AND t.id_professor = ?
     ORDER BY o.data_ocorrencia DESC"
);

if (!$stmt_ocorrencias) {
    error_log("Erro ao preparar consulta de ocorrências: " . $conexao->error);
    // Continua sem ocorrências, mas não quebra a página
} else {
    $stmt_ocorrencias->bind_param("ii", $aluno_id, $id_professor_logado);
    if ($stmt_ocorrencias->execute()) {
        $result_ocorrencias = $stmt_ocorrencias->get_result();
        if ($result_ocorrencias) {
            $ocorrencias = $result_ocorrencias->fetch_all(MYSQLI_ASSOC);
        }
    } else {
        error_log("Erro ao executar consulta de ocorrências: " . $stmt_ocorrencias->error);
    }
    $stmt_ocorrencias->close();
}
?>

<div class="card">
    <div class="card-header">
        <h3>Dossiê de <?php echo htmlspecialchars($aluno['nome_completo'], ENT_QUOTES, 'UTF-8'); ?></h3>
    </div>
    <div class="card-body">
        <h4 class="section-title">Dados Pessoais</h4>
        <div class="form-row">
            <div class="form-group">
                <label>Turma</label>
                <input type="text" value="<?php echo htmlspecialchars($aluno['nome_turma'] ?? 'Não matriculado', ENT_QUOTES, 'UTF-8'); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Nascimento</label>
                <input type="text" value="<?php 
                    $data_nascimento = $aluno['data_nascimento'] ?? '';
                    echo ($data_nascimento && $data_nascimento != '0000-00-00') ? 
                         htmlspecialchars(date('d/m/Y', strtotime($data_nascimento)), ENT_QUOTES, 'UTF-8') : 
                         'Não informado';
                ?>" readonly>
            </div>
        </div>

        <h4 class="section-title" style="margin-top: 20px;">Dados do Responsável</h4>
        <div class="form-row">
            <div class="form-group">
                <label>Nome do Responsável Principal</label>
                <input type="text" value="<?php echo htmlspecialchars(decodificar_dado($aluno['nome_responsavel']), ENT_QUOTES, 'UTF-8'); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Telefone do Responsável</label>
                <input type="text" value="<?php echo htmlspecialchars(decodificar_dado($aluno['telefone_responsavel']), ENT_QUOTES, 'UTF-8'); ?>" readonly>
            </div>
        </div>

        <h4 class="section-title" style="margin-top: 20px;">Histórico de Ocorrências</h4>
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
                        <tr>
                            <td colspan="3" class="text-center">Nenhuma ocorrência encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($ocorrencias as $oc): ?>
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
                <a href="turmas_professor.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar para Turmas
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once 'templates/footer_professor.php';
?>