<?php
$page_title = 'Fale com a Escola';
$page_icon = 'fas fa-comments';
require_once 'templates/header_responsavel.php';
?>

<style>
    /* Estilos do Chat (Pode ser movido para o CSS principal depois) */
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
        background: #e74c3c; /* Vermelho */
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
        color: var(--primary);
    }
    .chat-messages {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        background-color: #e5ddd5; /* Fundo estilo WhatsApp */
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
        line-height: 1.4;
    }
    .message.recebida {
        background: white;
        align-self: flex-start;
        border-top-left-radius: 0;
        box-shadow: 0 1px 1px rgba(0,0,0,0.1);
    }
    .message.enviada {
        background: #dcf8c6; /* Verde claro */
        align-self: flex-end;
        border-top-right-radius: 0;
        box-shadow: 0 1px 1px rgba(0,0,0,0.1);
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
            <div style="padding: 15px; border-bottom: 1px solid #ddd; background-color: var(--primary); color: white;">
                <strong><i class="fas fa-chalkboard-teacher"></i> Educadores</strong>
            </div>
            <div class="chat-list" id="lista-contatos">
                <p style="padding:15px; text-align:center; color:#999;">Carregando...</p>
            </div>
        </div>

        <div class="chat-main">
            <div class="chat-header" id="chat-header">
                Selecione um educador para iniciar
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
                // Não limpamos a lista toda hora para não piscar, idealmente faríamos um diff, 
                // mas para simplificar vamos recriar mantendo o scroll se possível
                lista.innerHTML = '';
                
                if(data.length === 0) {
                    lista.innerHTML = '<p style="padding:15px; text-align:center; color:#999;">Nenhum educador vinculado ainda.</p>';
                    return;
                }

                data.forEach(contato => {
                    const div = document.createElement('div');
                    div.className = `chat-item ${contatoAtualId == contato.id_usuario ? 'active' : ''}`;
                    div.onclick = () => abrirConversa(contato.id_usuario, contato.nome_completo, contato.cargo);
                    
                    let badge = contato.nao_lidas > 0 ? `<span class="badge">${contato.nao_lidas}</span>` : '';
                    
                    div.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="user-avatar" style="width: 35px; height: 35px; font-size: 12px;">${contato.nome_completo.substr(0,2).toUpperCase()}</div>
                            <div>
                                <div style="font-weight: 600;">${contato.nome_completo}</div>
                                <div style="font-size: 11px; color: #777;">${contato.cargo}</div>
                            </div>
                        </div>
                        ${badge}
                    `;
                    lista.appendChild(div);
                });
            });
    }

    // 2. Abrir Conversa
    function abrirConversa(id, nome, cargo) {
        contatoAtualId = id;
        document.getElementById('chat-header').innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-user-circle fa-2x" style="color: var(--primary-light);"></i>
                <div>
                    <div>${nome}</div>
                    <div style="font-size: 12px; font-weight: normal; color: #666;">${cargo}</div>
                </div>
            </div>
        `;
        document.getElementById('msg-input').disabled = false;
        document.getElementById('btn-enviar').disabled = false;
        
        carregarMensagens();
        
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(carregarMensagens, 3000); // Atualiza a cada 3s
        
        carregarContatos(); // Atualiza a lista lateral para limpar badges
    }

    // 3. Carregar Mensagens
    function carregarMensagens() {
        if (!contatoAtualId) return;

        fetch(`api_chat.php?action=mensagens&id_contato=${contatoAtualId}`)
            .then(response => response.json())
            .then(mensagens => {
                const container = document.getElementById('chat-messages');
                
                // Lógica simples: Limpa e redesenha (para um sistema real, faríamos append apenas das novas)
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
                // Rola para o final (apenas se não estiver lendo histórico antigo - simplificado aqui)
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
                carregarMensagens(); // Atualiza imediatamente
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
    setInterval(carregarContatos, 10000); // Atualiza lista de contatos a cada 10s
</script>

<?php require_once 'templates/footer_responsavel.php'; ?>