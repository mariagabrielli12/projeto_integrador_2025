<?php
$page_title = 'Página Inicial';
$page_icon = 'fas fa-home';
// O header agora cuida da sessão, conexão e da lógica de múltiplos filhos,
// disponibilizando a variável $id_aluno_logado.
require_once 'templates/header_responsavel.php';

// --- BUSCA DE DADOS DINÂMICOS PARA O DASHBOARD ---

// 1. Busca as informações do aluno atualmente selecionado
$aluno_info = [];
if ($id_aluno_logado > 0) {
    $stmt_aluno = $conexao->prepare("SELECT nome_completo FROM alunos WHERE id_aluno = ?");
    $stmt_aluno->bind_param("i", $id_aluno_logado);
    $stmt_aluno->execute();
    $result_aluno = $stmt_aluno->get_result();
    if ($result_aluno->num_rows > 0) {
        $aluno_info = $result_aluno->fetch_assoc();
    }
    $stmt_aluno->close();
}

// 2. Busca o número de ocorrências e de relatórios de desenvolvimento para os cards
$ocorrencias_count = 0;
$desenvolvimento_count = 0;
if ($id_aluno_logado > 0) {
    // Contagem de ocorrências
    $stmt_ocorrencias = $conexao->prepare("SELECT COUNT(*) as total FROM ocorrencias WHERE id_aluno = ?");
    $stmt_ocorrencias->bind_param("i", $id_aluno_logado);
    $stmt_ocorrencias->execute();
    $ocorrencias_count = $stmt_ocorrencias->get_result()->fetch_assoc()['total'];
    $stmt_ocorrencias->close();

    // Contagem de relatórios de desenvolvimento
    $stmt_dev = $conexao->prepare("SELECT COUNT(*) as total FROM desenvolvimento_observacoes WHERE id_aluno = ?");
    $stmt_dev->bind_param("i", $id_aluno_logado);
    $stmt_dev->execute();
    $desenvolvimento_count = $stmt_dev->get_result()->fetch_assoc()['total'];
    $stmt_dev->close();
}
?>

<div class="card">
    <div class="card-header"><h3 class="section-title">Acesso Rápido</h3></div>
    <div class="card-body">
        <div class="shortcut-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <a href="perfil_crianca.php?aluno_id=<?php echo $id_aluno_logado; ?>" class="shortcut-card" style="text-decoration: none; color: inherit; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <i class="fas fa-child fa-2x" style="color: #3498db;"></i>
                <p style="margin-top: 10px;">Perfil da Criança</p>
            </a>
            <a href="avisos_responsavel.php?aluno_id=<?php echo $id_aluno_logado; ?>" class="shortcut-card" style="text-decoration: none; color: inherit; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <i class="fas fa-bell fa-2x" style="color: #2ecc71;"></i>
                <p style="margin-top: 10px;">Avisos</p>
            </a>
            <a href="atestados_responsavel.php?aluno_id=<?php echo $id_aluno_logado; ?>" class="shortcut-card" style="text-decoration: none; color: inherit; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <i class="fas fa-notes-medical fa-2x" style="color: #e74c3c;"></i>
                <p style="margin-top: 10px;">Atestados</p>
            </a>
            <a href="ocorrencias_responsavel.php?aluno_id=<?php echo $id_aluno_logado; ?>" class="shortcut-card" style="text-decoration: none; color: inherit; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <i class="fas fa-exclamation-triangle fa-2x" style="color: #f39c12;"></i>
                <p style="margin-top: 10px;">Ocorrências (<?php echo $ocorrencias_count; ?>)</p>
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="section-title">Relatórios da Criança</h3></div>
    <div class="card-body">
         <div class="shortcut-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
             <a href="relatorio_frequencia.php?aluno_id=<?php echo $id_aluno_logado; ?>" class="shortcut-card" style="text-decoration: none; color: inherit; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <i class="fas fa-calendar-check fa-2x" style="color: #3498db;"></i>
                <p style="margin-top: 10px;">Frequência</p>
            </a>
            <a href="relatorio_rotina.php?aluno_id=<?php echo $id_aluno_logado; ?>" class="shortcut-card" style="text-decoration: none; color: inherit; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <i class="fas fa-clipboard-list fa-2x" style="color: #2ecc71;"></i>
                <p style="margin-top: 10px;">Rotina Diária</p>
            </a>
            <a href="relatorios_responsavel.php?aluno_id=<?php echo $id_aluno_logado; ?>" class="shortcut-card" style="text-decoration: none; color: inherit; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <i class="fas fa-chart-line fa-2x" style="color: #9b59b6;"></i>
                <p style="margin-top: 10px;">Desenvolvimento</p>
            </a>
        </div>
    </div>
</div>

<?php require_once 'templates/footer_responsavel.php'; ?>