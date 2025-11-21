<?php
$page_title = 'Perfil da Criança';
$page_icon = 'fas fa-child';
require_once 'templates/header_responsavel.php';

// --- FUNÇÕES DE CRIPTOGRAFIA (Locais para garantir funcionamento) ---
if (!defined('CODIFICACAO_SECRETA')) { define('CODIFICACAO_SECRETA', 'sua-chave-secreta-muito-forte-de-32-bytes'); }
if (!defined('ENCRYPTION_METHOD')) { define('ENCRYPTION_METHOD', 'AES-256-CBC'); }

if (!function_exists('codificar_dado')) {
    function codificar_dado($data) {
        if (empty($data)) return $data;
        $key = hash('sha256', CODIFICACAO_SECRETA);
        $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);
        $ciphertext = openssl_encrypt($data, ENCRYPTION_METHOD, $key, 0, $iv);
        return base64_encode($iv . $ciphertext);
    }
}

if (!function_exists('decodificar_dado')) {
    function decodificar_dado($encoded_data) {
        if (empty($encoded_data)) return 'Não informado';
        $data_decoded = base64_decode($encoded_data, true);
        $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
        if ($data_decoded === false || strlen($data_decoded) <= $iv_length) return $encoded_data; 
        $key = hash('sha256', CODIFICACAO_SECRETA);
        $iv = substr($data_decoded, 0, $iv_length);
        $ciphertext = substr($data_decoded, $iv_length);
        $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, $key, 0, $iv);
        return ($decrypted === false) ? $encoded_data : $decrypted;
    }
}

// --- PROCESSAMENTO DO FORMULÁRIO (SALVAR DADOS MÉDICOS) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($id_aluno_logado) && $id_aluno_logado > 0) {
    // Coleta os dados do formulário
    $tipo_sanguineo = $_POST['tipo_sanguineo'];
    $nome_pediatra = $_POST['nome_pediatra'];
    
    // Criptografa os dados sensíveis antes de salvar
    $alergias_enc = codificar_dado($_POST['alergias']);
    $condicoes_enc = codificar_dado($_POST['condicoes']);
    $telefone_pediatra_enc = codificar_dado($_POST['telefone_pediatra']);

    // Atualiza no banco (apenas os campos médicos)
    // A cláusula JOIN garante que o responsável só altere o aluno vinculado a ele
    $sql_update = "
        UPDATE alunos a
        JOIN alunos_responsaveis ar ON a.id_aluno = ar.id_aluno
        SET 
            a.tipo_sanguineo = ?,
            a.nome_pediatra = ?,
            a.alergias_criptografadas = ?,
            a.condicoes_criptografadas = ?,
            a.telefone_pediatra_criptografado = ?
        WHERE a.id_aluno = ? AND ar.id_responsavel = ?
    ";

    $stmt = $conexao->prepare($sql_update);
    $stmt->bind_param("sssssii", $tipo_sanguineo, $nome_pediatra, $alergias_enc, $condicoes_enc, $telefone_pediatra_enc, $id_aluno_logado, $id_responsavel_logado);
    
    if ($stmt->execute()) {
        echo "<div class='alert success'>Dados médicos atualizados com sucesso!</div>";
    } else {
        echo "<div class='alert error'>Erro ao atualizar dados: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// --- BUSCA OS DADOS DO ALUNO ---
$aluno = null;
if (isset($id_aluno_logado) && $id_aluno_logado > 0) {
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
    $stmt->bind_param("ii", $id_responsavel_logado, $id_aluno_logado);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $aluno = $result->fetch_assoc();
    }
    $stmt->close();
}
?>

