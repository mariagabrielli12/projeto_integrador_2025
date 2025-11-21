<?php
define('ROOT_PATH', dirname(__DIR__));
require_once(ROOT_PATH . '/Visao_do_Professor/templates/header_professor.php');

$page_title = 'Chat com Responsáveis';
$page_icon = 'fas fa-comments';
?>

<style>
    /* Estilos Específicos do Chat */
    .chat-container {
        display: flex;
        height: 600px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
    }
    .chat-sidebar {
        width: 300px;
        border-right: 1px solid var(--border);
        background: #f8f9fa;
        display: flex;
        flex-direction: column;
    }
    .chat-list {
        flex: 1;
        overflow-y: auto;
    }
    .chat-item {
        padding: 15px;
        border-bottom: 1px solid var(--border);
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .chat-item:hover, .chat-item.active {
        background: #e9ecef;
    }
    .chat-item .badge {
        background: var(--danger);
        color: white;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 11px;
    }
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #fff;
    }
    .chat-header {
        padding: 15px;
        border-bottom: 1px solid var(--border);
        font-weight: bold;
        background: #f8f9fa;
    }
    .chat-messages {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        background-color: #e5ddd5; /* Cor estilo WhatsApp */
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .message {
        max-width: 70%;
        padding: 10px 15px;
        border-radius: 8px;
        position: relative;
        font-size: 14px;
    }
    .message.recebida {
        background: white;
        align-self: flex-start;
        border-top-left-radius: 0;
    }
    .message.enviada {
        background: #dcf8c6;
        align-self: flex-end;
        border-top-right-radius: 0;
    }
    .message-time {
        font-size: 10px;
        color: #999;
        text-align: right;
        margin-top: 5px;
    }
    .chat-input-area {
        padding: 15px;
        background: #f0f0f0;
        display: flex;
        gap: 10px;
    }
    .chat-input-area input {
        flex: 1;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 20px;
        outline: none;
    }
</style>

<div class="card">
    <div class="chat-container">
        <div class="chat-sidebar">
            <div style="padding: 15px; border-bottom: 1px solid #ddd;">
                <strong><i class="fas fa-users"></i> Pais/Responsáveis</strong>
            </div>
            <div class="chat-list" id="lista-contatos">
                <p style="padding:15px; text-align:center; color:#999;">Carregando...</p>
            </div>
        </div>

        <div class="chat-main">
            <div class="chat-header" id="chat-header">
                Selecione um contato para iniciar
            </div>
            <div class="chat-messages" id="chat-messages">
                </div>
            <div class="chat-input-area">
                <input type="text" id="msg-input" placeholder="Digite sua mensagem..." disabled>
                <button class="btn btn-primary" id="btn-enviar" disabled><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<script>
    let contatoAtualId = null;
    let pollingInterval = null;

    // 1. Carregar Contatos
    function carregarContatos() {
        fetch('api_chat.php?action=contatos')
            .then(response => response.json())
            .then(data => {
                const lista = document.getElementById('lista-contatos');
                lista.innerHTML = '';
                data.forEach(contato => {
                    const div = document.createElement('div');
                    div.className = `chat-item ${contatoAtualId == contato.id_usuario ? 'active' : ''}`;
                    div.onclick = () => abrirConversa(contato.id_usuario, contato.nome_completo);
                    
                    let badge = contato.nao_lidas > 0 ? `<span class="badge">${contato.nao_lidas}</span>` : '';
                    
                    div.innerHTML = `
                        <div>
                            <i class="fas fa-user-circle" style="color: #ccc; margin-right: 8px;"></i>
                            ${contato.nome_completo}
                        </div>
                        ${badge}
                    `;
                    lista.appendChild(div);
                });
            });
    }

    // 2. Abrir Conversa
    function abrirConversa(id, nome) {
        contatoAtualId = id;
        document.getElementById('chat-header').innerHTML = `<i class="fas fa-user"></i> ${nome}`;
        document.getElementById('msg-input').disabled = false;
        document.getElementById('btn-enviar').disabled = false;
        
        carregarMensagens();
        
        // Inicia atualização automática (polling) a cada 3 segundos
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(carregarMensagens, 3000);
        
        // Atualiza lista de contatos para limpar badge de não lidas
        carregarContatos();
    }

    // 3. Carregar Mensagens
    function carregarMensagens() {
        if (!contatoAtualId) return;

        fetch(`api_chat.php?action=mensagens&id_contato=${contatoAtualId}`)
            .then(response => response.json())
            .then(mensagens => {
                const container = document.getElementById('chat-messages');
                container.innerHTML = '';
                mensagens.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = `message ${msg.eh_minha ? 'enviada' : 'recebida'}`;
                    div.innerHTML = `
                        ${msg.mensagem}
                        <div class="message-time">${msg.data_formatada}</div>
                    `;
                    container.appendChild(div);
                });
                // Rola para o final
                container.scrollTop = container.scrollHeight;
            });
    }

    // 4. Enviar Mensagem
    function enviarMensagem() {
        const input = document.getElementById('msg-input');
        const texto = input.value.trim();
        if (!texto || !contatoAtualId) return;

        fetch('api_chat.php?action=enviar', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id_destinatario: contatoAtualId,
                mensagem: texto
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'sucesso') {
                input.value = '';
                carregarMensagens(); // Atualiza na hora
            }
        });
    }

    // Eventos
    document.getElementById('btn-enviar').addEventListener('click', enviarMensagem);
    document.getElementById('msg-input').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') enviarMensagem();
    });

    // Inicialização
    carregarContatos();
    // Atualiza lista de contatos a cada 10s para ver novos online
    setInterval(carregarContatos, 10000);
</script>

<?php require_once 'templates/footer_professor.php'; ?>