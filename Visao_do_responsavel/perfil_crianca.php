<?php
$page_title = 'Perfil da Criança';
$page_icon = 'fas fa-child';
require_once 'templates/header_responsavel.php';

// --- FUNÇÕES DE CRIPTOGRAFIA (Necessárias para ler os dados do responsável) ---
if (!defined('CODIFICACAO_SECRETA')) {
    define('CODIFICACAO_SECRETA', 'sua-chave-secreta-muito-forte-de-32-bytes');
}
if (!defined('ENCRYPTION_METHOD')) {
    define('ENCRYPTION_METHOD', 'AES-256-CBC');
}

function decodificar_dado($encoded_data) {
    if (empty($encoded_data)) return 'Não informado';
    $data_decoded = base64_decode($encoded_data, true);
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    if ($data_decoded === false || strlen($data_decoded) <= $iv_length) {
        return $encoded_data; 
    }
    $key = hash('sha256', CODIFICACAO_SECRETA);
    $iv = substr($data_decoded, 0, $iv_length);
    $ciphertext = substr($data_decoded, $iv_length);
    $decrypted_data = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, $key, 0, $iv);
    if ($decrypted_data === false) {
        return "Erro ao decodificar";
    }
    return $decrypted_data;
}
// --- FIM DAS FUNÇÕES DE CRIPTOGRAFIA ---


// --- BUSCA OS DADOS DO ALUNO (ATUALIZADO PARA BUSCAR RESPONSÁVEL) ---
$aluno = null;
$stmt = $conexao->prepare(
    "SELECT 
        a.*, 
        t.nome_turma,
        u.email AS email_responsavel_principal,
        u.telefone AS telefone_responsavel_principal
     FROM alunos a
     JOIN alunos_responsaveis ar ON a.id_aluno = ar.id_aluno
     LEFT JOIN turmas t ON a.id_turma = t.id_turma
     LEFT JOIN usuarios u ON a.id_responsavel_principal = u.id_usuario
     WHERE ar.id_responsavel = ? AND a.id_aluno = ?
     LIMIT 1"
);
// $id_responsavel_logado e $id_aluno_logado vêm do header_responsavel.php
$stmt->bind_param("ii", $id_responsavel_logado, $id_aluno_logado);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $aluno = $result->fetch_assoc();
}
$stmt->close();
?>

<?php if ($aluno): ?>
    <div class="card">
        <div class="card-header"><h3 class="section-title"><?php echo htmlspecialchars($aluno['nome_completo']); ?></h3></div>
        <div class="card-body">
            <h3 class="section-title">Informações Pessoais</h3>
            <div class="form-row">
                <div class="form-group"><label>Nome Completo</label><input type="text" value="<?php echo htmlspecialchars($aluno['nome_completo']); ?>" readonly></div>
                <div class="form-group"><label>Data de Nascimento</label><input type="text" value="<?php echo date("d/m/Y", strtotime($aluno['data_nascimento'])); ?>" readonly></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Turma</label><input type="text" value="<?php echo htmlspecialchars($aluno['nome_turma'] ?? 'Não matriculado'); ?>" readonly></div>
                <div class="form-group"><label>Gênero</label><input type="text" value="<?php echo htmlspecialchars($aluno['genero'] ?? 'Não informado'); ?>" readonly></div>
            </div>

            <h3 class="section-title">Endereço</h3>
            <div class="form-group"><label>Endereço Completo</label><input type="text" value="<?php echo htmlspecialchars(($aluno['logradouro'] ?? '') . ', ' . ($aluno['numero'] ?? '') . ' - ' . ($aluno['bairro'] ?? '')); ?>" readonly></div>
            
            <h3 class="section-title">Informações de Contato (Responsável Principal)</h3>
            <div class="form-row">
                <div class="form-group"><label>Telefone do Responsável</label><input type="text" value="<?php echo htmlspecialchars(decodificar_dado($aluno['telefone_responsavel_principal'] ?? '')); ?>" readonly></div>
                <div class="form-group"><label>E-mail do Responsável</label><input type="text" value="<?php echo htmlspecialchars($aluno['email_responsavel_principal'] ?? ''); ?>" readonly></div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card"><div class="card-body"><p>Nenhuma criança está associada a este perfil de responsável no momento.</p></div></div>
<?php endif; ?>

<?php require_once 'templates/footer_responsavel.php'; ?>