<?php
// --- O HEADER DEVE SER O PRIMEIRO A SER INCLUÍDO ---
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}
// Inclui o header no início (conexão, sessão e funções de criptografia)
require_once PROJECT_ROOT . '/visao_secretario/templates/header_secretario.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_aluno = $_POST['id_aluno'] ?: null;
    $nome_completo = $_POST['nome_completo'];
    $data_nascimento = $_POST['data_nascimento'];
    $id_turma = $_POST['id_turma'];
    $genero = $_POST['genero']; // Novo campo gênero
    
    // Criptografia de dados pessoais básicos
    $rg = codificar_dado($_POST['rg']); 
    $cpf = codificar_dado($_POST['cpf']); 
    
    // Endereço
    $cep = $_POST['cep']; $logradouro = $_POST['logradouro']; $numero = $_POST['numero']; $bairro = $_POST['bairro']; $cidade = $_POST['cidade']; $estado = $_POST['estado'];
    
    // Responsável
    $matricula_resp_form = $_POST['matricula_responsavel'];

    // --- NOVOS CAMPOS MÉDICOS ---
    $tipo_sanguineo = $_POST['tipo_sanguineo'];
    $nome_pediatra = $_POST['nome_pediatra'];
    
    // Criptografia de dados médicos sensíveis
    $alergias_enc = codificar_dado($_POST['alergias']);
    $condicoes_enc = codificar_dado($_POST['condicoes']);
    $telefone_pediatra_enc = codificar_dado($_POST['telefone_pediatra']);

    // Lógica para encontrar o responsável
    $id_responsavel_encontrado = null;
    if (!empty($matricula_resp_form)) {
        $stmt_resp = $conexao->prepare("SELECT id_usuario FROM usuarios WHERE matricula = ? AND id_tipo = 5");
        $stmt_resp->bind_param("s", $matricula_resp_form);
        $stmt_resp->execute();
        $result_resp = $stmt_resp->get_result();
        if ($result_resp->num_rows === 1) {
            $id_responsavel_encontrado = $result_resp->fetch_assoc()['id_usuario'];
        } else {
            $_SESSION['mensagem_erro'] = "Matrícula do responsável não encontrada ou inválida.";
            header("Location: Cadastro_Alunos.php" . ($id_aluno ? '?id=' . $id_aluno : ''));
            exit();
        }
        $stmt_resp->close();
    }
    
    if ($id_aluno) { 
        // UPDATE (Atualizado com campos médicos e gênero)
        $sql = "UPDATE alunos SET 
            nome_completo=?, data_nascimento=?, genero=?, rg=?, cpf=?, id_turma=?, 
            cep=?, logradouro=?, numero=?, bairro=?, cidade=?, estado=?, id_responsavel_principal=?,
            tipo_sanguineo=?, alergias_criptografadas=?, condicoes_criptografadas=?, nome_pediatra=?, telefone_pediatra_criptografado=?
            WHERE id_aluno = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("sssssissssssisssssi", 
            $nome_completo, $data_nascimento, $genero, $rg, $cpf, $id_turma, 
            $cep, $logradouro, $numero, $bairro, $cidade, $estado, $id_responsavel_encontrado,
            $tipo_sanguineo, $alergias_enc, $condicoes_enc, $nome_pediatra, $telefone_pediatra_enc,
            $id_aluno
        );
    } else { 
        // INSERT (Atualizado com campos médicos e gênero)
        $sql = "INSERT INTO alunos (
            nome_completo, data_nascimento, genero, rg, cpf, id_turma, 
            cep, logradouro, numero, bairro, cidade, estado, id_responsavel_principal,
            tipo_sanguineo, alergias_criptografadas, condicoes_criptografadas, nome_pediatra, telefone_pediatra_criptografado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("sssssissssssisssss", 
            $nome_completo, $data_nascimento, $genero, $rg, $cpf, $id_turma, 
            $cep, $logradouro, $numero, $bairro, $cidade, $estado, $id_responsavel_encontrado,
            $tipo_sanguineo, $alergias_enc, $condicoes_enc, $nome_pediatra, $telefone_pediatra_enc
        );
    }

    if ($stmt->execute()) {
        $novo_aluno_id = $id_aluno ?: $conexao->insert_id;
        // Vincula na tabela associativa se houver responsável
        if ($id_responsavel_encontrado) {
            // Remove vínculos antigos para evitar duplicidade/conflito simples neste modelo
            $conexao->query("DELETE FROM alunos_responsaveis WHERE id_aluno = $novo_aluno_id AND id_responsavel = $id_responsavel_encontrado");
            $conexao->query("INSERT INTO alunos_responsaveis (id_aluno, id_responsavel) VALUES ($novo_aluno_id, $id_responsavel_encontrado)");
        }
        $_SESSION['mensagem_sucesso'] = "Aluno salvo com sucesso!";
        header("Location: Listagem_Alunos.php");
        exit();
    } else {
        $_SESSION['mensagem_erro'] = "Erro ao salvar aluno: " . $stmt->error;
        header("Location: Cadastro_Alunos.php" . ($id_aluno ? '?id=' . $id_aluno : ''));
        exit();
    }
}

