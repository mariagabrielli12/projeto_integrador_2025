<?php
if (!defined('PROJECT_ROOT')) {
     define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}
require_once PROJECT_ROOT . '/visao_secretario/templates/header_secretario.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
     $conexao->begin_transaction();
     try {
         $id_responsavel = $_POST['id_usuario'] ?: null;
         $senha = $_POST['senha']; 
         $tipo_id = 5; 

        // Criptografa antes de salvar
        $nome_codificado = codificar_dado($_POST['nome_completo']);
        $cpf_codificado = codificar_dado($_POST['cpf']);
        $rg_codificado = codificar_dado($_POST['rg']);
        $telefone_codificado = codificar_dado($_POST['telefone']);
        
        $email = $_POST['email']; 
        $matricula = $_POST['matricula']; 

        if ($id_responsavel) {
             if (!empty($senha)) {
                 $senha_hash = md5($senha);
                 $stmt_user = $conexao->prepare("UPDATE usuarios SET nome_completo=?, cpf=?, rg=?, email=?, telefone=?, matricula=?, senha_hash=? WHERE id_usuario=?");
                 $stmt_user->bind_param("sssssssi", $nome_codificado, $cpf_codificado, $rg_codificado, $email, $telefone_codificado, $matricula, $senha_hash, $id_responsavel);
             } else {
                 $stmt_user = $conexao->prepare("UPDATE usuarios SET nome_completo=?, cpf=?, rg=?, email=?, telefone=?, matricula=? WHERE id_usuario=?");
                 $stmt_user->bind_param("ssssssi", $nome_codificado, $cpf_codificado, $rg_codificado, $email, $telefone_codificado, $matricula, $id_responsavel);
             }
         } else { 
             if (empty($senha)) {
                throw new Exception("A senha é obrigatória para novos responsáveis.");
             }
             $senha_hash = md5($senha);
             $stmt_user = $conexao->prepare("INSERT INTO usuarios (nome_completo, cpf, rg, email, telefone, matricula, senha_hash, id_tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
             $stmt_user->bind_param("sssssssi", $nome_codificado, $cpf_codificado, $rg_codificado, $email, $telefone_codificado, $matricula, $senha_hash, $tipo_id);
         }
         
         if (!$stmt_user->execute()) {
            throw new Exception("Erro ao salvar dados do usuário: " . $stmt_user->error);
         }

         $id_responsavel_final = $id_responsavel ?: $conexao->insert_id;
         $stmt_user->close();

         // Tratamento do Endereço
         $id_endereco = $_POST['id_endereco'] ?: null;
         $cep = $_POST['cep']; 
         $logradouro = $_POST['logradouro']; 
         $numero = $_POST['numero']; 
         $complemento = $_POST['complemento'] ?? ''; 
         $bairro = $_POST['bairro']; 
         $cidade = $_POST['cidade']; 
         $estado = $_POST['estado'];

         if ($id_endereco) {
             $stmt_addr = $conexao->prepare("UPDATE enderecos SET cep=?, logradouro=?, numero=?, complemento=?, bairro=?, cidade=?, estado=? WHERE id_endereco=?");
             $stmt_addr->bind_param("sssssssi", $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado, $id_endereco);
         } else {
             $check_addr = $conexao->query("SELECT id_endereco FROM enderecos WHERE id_usuario = $id_responsavel_final");
             if($check_addr->num_rows == 0){
                 $stmt_addr = $conexao->prepare("INSERT INTO enderecos (id_usuario, cep, logradouro, numero, complemento, bairro, cidade, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                 $stmt_addr->bind_param("isssssss", $id_responsavel_final, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado);
             }
         }

         if (isset($stmt_addr)) {
            if (!$stmt_addr->execute()) {
                 throw new Exception("Erro ao salvar dados de endereço: " . $stmt_addr->error);
            }
            $stmt_addr->close();
         }

         $conexao->commit();
         $_SESSION['mensagem_sucesso'] = "Responsável salvo com sucesso!";
         header("Location: Listagem_Responsavel.php");
         exit();
     } catch (Exception $e) {
         $conexao->rollback();
         $_SESSION['mensagem_erro'] = "Erro ao salvar: " . $e->getMessage();
         header("Location: Cadastro_Responsavel.php" . ($id_responsavel ? '?id=' . $id_responsavel : ''));
         exit();
     }
}

$page_title = 'Cadastro de Responsável';
$page_icon = 'fas fa-user-tie';

$responsavel = ['id_usuario' => null, 'nome_completo' => '', 'cpf' => '', 'rg' => '', 'data_nascimento' => '', 'email' => '', 'telefone' => '', 'matricula' => ''];
$endereco = ['id_endereco' => null, 'logradouro' => '', 'cep' => '', 'numero' => '', 'complemento' => '', 'bairro' => '', 'cidade' => '', 'estado' => ''];
$is_edit_mode = false;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
     $is_edit_mode = true;
     $id_responsavel = $_GET['id'];
     $page_title = 'Editar Responsável';

     $stmt = $conexao->prepare("SELECT u.*, e.* FROM usuarios u LEFT JOIN enderecos e ON u.id_usuario = e.id_usuario WHERE u.id_usuario = ? AND u.id_tipo = 5");
     $stmt->bind_param("i", $id_responsavel);
     $stmt->execute();
     $result = $stmt->get_result();
     if ($result->num_rows > 0) {
         $data = $result->fetch_assoc();
     
        // Descriptografa para mostrar no formulário
        $data['nome_completo'] = decodificar_dado($data['nome_completo']);
        $data['cpf'] = decodificar_dado($data['cpf']);
        $data['rg'] = decodificar_dado($data['rg']);
        $data['telefone'] = decodificar_dado($data['telefone']);
        
         foreach ($data as $key => $value) {
             if (array_key_exists($key, $responsavel)) $responsavel[$key] = $value;
             if (array_key_exists($key, $endereco)) $endereco[$key] = $value;
         }
    }
     $stmt->close();
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="section-title"><?php echo $page_title; ?></h3>
    </div>
    <div class="card-body">
         <form id="form-responsavel" method="POST" action="Cadastro_Responsavel.php<?php echo $is_edit_mode ? '?id=' . htmlspecialchars($responsavel['id_usuario'] ?? '') : ''; ?>">
            <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($responsavel['id_usuario'] ?? ''); ?>">
            <input type="hidden" name="id_endereco" value="<?php echo htmlspecialchars($endereco['id_endereco'] ?? ''); ?>">

             <div class="form-row">
                 <div class="form-group"><label>Nome Completo*</label><input type="text" name="nome_completo" value="<?php echo htmlspecialchars($responsavel['nome_completo'] ?? ''); ?>" required></div>
                 <div class="form-group"><label for="cpf">CPF*</label><input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($responsavel['cpf'] ?? ''); ?>" required></div>
             </div>
             <div class="form-row">
                <div class="form-group">
                    <label for="rg">RG</label>
                    <input type="text" id="rg" name="rg" value="<?php echo htmlspecialchars($responsavel['rg'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($responsavel['telefone'] ?? ''); ?>">
                </div>
             </div>
             <div class="form-row">
                 <div class="form-group"><label>E-mail*</label><input type="email" name="email" value="<?php echo htmlspecialchars($responsavel['email'] ?? ''); ?>" required></div>
             </div>

             <h3 class="section-title" style="margin-top: 20px; margin-bottom: 20px;">Endereço</h3>
             <div class="form-row">
                 <div class="form-group"><label for="cep">CEP</label><input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($endereco['cep'] ?? ''); ?>"></div>
                 <div class="form-group"><label>Logradouro</label><input type="text" name="logradouro" value="<?php echo htmlspecialchars($endereco['logradouro'] ?? ''); ?>"></div>
                 <div class="form-group"><label>Número</label><input type="text" name="numero" value="<?php echo htmlspecialchars($endereco['numero'] ?? ''); ?>"></div>
                 <div class="form-group"><label>Complemento</label><input type="text" name="complemento" value="<?php echo htmlspecialchars($endereco['complemento'] ?? ''); ?>"></div>
                 <div class="form-group"><label>Bairro</label><input type="text" name="bairro" value="<?php echo htmlspecialchars($endereco['bairro'] ?? ''); ?>"></div>
                 <div class="form-group"><label>Cidade</label><input type="text" name="cidade" value="<?php echo htmlspecialchars($endereco['cidade'] ?? ''); ?>"></div>
                 <div class="form-group"><label>Estado</label><input type="text" name="estado" value="<?php echo htmlspecialchars($endereco['estado'] ?? ''); ?>"></div>
             </div>
            
             <h3 class="section-title" style="margin-top: 20px; margin-bottom: 20px;">Acesso</h3>
             <div class="form-row">
                 <div class="form-group"><label>Matrícula (ou Usuário)*</label><input type="text" name="matricula" value="<?php echo htmlspecialchars($responsavel['matricula'] ?? ''); ?>" required></div>
                 <div class="form-group">
                     <label for="senha">Senha*</label>
                     <input type="password" id="senha" name="senha" placeholder="<?php echo $is_edit_mode ? 'Deixe em branco para não alterar' : 'Senha de acesso'; ?>" <?php echo !$is_edit_mode ? 'required' : ''; ?>>
                 </div>
             </div>

             <div class="form-actions">
                 <a href="Listagem_Responsavel.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                 <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Cadastro</button>
            </div>
         </form>
    </div>
</div>

<script src="https://unpkg.com/imask"></script>
<script>
     document.addEventListener('DOMContentLoaded', function() {
         // Correção do bug de sintaxe aqui: cada IMask é uma chamada separada
         IMask(document.getElementById('cpf'), { mask: '000.000.000-00' });
         IMask(document.getElementById('cep'), { mask: '00000-000' });
         IMask(document.getElementById('rg'), { mask: '00.000.000-0' });
         IMask(document.getElementById('telefone'), {
             mask: [ { mask: '(00) 0000-0000' }, { mask: '(00) 00000-0000' } ]
         });
    });
</script>

<?php require_once PROJECT_ROOT . '/visao_secretario/templates/footer_secretario.php'; ?>