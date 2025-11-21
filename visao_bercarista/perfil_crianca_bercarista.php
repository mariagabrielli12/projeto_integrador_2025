<?php
$page_title = 'Perfil da Criança';
$page_icon = 'fas fa-child';
require_once 'templates/header_bercarista.php';

// Verificação adicional de autenticação
if (!isset($id_bercarista_logado) || empty($id_bercarista_logado)) {
    header('Location: login.php');
    exit();
}

// Validação do ID do berçarista
$id_bercarista_logado = filter_var($id_bercarista_logado, FILTER_VALIDATE_INT);
if ($id_bercarista_logado === false || $id_bercarista_logado <= 0) {
    die("ID de berçarista inválido");
}

// Busca as turmas associadas ao berçarista logado com prepared statements
$turmas = [];
$stmt_turmas = $conexao->prepare("SELECT id_turma, nome_turma FROM turmas WHERE id_bercarista = ? ORDER BY nome_turma");
if (!$stmt_turmas) {
    error_log("Erro ao preparar consulta de turmas: " . $conexao->error);
    die("Erro interno do sistema");
}

$stmt_turmas->bind_param("i", $id_bercarista_logado);
if (!$stmt_turmas->execute()) {
    error_log("Erro ao executar consulta de turmas: " . $stmt_turmas->error);
    die("Erro ao carregar turmas");
}

$result_turmas = $stmt_turmas->get_result();
if ($result_turmas) {
    $turmas = $result_turmas->fetch_all(MYSQLI_ASSOC);
}
$stmt_turmas->close();
?>

<div class="card">
    <div class="card-header"><h3 class="section-title">Selecionar Criança</h3></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label for="select-turma">Turma</label>
                <select id="select-turma" class="form-control" onchange="carregarAlunosParaPerfil(this.value)">
                    <option value="">Selecione uma turma</option>
                    <?php foreach($turmas as $turma): ?>
                        <option value="<?php echo htmlspecialchars($turma['id_turma'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($turma['nome_turma'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="select-aluno">Criança</label>
                <select id="select-aluno" class="form-control" disabled onchange="verPerfil()">
                    <option value="">Aguardando seleção de turma...</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card" id="perfil-info-card" style="margin-top: 25px; display: none;">
    <div class="card-header"><h3 class="section-title">Informações da Criança</h3></div>
    <div class="card-body" id="perfil-body">
        <p class="text-center">Aguardando seleção de um aluno...</p>
    </div>
</div>

<?php
$extra_js = '<script>
    function carregarAlunosParaPerfil(turmaId) {
        // Validação do input no cliente
        turmaId = turmaId.replace(/[^0-9]/g, "");
        
        const alunoSelect = document.getElementById("select-aluno");
        const perfilCard = document.getElementById("perfil-info-card");
        perfilCard.style.display = "none";
        alunoSelect.innerHTML = \'<option value="">A carregar...</option>\';
        alunoSelect.disabled = true;

        if (!turmaId) {
            alunoSelect.innerHTML = \'<option value="">Aguardando seleção de turma...</option>\';
            return;
        }

        // Adiciona token CSRF para proteção adicional
        const csrfToken = document.querySelector(\'meta[name="csrf-token"]\')?.getAttribute(\'content\') || \'\';
        
        fetch(`api_get_alunos.php?turma_id=${encodeURIComponent(turmaId)}`, {
            headers: {
                \'X-Requested-With\': \'XMLHttpRequest\',
                \'X-CSRF-Token\': csrfToken
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(\'Erro na rede\');
            }
            return response.json();
        })
        .then(data => {
            alunoSelect.innerHTML = \'<option value="">Selecione uma criança</option>\';
            if (data.length > 0) {
                data.forEach(aluno => {
                    const option = document.createElement("option");
                    option.value = aluno.id_aluno;
                    // Sanitização do output no JavaScript
                    option.textContent = aluno.nome_completo || \'\';
                    alunoSelect.appendChild(option);
                });
                alunoSelect.disabled = false;
            } else {
                alunoSelect.innerHTML = \'<option value="">Nenhuma criança nesta turma</option>\';
            }
        })
        .catch(error => {
            console.error(\'Erro:\', error);
            alunoSelect.innerHTML = \'<option value="">Erro ao carregar crianças</option>\';
        });
    }
    
    function verPerfil() {
        const alunoSelect = document.getElementById("select-aluno");
        const alunoId = alunoSelect.value;
        
        // Validação do input no cliente
        const cleanAlunoId = alunoId.replace(/[^0-9]/g, "");
        
        const perfilCard = document.getElementById("perfil-info-card");
        const perfilBody = document.getElementById("perfil-body");

        if (!cleanAlunoId) {
            perfilCard.style.display = "none";
            return;
        }

        perfilBody.innerHTML = "<p>A carregar perfil...</p>";
        perfilCard.style.display = "block";

        // Adiciona token CSRF para proteção adicional
        const csrfToken = document.querySelector(\'meta[name="csrf-token"]\')?.getAttribute(\'content\') || \'\';
        
        fetch(`api_get_perfil_aluno.php?id=${encodeURIComponent(cleanAlunoId)}`, {
            headers: {
                \'X-Requested-With\': \'XMLHttpRequest\',
                \'X-CSRF-Token\': csrfToken
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(\'Erro na rede\');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                perfilBody.innerHTML = `<p class="alert error">${data.error}</p>`;
            } else {
                // Função para escapar HTML e prevenir XSS
                const escapeHtml = (text) => {
                    if (text === null || text === undefined) return \'\';
                    const div = document.createElement(\'div\');
                    div.textContent = text;
                    return div.innerHTML;
                };

                const nomeCompleto = escapeHtml(data.nome_completo || \'\');
                const dataNascimento = escapeHtml(data.data_nascimento || \'\');
                const nomeTurma = escapeHtml(data.nome_turma || \'\');
                const responsavelNome = escapeHtml(data.responsavel_nome || \'\');
                const responsavelContato = escapeHtml(data.responsavel_contato || \'\');

                perfilBody.innerHTML = `
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nome Completo</label>
                            <input type="text" value="` + nomeCompleto + `" readonly>
                        </div>
                        <div class="form-group">
                            <label>Data de Nascimento</label>
                            <input type="text" value="` + dataNascimento + `" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Turma</label>
                            <input type="text" value="` + nomeTurma + `" readonly>
                        </div>
                    </div>
                    <h4 class="section-title" style="font-size: 1.1em; margin-top: 20px;">Contato de Emergência</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nome do Responsável</label>
                            <input type="text" value="` + responsavelNome + `" readonly>
                        </div>
                        <div class="form-group">
                            <label>Telefone</label>
                            <input type="text" value="` + responsavelContato + `" readonly>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error(\'Erro:\', error);
            perfilBody.innerHTML = \'<p class="alert error">Erro ao carregar perfil</p>\';
        });
    }
</script>';

// Adiciona meta tag CSRF token se não existir
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$extra_js = '<meta name="csrf-token" content="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">' . $extra_js;

echo $extra_js;
require_once 'templates/footer_bercarista.php';
?>