$page_title = 'Cadastro de Aluno';
$page_icon = 'fas fa-user-graduate';

// Inicializa variáveis vazias
$aluno = [
    'id_aluno' => null, 'nome_completo' => '', 'data_nascimento' => '', 'genero' => '', 'rg' => '', 'cpf' => '', 'id_turma' => '', 
    'cep' => '', 'logradouro' => '', 'numero' => '', 'bairro' => '', 'cidade' => '', 'estado' => '',
    'tipo_sanguineo' => '', 'alergias' => '', 'condicoes' => '', 'nome_pediatra' => '', 'telefone_pediatra' => ''
];
$matricula_responsavel = '';
$is_edit_mode = false;

// --- MODO EDIÇÃO ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $is_edit_mode = true;
    $id_aluno = $_GET['id'];
    $page_title = "Editar Aluno"; 

    $stmt = $conexao->prepare(
        "SELECT a.*, r.matricula as matricula_responsavel 
         FROM alunos a 
         LEFT JOIN usuarios r ON a.id_responsavel_principal = r.id_usuario
         WHERE a.id_aluno = ?"
    );
    $stmt->bind_param("i", $id_aluno);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $aluno_data = $result->fetch_assoc();
        
        // Descriptografa dados pessoais
        $aluno_data['rg'] = decodificar_dado($aluno_data['rg']);
        $aluno_data['cpf'] = decodificar_dado($aluno_data['cpf']);
        
        // Descriptografa e mapeia dados médicos para o array $aluno
        $aluno_data['alergias'] = decodificar_dado($aluno_data['alergias_criptografadas']);
        $aluno_data['condicoes'] = decodificar_dado($aluno_data['condicoes_criptografadas']);
        $aluno_data['telefone_pediatra'] = decodificar_dado($aluno_data['telefone_pediatra_criptografado']);
        
        $aluno = $aluno_data;
        $matricula_responsavel = $aluno_data['matricula_responsavel'] ?? '';
    }
    $stmt->close();
}

$turmas_result = $conexao->query("SELECT id_turma, nome_turma FROM turmas ORDER BY nome_turma");
?>

