<?php
require_once '../conexao.php';
header('Content-Type: application/json');

$aluno_id = isset($_GET['aluno_id']) ? (int)$_GET['aluno_id'] : 0;

$response = [
    'labels' => [],
    'data' => []
];

if ($aluno_id > 0) {
    // Consulta para contar observações por área
    $sql = "
        SELECT area_desenvolvimento, COUNT(*) as total 
        FROM desenvolvimento_observacoes 
        WHERE id_aluno = ? 
        GROUP BY area_desenvolvimento
    ";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $aluno_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $response['labels'][] = $row['area_desenvolvimento'];
        $response['data'][] = (int)$row['total'];
    }
    
    $stmt->close();
}

// Se não houver dados, retorna estrutura vazia para não quebrar o gráfico
if (empty($response['labels'])) {
    $response['labels'] = ['Motor', 'Cognitivo', 'Socioafetivo', 'Linguagem'];
    $response['data'] = [0, 0, 0, 0];
}

echo json_encode($response);
$conexao->close();
?>