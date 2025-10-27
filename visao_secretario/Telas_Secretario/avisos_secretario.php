<?php
$page_title = 'Gerenciamento de Avisos';
$page_icon = 'fas fa-bell';
require_once '../templates/header_secretario.php';

// --- LÓGICA DE EXCLUSÃO ATUALIZADA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
    $id_aviso = $_POST['delete_id'];
    $stmt = $conexao->prepare("DELETE FROM avisos WHERE id_aviso = ?");
    $stmt->bind_param("i", $id_aviso);
    if ($stmt->execute()) {
        header("Location: avisos_secretario.php?sucesso=Aviso excluído com sucesso!");
    } else {
        header("Location: avisos_secretario.php?erro=Erro ao excluir o aviso.");
    }
    $stmt->close();
    exit();
}
$sql = "SELECT id_aviso, titulo, data_aviso, categoria, descricao 
        FROM avisos 
        WHERE destinatario IN ('GERAL', 'FUNCIONARIOS', 'SECRETARIA') 
        ORDER BY data_aviso DESC";

        $resultado = $conexao->query($sql)

?>

<div class="content-container">
    <div class="card">
        <div class="card-header">
            <h3 class="section-title">Todos os Avisos</h3>
            <a href="Cadastro_Aviso.php" class="btn-cadastrar-aviso" style="padding: 8px 15px; background: var(--primary-light); color: white; border-radius: 4px; text-decoration: none; font-weight: 600;">
                <i class="fas fa-plus"></i> Novo Aviso
            </a>
        </div>
        <div class="card-body">
            <?php if(isset($_GET['sucesso'])): ?>
                <div class="alert success"><?php echo htmlspecialchars($_GET['sucesso']); ?></div>
            <?php endif; ?>

            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while($aviso = $resultado->fetch_assoc()): ?>
                    <div class="notice-card">
                        <h4 class="card-title"><i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($aviso['titulo']); ?></h4>
                        <p class="card-meta">
                            <span><i class="far fa-clock"></i> <?php echo date("d/m/Y", strtotime($aviso['data_aviso'])); ?></span>
                            <span><i class="fas fa-tag"></i> Categoria: <?php echo htmlspecialchars($aviso['categoria']); ?></span>
                        </p>
                        <p class="card-text"><?php echo htmlspecialchars($aviso['descricao']); ?></p>
                        <div class="card-actions">
                             <a href="Cadastro_Aviso.php" class="btn btn-primary"></i> Editar</a>
                                <form method="POST" action="avisos_secretario.php" onsubmit="return confirm('Tem certeza que deseja excluir este aviso?');">
                            <input type="hidden" name="delete_id" value="<?php echo $aviso['id_aviso']; ?>">
                            <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Excluir
                            </button>
                                </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Nenhum aviso cadastrado no momento.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../templates/footer_secretario.php'; ?>