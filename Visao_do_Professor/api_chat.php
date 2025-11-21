<?php
require_once '../conexao.php';
session_start();

header('Content-Type: application/json');

// Verifica se está logado
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$meu_id = $_SESSION['id_usuario'];
$action = $_GET['action'] ?? '';

// --- 1. LISTAR CONTATOS (PAIS) ---
if ($action === 'contatos') {
    // Busca os responsáveis dos alunos que estão nas turmas deste professor
    // Agrupa para não repetir o mesmo pai várias vezes
    $sql = "
        SELECT DISTINCT u.id_usuario, u.nome_completo, 
               (SELECT COUNT(*) FROM chat_mensagens WHERE id_remetente = u.id_usuario AND id_destinatario = ? AND lida = 0) as nao_lidas
        FROM usuarios u
        JOIN alunos_responsaveis ar ON u.id_usuario = ar.id_responsavel
        JOIN alunos a ON ar.id_aluno = a.id_aluno
        JOIN turmas t ON a.id_turma = t.id_turma
        WHERE t.id_professor = ? AND u.id_tipo = 5
        ORDER BY nao_lidas DESC, u.nome_completo ASC
    ";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("ii", $meu_id, $meu_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $contatos = [];
    while ($row = $result->fetch_assoc()) {
        $contatos[] = $row;
    }
    echo json_encode($contatos);
    exit;
}

// --- 2. LISTAR MENSAGENS ---
if ($action === 'mensagens' && isset($_GET['id_contato'])) {
    $id_contato = (int)$_GET['id_contato'];
    
    // Marca mensagens como lidas
    $conexao->query("UPDATE chat_mensagens SET lida = 1 WHERE id_remetente = $id_contato AND id_destinatario = $meu_id");

    // Busca as mensagens entre EU e o CONTATO
    $sql = "
        SELECT *, 
               DATE_FORMAT(data_envio, '%d/%m %H:%i') as data_formatada 
        FROM chat_mensagens 
        WHERE (id_remetente = ? AND id_destinatario = ?) 
           OR (id_remetente = ? AND id_destinatario = ?) 
        ORDER BY data_envio ASC
    ";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("iiii", $meu_id, $id_contato, $id_contato, $meu_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mensagens = [];
    while ($row = $result->fetch_assoc()) {
        $row['eh_minha'] = ($row['id_remetente'] == $meu_id);
        $mensagens[] = $row;
    }
    echo json_encode($mensagens);
    exit;
}

// --- 3. ENVIAR MENSAGEM ---
if ($action === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = json_decode(file_get_contents('php://input'), true);
    
    $id_destinatario = (int)$dados['id_destinatario'];
    $mensagem = trim($dados['mensagem']);
    
    if (!empty($mensagem) && $id_destinatario > 0) {
        $stmt = $conexao->prepare("INSERT INTO chat_mensagens (id_remetente, id_destinatario, mensagem) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $meu_id, $id_destinatario, $mensagem);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'sucesso']);
        } else {
            echo json_encode(['error' => 'Erro ao enviar']);
        }
    }
    exit;
}
?>