<div class="card">
    <div class="card-header">
        <h3 class="section-title"><?php echo $page_title; ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" action="Cadastro_Alunos.php<?php echo $is_edit_mode ? '?id=' . htmlspecialchars($aluno['id_aluno'] ?? '') : ''; ?>">
            <input type="hidden" name="id_aluno" value="<?php echo htmlspecialchars($aluno['id_aluno'] ?? ''); ?>">
            
            <h4 class="section-title" style="font-size: 1.1em; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Dados Pessoais</h4>
            <div class="form-group"><label>Nome Completo*</label><input type="text" name="nome_completo" value="<?php echo htmlspecialchars($aluno['nome_completo'] ?? ''); ?>" required></div>
            
            <div class="form-row">
                <div class="form-group"><label>Gênero</label>
                    <select name="genero">
                        <option value="">Selecione...</option>
                        <option value="Masculino" <?php echo (($aluno['genero'] ?? '') == 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                        <option value="Feminino" <?php echo (($aluno['genero'] ?? '') == 'Feminino') ? 'selected' : ''; ?>>Feminino</option>
                    </select>
                </div>
                <div class="form-group"><label>Data de Nascimento*</label><input type="date" name="data_nascimento" value="<?php echo htmlspecialchars($aluno['data_nascimento'] ?? ''); ?>" required></div>
            </div>

            <div class="form-row">
                <div class="form-group"><label for="rg">RG</label><input type="text" id="rg" name="rg" value="<?php echo htmlspecialchars($aluno['rg'] ?? ''); ?>"></div>
                <div class="form-group"><label for="cpf">CPF</label><input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($aluno['cpf'] ?? ''); ?>"></div>
            </div>
            
            <h4 class="section-title" style="font-size: 1.1em; margin-top: 20px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Endereço</h4>
            <div class="form-row">
                <div class="form-group"><label for="cep">CEP</label><input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($aluno['cep'] ?? ''); ?>"></div>
                <div class="form-group"><label>Logradouro</label><input type="text" name="logradouro" value="<?php echo htmlspecialchars($aluno['logradouro'] ?? ''); ?>"></div>
                <div class="form-group"><label>Número</label><input type="text" name="numero" value="<?php echo htmlspecialchars($aluno['numero'] ?? ''); ?>"></div>
                <div class="form-group"><label>Bairro</label><input type="text" name="bairro" value="<?php echo htmlspecialchars($aluno['bairro'] ?? ''); ?>"></div>
                <div class="form-group"><label>Cidade</label><input type="text" name="cidade" value="<?php echo htmlspecialchars($aluno['cidade'] ?? ''); ?>"></div>
                <div class="form-group"><label>Estado</label><input type="text" name="estado" value="<?php echo htmlspecialchars($aluno['estado'] ?? ''); ?>"></div>
            </div>

            <h4 class="section-title" style="font-size: 1.1em; margin-top: 20px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px; color: var(--danger);">Ficha Médica e Contatos de Emergência</h4>
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo Sanguíneo</label>
                    <select name="tipo_sanguineo">
                        <option value="">Desconhecido</option>
                        <option value="A+" <?php echo (($aluno['tipo_sanguineo'] ?? '') == 'A+') ? 'selected' : ''; ?>>A+</option>
                        <option value="A-" <?php echo (($aluno['tipo_sanguineo'] ?? '') == 'A-') ? 'selected' : ''; ?>>A-</option>
                        <option value="B+" <?php echo (($aluno['tipo_sanguineo'] ?? '') == 'B+') ? 'selected' : ''; ?>>B+</option>
                        <option value="B-" <?php echo (($aluno['tipo_sanguineo'] ?? '') == 'B-') ? 'selected' : ''; ?>>B-</option>
                        <option value="AB+" <?php echo (($aluno['tipo_sanguineo'] ?? '') == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                        <option value="AB-" <?php echo (($aluno['tipo_sanguineo'] ?? '') == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                        <option value="O+" <?php echo (($aluno['tipo_sanguineo'] ?? '') == 'O+') ? 'selected' : ''; ?>>O+</option>
                        <option value="O-" <?php echo (($aluno['tipo_sanguineo'] ?? '') == 'O-') ? 'selected' : ''; ?>>O-</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nome do Pediatra</label>
                    <input type="text" name="nome_pediatra" value="<?php echo htmlspecialchars($aluno['nome_pediatra'] ?? ''); ?>" placeholder="Nome do médico">
                </div>
                 <div class="form-group">
                    <label>Telefone do Pediatra</label>
                    <input type="text" id="telefone_pediatra" name="telefone_pediatra" value="<?php echo htmlspecialchars($aluno['telefone_pediatra'] ?? ''); ?>" placeholder="(99) 99999-9999">
                </div>
            </div>
            <div class="form-group">
                <label>Alergias (Alimentares, Medicamentosas, etc.) - <small>Informação Protegida</small></label>
                <textarea name="alergias" rows="3" placeholder="Liste todas as alergias conhecidas..."><?php echo htmlspecialchars($aluno['alergias'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label>Condições Pré-existentes / Cuidados Especiais - <small>Informação Protegida</small></label>
                <textarea name="condicoes" rows="3" placeholder="Diabetes, asma, restrições alimentares, etc..."><?php echo htmlspecialchars($aluno['condicoes'] ?? ''); ?></textarea>
            </div>

            <h4 class="section-title" style="font-size: 1.1em; margin-top: 20px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Vínculos</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="id_turma">Turma*</label>
                    <select id="id_turma" name="id_turma" required>
                        <option value="">Selecione a turma</option>
                        <?php if ($turmas_result->num_rows > 0) {
                            while($turma = $turmas_result->fetch_assoc()): ?>
                                <option value="<?php echo $turma['id_turma']; ?>" <?php echo (($aluno['id_turma'] ?? '') == $turma['id_turma']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($turma['nome_turma']); ?>
                                </option>
                            <?php endwhile; } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="matricula_responsavel">Matrícula do Responsável Principal</label>
                    <input type="text" id="matricula_responsavel" name="matricula_responsavel" value="<?php echo htmlspecialchars($matricula_responsavel); ?>" placeholder="Insira a matrícula para vincular">
                </div>
            </div>
            
            <div class="form-actions">
                <a href="Listagem_Alunos.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/imask"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        IMask(document.getElementById('cpf'), { mask: '000.000.000-00' });
        IMask(document.getElementById('cep'), { mask: '00000-000' });
        IMask(document.getElementById('rg'), { mask: '00.000.000-0' }); 
        
        // Máscara para o telefone do pediatra
        var phoneMask = IMask(document.getElementById('telefone_pediatra'), {
            mask: [
                { mask: '(00) 0000-0000' },
                { mask: '(00) 00000-0000' }
            ]
        });
    });
</script>

<?php require_once PROJECT_ROOT . '/visao_secretario/templates/footer_secretario.php'; ?>