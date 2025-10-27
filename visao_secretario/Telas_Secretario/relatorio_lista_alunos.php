<?php
// Define a constante que aponta para a pasta raiz do projeto
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}

// O header já inicia a sessão, faz a conexão ($conexao) e verifica o login
$page_title = 'Lista de Alunos';
$page_icon = 'fas fa-print';
// A inclusão do header foi MOVIDA PARA CIMA para garantir que $conexao exista
require_once PROJECT_ROOT . '/visao_secretario/templates/header_secretario.php';


// --- FUNÇÕES DE CRIPTOGRAFIA SEGURA ---

// !!! AVISO IMPORTANTE !!!
// Mova esta constante para seu arquivo principal de conexão (ex: conexao.php)
// e use um valor longo, aleatório e secreto!
if (!defined('CODIFICACAO_SECRETA')) {
    define('CODIFICACAO_SECRETA', 'sua-chave-secreta-muito-forte-de-32-bytes');
}

// Método de criptografia padrão
define('ENCRYPTION_METHOD', 'AES-256-CBC');

/**
 * Criptografa um dado de forma segura.
 */
function codificar_dado($data) {
    if (empty($data)) return $data;
    
    // Gera uma chave de 32 bytes a partir do seu segredo
    $key = hash('sha256', CODIFICACAO_SECRETA);
    
    // Gera um Vetor de Inicialização (IV) aleatório
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = openssl_random_pseudo_bytes($iv_length);
    
    // Criptografa
    $ciphertext = openssl_encrypt($data, ENCRYPTION_METHOD, $key, 0, $iv);
    
    // Retorna o IV + texto cifrado, codificado em Base64 para salvar no DB
    return base64_encode($iv . $ciphertext);
}

/**
 * Descriptografa um dado de forma segura.
 * (VERSÃO FINAL ATUALIZADA - corrige dados em texto puro)
 */
function decodificar_dado($encoded_data) {
    if (empty($encoded_data)) return 'Não informado';

    // 1. Tenta decodificar o Base64
    $data_decoded = base64_decode($encoded_data, true);
    
    // 2. Pega o tamanho do IV
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);

    // 3. VERIFICAÇÃO ATUALIZADA:
    //    Se não for Base64 VÁLIDO 
    //    OU se o tamanho decodificado for MENOR ou IGUAL ao tamanho do IV
    //    (não pode ser criptografado), então é texto puro.
    if ($data_decoded === false || strlen($data_decoded) <= $iv_length) {
        // Retorna o dado antigo como está
        return $encoded_data; 
    }
    
    // 4. É Base64 e tem o tamanho correto. Prossiga.
    $key = hash('sha256', CODIFICACAO_SECRETA);
    
    // 5. Separa o IV do texto cifrado
    $iv = substr($data_decoded, 0, $iv_length);
    $ciphertext = substr($data_decoded, $iv_length);

    // 6. Descriptografa
    $decrypted_data = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, $key, 0, $iv);

    if ($decrypted_data === false) {
        return "Erro ao decodificar";
    }
    
    return $decrypted_data;
}

// --- FIM DAS FUNÇÕES DE CRIPTOGRAFIA ---


// --- Lógica de Busca ---
$turmas = $conexao->query("SELECT id_turma, nome_turma FROM turmas ORDER BY nome_turma")->fetch_all(MYSQLI_ASSOC);
$lista_alunos = [];
$turma_selecionada_nome = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['turma_id'])) {
        $turma_id = $_POST['turma_id'];

     $stmt_nome = $conexao->prepare("SELECT nome_turma FROM turmas WHERE id_turma = ?");
     $stmt_nome->bind_param("i", $turma_id);
     $stmt_nome->execute();
     $resultado_nome = $stmt_nome->get_result();
    if ($resultado_nome->num_rows > 0) {
         $turma_selecionada_nome = $resultado_nome->fetch_assoc()['nome_turma'];
     }

     $sql_alunos = "
         SELECT a.nome_completo AS nome_aluno, u.nome_completo AS nome_responsavel, u.telefone
            FROM alunos a
             LEFT JOIN usuarios u ON a.id_responsavel_principal = u.id_usuario
             WHERE a.id_turma = ?
             ORDER BY a.nome_completo ASC
     ";

     $stmt_alunos = $conexao->prepare($sql_alunos);
     $stmt_alunos->bind_param("i", $turma_id);
     $stmt_alunos->execute();
     $lista_alunos = $stmt_alunos->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="section-title">Gerar Lista de Alunos</h3>
    </div>
    <div class="card-body">
        <form action="relatorio_lista_alunos.php" method="POST">
            <div class="form-row">
                <div class="form-group" style="flex-grow: 2;"> <label for="turma_id">Turma</label>
                    <select name="turma_id" id="turma_id" required>
                        <option value="">-- Escolha uma turma --</option>
                        <?php foreach ($turmas as $turma): ?>
                            <option value="<?php echo $turma['id_turma']; ?>">
                                <?php echo htmlspecialchars($turma['nome_turma']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="position: relative; align-self: flex-end;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-search"></i> Gerar Lista
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($lista_alunos)): ?>
    <div class="table-container" style="margin-top: 20px;">
        
        <div class="table-settings"> <h3 class="section-title">
                Lista de Alunos - <?php echo htmlspecialchars($turma_selecionada_nome); ?>
            </h3>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>

        <div class="alert success" style="margin: 15px;">
            Os dados do Responsável e Telefone são exibidos decodificados.
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