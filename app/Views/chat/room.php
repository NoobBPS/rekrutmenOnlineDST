<div class="chat-room">
    <div class="chat-header">
        <a href="<?= BASE_URL ?>chat" class="back-btn" aria-label="Kembali">&larr;</a>
        <div class="chat-user">
            <strong><?= h($partner['full_name']) ?></strong>
            <span class="user-role"><?= $partner['role'] === 'hrd' ? 'HRD' : 'Kandidat' ?></span>
        </div>
    </div>

    <div class="chat-messages" id="messages-container">
        <?php foreach ($messages as $msg): ?>
        <div class="message <?= (int) $msg['from_user_id'] === (int) $_SESSION['user_id'] ? 'sent' : 'received' ?>" data-id="<?= (int) $msg['id'] ?>">
            <div class="message-content"><?= nl2br(h($msg['content'])) ?></div>
            <span class="message-time"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <form class="chat-input" id="chat-form">
        <?= csrfField() ?>
        <input type="hidden" name="to_user_id" value="<?= (int) $partner['id'] ?>">
        <input type="text" name="content" maxlength="1000" placeholder="Ketik pesan..." autocomplete="off" required>
        <button type="submit" class="btn btn-primary">Kirim</button>
    </form>
</div>

<script>
(function () {
    const currentUserId = <?= (int) $_SESSION['user_id'] ?>;
    const partnerId = <?= (int) $partner['id'] ?>;
    const messagesContainer = document.getElementById('messages-container');
    const chatForm = document.getElementById('chat-form');
    const messageInput = chatForm.querySelector('input[name="content"]');
    const sendButton = chatForm.querySelector('button[type="submit"]');
    let lastId = <?= !empty($messages) ? (int) end($messages)['id'] : 0 ?>;
    let isLoading = false;
    let isSending = false;
    const renderedMessageIds = new Set(
        Array.from(messagesContainer.querySelectorAll('.message[data-id]'))
            .map((el) => Number(el.dataset.id))
            .filter((id) => Number.isFinite(id) && id > 0)
    );

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    function addMessage(msg) {
        const messageId = Number(msg.id);
        if (!Number.isFinite(messageId) || renderedMessageIds.has(messageId)) {
            return;
        }

        renderedMessageIds.add(messageId);
        lastId = Math.max(lastId, messageId);

        const div = document.createElement('div');
        div.className = 'message ' + (Number(msg.from_user_id) === currentUserId ? 'sent' : 'received');
        div.dataset.id = String(messageId);

        const content = document.createElement('div');
        content.className = 'message-content';
        content.innerHTML = escapeHtml(msg.content).replace(/\n/g, '<br>');

        const time = document.createElement('span');
        time.className = 'message-time';
        time.textContent = msg.created_at.split(' ')[1].slice(0, 5);

        div.appendChild(content);
        div.appendChild(time);
        messagesContainer.appendChild(div);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    async function loadMessages() {
        if (isLoading) {
            return;
        }

        isLoading = true;

        try {
            const response = await fetch('<?= BASE_URL ?>chat/getMessages/' + partnerId + '?last_id=' + lastId, {
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (data.success && Array.isArray(data.messages) && data.messages.length > 0) {
                data.messages.forEach((msg) => {
                    addMessage(msg);
                });
            }
        } catch (error) {
            // Silent polling fail to keep UX smooth.
        } finally {
            isLoading = false;
        }
    }

    chatForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (isSending) {
            return;
        }

        const content = messageInput.value.trim();
        if (!content) return;

        isSending = true;
        if (sendButton) sendButton.disabled = true;

        const formData = new FormData(chatForm);

        try {
            const response = await fetch('<?= BASE_URL ?>chat/send', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (data.success) {
                messageInput.value = '';
                await loadMessages();
            } else if (data.message) {
                alert(data.message);
            }
        } catch (error) {
            alert('Pesan gagal dikirim. Silakan coba lagi.');
        } finally {
            isSending = false;
            if (sendButton) sendButton.disabled = false;
        }
    });

    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    setInterval(loadMessages, 3000);
})();
</script>