<?php if ($aluno): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="section-title">Ficha de <?php echo htmlspecialchars($aluno['nome_completo']); ?></h3>
            </div>
        <div class="card-body">
            
            <form id="form-perfil" method="POST" action="">
                <h4 class="section-title">Informações Pessoais <small>(Somente Leitura)</small></h4>
                <div class="form-row">
                    <div class="form-group"><label>Nome Completo</label><input type="text" value="<?php echo htmlspecialchars($aluno['nome_completo']); ?>" readonly disabled></div>
                    <div class="form-group"><label>Data de Nascimento</label><input type="text" value="<?php echo date("d/m/Y", strtotime($aluno['data_nascimento'])); ?>" readonly disabled></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Turma</label><input type="text" value="<?php echo htmlspecialchars($aluno['nome_turma'] ?? 'Não matriculado'); ?>" readonly disabled></div>
                    <div class="form-group"><label>Gênero</label><input type="text" value="<?php echo htmlspecialchars($aluno['genero'] ?? 'Não informado'); ?>" readonly disabled></div>
                </div>

                <h4 class="section-title" style="margin-top: 25px; color: #c0392b;"><i class="fas fa-heartbeat"></i> Saúde e Emergência (Editável)</h4>
                <div class="form-row">
                     <div class="form-group">
                        <label>Tipo Sanguíneo</label>
                        <select name="tipo_sanguineo">
                            <option value="">Selecione...</option>
                            <?php 
                            $tipos = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach($tipos as $tipo) {
                                $selected = ($aluno['tipo_sanguineo'] == $tipo) ? 'selected' : '';
                                echo "<option value='$tipo' $selected>$tipo</option>";
                            }
                            ?>
                        </select>
                     </div>
                     <div class="form-group">
                        <label>Nome do Pediatra</label>
                        <input type="text" name="nome_pediatra" value="<?php echo htmlspecialchars($aluno['nome_pediatra'] ?? ''); ?>" placeholder="Nome do médico">
                     </div>
                     <div class="form-group">
                        <label>Telefone do Pediatra</label>
                        <input type="text" id="telefone_pediatra" name="telefone_pediatra" value="<?php echo htmlspecialchars(decodificar_dado($aluno['telefone_pediatra_criptografado'] ?? '')); ?>" placeholder="(99) 99999-9999">
                    </div>
                </div>
                <div class="form-group">
                    <label style="color: #c0392b;">Alergias</label>
                    <textarea name="alergias" rows="3" placeholder="Alergia a amendoim, dipirona, picada de inseto..." style="background-color: #fffbfb; border-color: #feb2b2;"><?php echo htmlspecialchars(decodificar_dado($aluno['alergias_criptografadas'] ?? '')); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Condições Pré-existentes / Cuidados Especiais</label>
                    <textarea name="condicoes" rows="3" placeholder="Diabetes, asma, intolerância a lactose..."><?php echo htmlspecialchars(decodificar_dado($aluno['condicoes_criptografadas'] ?? '')); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Dados Médicos</button>
                </div>
            </form>

            <h4 class="section-title" style="margin-top: 25px;">Endereço Cadastrado</h4>
            <div class="form-group"><label>Endereço Completo</label><input type="text" value="<?php echo htmlspecialchars(($aluno['logradouro'] ?? '') . ', ' . ($aluno['numero'] ?? '') . ' - ' . ($aluno['bairro'] ?? '')); ?>" readonly disabled></div>
            
            <h4 class="section-title" style="margin-top: 25px;">Seus Contatos Principais</h4>
            <div class="form-row">
                <div class="form-group"><label>Telefone Principal</label><input type="text" value="<?php echo htmlspecialchars(decodificar_dado($aluno['telefone_responsavel_principal'] ?? '')); ?>" readonly disabled></div>
                <div class="form-group"><label>E-mail Principal</label><input type="text" value="<?php echo htmlspecialchars($aluno['email_responsavel_principal'] ?? ''); ?>" readonly disabled></div>
            </div>
            <p style="font-size: 0.9em; color: #666; margin-top: 10px;">* Para alterar endereço ou contatos principais, por favor entre em contato com a secretaria.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <p class="alert info">Nenhuma criança selecionada ou encontrada. Por favor, selecione um aluno no menu acima.</p>
        </div>
    </div>
<?php endif; ?>

<script src="https://unpkg.com/imask"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var phoneInput = document.getElementById('telefone_pediatra');
        if(phoneInput) {
            IMask(phoneInput, {
                mask: [
                    { mask: '(00) 0000-0000' },
                    { mask: '(00) 00000-0000' }
                ]
            });
        }
    });
</script>

<?php require_once 'templates/footer_responsavel.php'; ?>