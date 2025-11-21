<?php
require_once '../conexao.php';
session_start();

header('Content-Type: application/json');

// Verifica se está logado como responsável
if (!isset($_SESSION['id_usuario']) || $_SESSION['perfil'] !== 'Responsavel') {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$meu_id = $_SESSION['id_usuario'];
$action = $_GET['action'] ?? '';

// --- 1. LISTAR CONTATOS (EDUCADORES) ---
if ($action === 'contatos') {
    // Busca professores e berçaristas das turmas onde os filhos deste responsável estudam
    $sql = "
        SELECT DISTINCT u.id_usuario, u.nome_completo, tu.nome_tipo as cargo,
               (SELECT COUNT(*) FROM chat_mensagens WHERE id_remetente = u.id_usuario AND id_destinatario = ? AND lida = 0) as nao_lidas
        FROM usuarios u
        JOIN tipos_usuario tu ON u.id_tipo = tu.id_tipo
        /* Liga o professor/bercarista à turma */
        JOIN turmas t ON (t.id_professor = u.id_usuario OR t.id_bercarista = u.id_usuario)
        /* Liga a turma ao aluno */
        JOIN alunos a ON t.id_turma = a.id_turma
        /* Liga o aluno ao responsável logado */
        JOIN alunos_responsaveis ar ON a.id_aluno = ar.id_aluno
        WHERE ar.id_responsavel = ?
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

    // Busca as mensagens (mesma lógica do professor)
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