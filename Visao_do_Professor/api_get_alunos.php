<?php
// api_get_alunos.php
require_once dirname(__DIR__) . '/conexao.php';
header('Content-Type: application/json');

$turma_id = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0;

if ($turma_id > 0) {
    // --- CORREÇÃO AQUI ---
    // Ajusta a consulta para usar os nomes de coluna corretos do seu banco de dados
    $stmt = $conexao->prepare("SELECT id_aluno, nome_completo FROM alunos WHERE id_turma = ? ORDER BY nome_completo");
    $stmt->bind_param("i", $turma_id);
    $stmt->execute();
    $alunos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode($alunos);
} else {
    echo json_encode([]); // Retorna um array vazio se não houver turma_id
}

$conexao->close();
?>