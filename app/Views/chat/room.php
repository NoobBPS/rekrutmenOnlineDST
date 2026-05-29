<?php
$partner = isset($partner) && is_array($partner) ? $partner : ['id' => 0, 'full_name' => '', 'role' => '', 'avatar' => null];
$messages = isset($messages) && is_array($messages) ? $messages : [];
$applicationContext = isset($application_context) && is_array($application_context) ? $application_context : [];
$partnerAvatarUrl = avatarUrl($partner['avatar'] ?? null);
$lastMessage = !empty($messages) ? end($messages) : null;
$lastMessageId = is_array($lastMessage) ? (int) ($lastMessage['id'] ?? 0) : 0;
$statusKey = (string) ($applicationContext['status_key'] ?? '');
$isStaffViewer = hasRole('hrd') || hasRole('admin');
$chatStatusLabel = 'Terhubung dengan HRD';
$chatStatusVariant = 'info';

if ($isStaffViewer) {
    $staffLabels = [
        'pending' => 'Lamaran Baru',
        'screening' => 'Screening',
        'interview' => 'Interview',
        'accepted' => 'Diterima',
        'rejected' => 'Ditolak'
    ];
    $staffVariants = [
        'pending' => 'info',
        'screening' => 'info',
        'interview' => 'primary',
        'accepted' => 'success',
        'rejected' => 'neutral'
    ];
    $chatStatusLabel = (string) ($applicationContext['status_label'] ?? ($staffLabels[$statusKey] ?? 'Status Lamaran'));
    $chatStatusVariant = $staffVariants[$statusKey] ?? 'info';
} else {
    $candidateLabels = [
        'pending' => 'Lamaran Baru',
        'screening' => 'Screening',
        'interview' => 'Interview',
        'accepted' => 'Diterima',
        'rejected' => 'Ditolak'
    ];
    $candidateVariants = [
        'pending' => 'info',
        'screening' => 'info',
        'interview' => 'primary',
        'accepted' => 'success',
        'rejected' => 'neutral'
    ];
    $chatStatusLabel = (string) ($candidateLabels[$statusKey] ?? 'Chat Dimulai');
    $chatStatusVariant = $candidateVariants[$statusKey] ?? 'info';
}
$applicationLine = trim((string) ($applicationContext['job_title'] ?? ''));
?>
<div class="chat-room">
    <div class="chat-header">
        <a href="<?= BASE_URL ?>chat" class="back-btn" aria-label="Kembali">&larr;</a>
        <div class="chat-user-avatar" aria-hidden="true">
            <?php if ($partnerAvatarUrl): ?>
            <img src="<?= h($partnerAvatarUrl) ?>" class="chat-user-avatar-image" alt="">
            <?php else: ?>
            <?= h(avatarInitial($partner['full_name'] ?? '')) ?>
            <?php endif; ?>
        </div>
        <div class="chat-user">
            <strong><?= h($partner['full_name']) ?></strong>
            <span class="chat-status-badge chat-status-<?= h($chatStatusVariant) ?>"><?= h($chatStatusLabel) ?></span>
            <?php if ($applicationLine !== ''): ?>
            <span class="user-role">Melamar posisi: <?= h($applicationLine) ?></span>
            <?php else: ?>
            <span class="user-role"><?= in_array(($partner['role'] ?? ''), ['hrd', 'admin'], true) ? 'HRD' : 'Kandidat' ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat-messages" id="messages-container">
        <?php foreach ($messages as $msg): ?>
        <?php
            $isOwnMessage = (int) ($msg['from_user_id'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0);
            $isDeleted = !empty($msg['is_deleted']);
            $messageText = (string) ($msg['content'] ?? '');
            $rawContent = (string) ($msg['raw_content'] ?? '');
            $canEdit = !empty($msg['can_edit']);
            $canDelete = !empty($msg['can_delete']);
            $timeLabel = '-';
            if (!empty($msg['created_at'])) {
                $ts = strtotime((string) $msg['created_at']);
                if ($ts !== false) {
                    $timeLabel = date('H:i', $ts);
                }
            }
        ?>
        <div
            class="message <?= $isOwnMessage ? 'sent' : 'received' ?>"
            data-id="<?= (int) ($msg['id'] ?? 0) ?>"
            data-own="<?= $isOwnMessage ? '1' : '0' ?>"
            data-can-edit="<?= $canEdit ? '1' : '0' ?>"
            data-can-delete="<?= $canDelete ? '1' : '0' ?>"
            data-is-deleted="<?= $isDeleted ? '1' : '0' ?>"
            data-raw-content="<?= h($rawContent) ?>"
        >
            <div class="message-bubble">
                <?php if ($isDeleted): ?>
                <div class="message-content is-deleted">
                    <span>Pesan telah dihapus</span>
                    <span class="deleted-ban-icon text-red-500" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"></circle>
                            <path d="M8 16L16 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                        </svg>
                    </span>
                </div>
                <?php else: ?>
                <div class="message-content"><?= nl2br(h($messageText)) ?></div>
                <?php endif; ?>
                <span class="message-time"><?= h($timeLabel) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <form class="chat-input" id="chat-form">
        <?= csrfField() ?>
        <input type="hidden" name="to_user_id" value="<?= (int) ($partner['id'] ?? 0) ?>">
        <div class="chat-input-field">
            <div class="chat-edit-indicator" id="chat-edit-indicator" hidden>
                <span class="chat-edit-indicator__label">Mode edit pesan aktif (hanya pesan terkini, maksimal 10 menit)</span>
                <button type="button" class="chat-edit-cancel" id="chat-edit-cancel">Batal</button>
            </div>
            <textarea name="content" maxlength="1000" placeholder="Ketik pesan..." autocomplete="off" rows="2" required></textarea>
            <small class="chat-input-hint">Klik kanan (desktop) atau tekan lama (mobile) pada bubble untuk salin/edit/hapus.</small>
        </div>
        <button type="submit" class="btn btn-primary chat-send-btn" aria-label="Kirim pesan">&gt;</button>
    </form>

    <div class="chat-context-menu" id="message-context-menu" hidden>
        <button type="button" class="chat-context-item" data-context-action="copy">Salin Pesan</button>
        <button type="button" class="chat-context-item" data-context-action="edit" id="context-edit-item">Edit Pesan</button>
        <button type="button" class="chat-context-item chat-context-item-danger" data-context-action="delete" id="context-delete-item">Hapus Pesan</button>
    </div>
</div>

<script>
(function () {
    const currentUserId = <?= (int) ($_SESSION['user_id'] ?? 0) ?>;
    const partnerId = <?= (int) ($partner['id'] ?? 0) ?>;

    const messagesContainer = document.getElementById('messages-container');
    const chatForm = document.getElementById('chat-form');
    const messageInput = chatForm.querySelector('textarea[name="content"]');
    const csrfTokenInput = chatForm.querySelector('input[name="csrf_token"]');
    const hintElement = chatForm.querySelector('.chat-input-hint');
    const editIndicator = document.getElementById('chat-edit-indicator');
    const editCancelButton = document.getElementById('chat-edit-cancel');
    const sendButton = chatForm.querySelector('button[type="submit"]');
    const contextMenu = document.getElementById('message-context-menu');
    const contextEditItem = document.getElementById('context-edit-item');
    const contextDeleteItem = document.getElementById('context-delete-item');

    const defaultSendButtonText = sendButton ? sendButton.textContent : '>';
    const defaultHintText = hintElement ? hintElement.textContent : '';
    let lastId = <?= $lastMessageId ?>;
    let isLoading = false;
    let isSending = false;
    let editingMessageId = 0;
    let statusHintTimeout = null;
    let contextTargetMessage = null;
    let longPressTimer = null;

    const renderedMessageIds = (function () {
        const map = {};
        Array.prototype.forEach.call(messagesContainer.querySelectorAll('.message[data-id]'), function (el) {
            const id = Number(el.dataset.id);
            if (Number.isFinite(id) && id > 0) {
                map[id] = true;
            }
        });
        return {
            has(id) {
                return !!map[id];
            },
            add(id) {
                map[id] = true;
            }
        };
    })();

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value || '');
        return div.innerHTML;
    }

    function formatTime(datetimeText) {
        const text = String(datetimeText || '');
        const parts = text.split(' ');
        if (parts.length < 2) {
            return '';
        }
        return parts[1].slice(0, 5);
    }

    function showHintStatus(message, timeoutMs) {
        if (!hintElement) {
            return;
        }

        if (statusHintTimeout) {
            window.clearTimeout(statusHintTimeout);
            statusHintTimeout = null;
        }

        hintElement.textContent = message;
        statusHintTimeout = window.setTimeout(function () {
            hintElement.textContent = defaultHintText;
            statusHintTimeout = null;
        }, Number(timeoutMs || 2000));
    }

    function buildDeletedContentHtml() {
        return '<div class="message-content is-deleted"><span>Pesan telah dihapus</span><span class="deleted-ban-icon text-red-500" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"></circle><path d="M8 16L16 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg></span></div>';
    }

    function buildMessageBodyHtml(message) {
        const isDeleted = !!message.is_deleted;
        const contentHtml = isDeleted
            ? buildDeletedContentHtml()
            : '<div class="message-content">' + escapeHtml(message.content || '').replace(/\n/g, '<br>') + '</div>';
        const timeLabel = formatTime(message.created_at || '');
        return contentHtml + '<span class="message-time">' + escapeHtml(timeLabel) + '</span>';
    }

    function createMessageElement(message) {
        const messageId = Number(message.id || 0);
        const isOwn = Number(message.from_user_id || 0) === currentUserId;

        const wrapper = document.createElement('div');
        wrapper.className = 'message ' + (isOwn ? 'sent' : 'received');
        wrapper.dataset.id = String(messageId);
        wrapper.dataset.own = isOwn ? '1' : '0';
        wrapper.dataset.canEdit = message.can_edit ? '1' : '0';
        wrapper.dataset.canDelete = message.can_delete ? '1' : '0';
        wrapper.dataset.isDeleted = message.is_deleted ? '1' : '0';
        wrapper.dataset.rawContent = String(message.raw_content || '');

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.innerHTML = buildMessageBodyHtml(message);
        wrapper.appendChild(bubble);

        return wrapper;
    }

    function applyMessageUpdate(messageElement, messageData) {
        if (!messageElement) {
            return;
        }

        messageElement.dataset.canEdit = messageData.can_edit ? '1' : '0';
        messageElement.dataset.canDelete = messageData.can_delete ? '1' : '0';
        messageElement.dataset.isDeleted = messageData.is_deleted ? '1' : '0';
        messageElement.dataset.rawContent = String(messageData.raw_content || '');

        const bubble = messageElement.querySelector('.message-bubble');
        if (bubble) {
            bubble.innerHTML = buildMessageBodyHtml(messageData);
        }
    }

    function addMessage(message) {
        const messageId = Number(message.id || 0);
        if (!Number.isFinite(messageId) || messageId <= 0 || renderedMessageIds.has(messageId)) {
            return;
        }

        renderedMessageIds.add(messageId);
        lastId = Math.max(lastId, messageId);
        messagesContainer.appendChild(createMessageElement(message));
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    async function copyToClipboard(text) {
        const plainText = String(text || '').trim();
        if (plainText === '') {
            return false;
        }

        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(plainText);
            return true;
        }

        const textarea = document.createElement('textarea');
        textarea.value = plainText;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        let copied = false;
        try {
            copied = document.execCommand('copy');
        } finally {
            document.body.removeChild(textarea);
        }

        return copied;
    }

    function closeContextMenu() {
        if (!contextMenu) {
            return;
        }
        contextMenu.hidden = true;
        contextTargetMessage = null;
    }

    function openContextMenuForMessage(messageElement) {
        if (!contextMenu || !messageElement || messageElement.dataset.system === '1') {
            return;
        }

        if (messageElement.dataset.own !== '1' || messageElement.dataset.isDeleted === '1') {
            return;
        }

        contextTargetMessage = messageElement;

        const canEdit = messageElement.dataset.canEdit === '1';
        const canDelete = messageElement.dataset.canDelete === '1';
        if (contextEditItem) {
            contextEditItem.hidden = false;
            contextEditItem.disabled = !canEdit;
        }
        if (contextDeleteItem) {
            contextDeleteItem.hidden = false;
            contextDeleteItem.disabled = !canDelete;
        }

        contextMenu.hidden = false;
        contextMenu.style.visibility = 'hidden';
        contextMenu.style.left = '0px';
        contextMenu.style.top = '0px';

        const viewportW = window.innerWidth || document.documentElement.clientWidth;
        const viewportH = window.innerHeight || document.documentElement.clientHeight;
        const menuRect = contextMenu.getBoundingClientRect();
        const bubble = messageElement.querySelector('.message-bubble') || messageElement;
        const bubbleRect = bubble.getBoundingClientRect();

        let left = bubbleRect.right - menuRect.width;
        let top = bubbleRect.top - menuRect.height - 8;

        if (top < 8) {
            top = bubbleRect.bottom + 8;
        }
        if (left + menuRect.width > viewportW - 8) {
            left = viewportW - menuRect.width - 8;
        }
        if (top + menuRect.height > viewportH - 8) {
            top = viewportH - menuRect.height - 8;
        }
        if (left < 8) left = 8;
        if (top < 8) top = 8;

        contextMenu.style.left = left + 'px';
        contextMenu.style.top = top + 'px';
        contextMenu.style.visibility = 'visible';
    }

    function setEditMode(messageElement) {
        if (!messageElement) {
            return;
        }

        const messageId = Number(messageElement.dataset.id || 0);
        const rawContent = String(messageElement.dataset.rawContent || '');
        if (!Number.isFinite(messageId) || messageId <= 0 || rawContent.trim() === '') {
            return;
        }

        editingMessageId = messageId;
        messageInput.value = rawContent;
        resizeInput();
        messageInput.focus();

        if (sendButton) {
            sendButton.textContent = 'OK';
            sendButton.setAttribute('aria-label', 'Simpan edit pesan');
        }
        if (editIndicator) {
            editIndicator.hidden = false;
        }

        showHintStatus('Mode edit aktif. Simpan perubahan dalam waktu yang masih diizinkan.', 3000);
    }

    function clearEditMode() {
        editingMessageId = 0;
        if (sendButton) {
            sendButton.textContent = defaultSendButtonText;
            sendButton.setAttribute('aria-label', 'Kirim pesan');
        }
        if (editIndicator) {
            editIndicator.hidden = true;
        }
    }

    async function deleteMessageFromServer(messageElement) {
        const messageId = Number(messageElement.dataset.id || 0);
        const csrfToken = csrfTokenInput ? csrfTokenInput.value : '';
        if (!Number.isFinite(messageId) || messageId <= 0 || csrfToken === '') {
            return;
        }

        const payload = new window.FormData();
        payload.append('csrf_token', csrfToken);
        payload.append('message_id', String(messageId));
        payload.append('to_user_id', String(partnerId));

        const response = await fetch('<?= BASE_URL ?>chat/deleteMessage', {
            method: 'POST',
            body: payload,
            credentials: 'same-origin'
        });
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Gagal menghapus pesan.');
        }

        if (data.data && typeof data.data === 'object') {
            applyMessageUpdate(messageElement, data.data);
        }

        if (editingMessageId === messageId) {
            clearEditMode();
            messageInput.value = '';
            resizeInput();
        }
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
                data.messages.forEach(function (msg) {
                    addMessage(msg);
                });
            }
        } catch (error) {
            // Silent polling fail to keep chat stable.
        } finally {
            isLoading = false;
        }
    }

    function resizeInput() {
        if (!messageInput) {
            return;
        }
        messageInput.style.height = 'auto';
        messageInput.style.height = Math.min(messageInput.scrollHeight, 160) + 'px';
    }

    function isMobileChatMode() {
        return window.matchMedia('(max-width: 760px)').matches || /Android|webOS|iPhone|iPad|iPod|Mobi/i.test(navigator.userAgent);
    }

    if (messageInput) {
        resizeInput();
        messageInput.addEventListener('input', resizeInput);
        messageInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                if (isMobileChatMode()) {
                    return;
                }

                event.preventDefault();
                if (typeof chatForm.requestSubmit === 'function') {
                    chatForm.requestSubmit();
                } else {
                    const submitEvent = document.createEvent('Event');
                    submitEvent.initEvent('submit', true, true);
                    chatForm.dispatchEvent(submitEvent);
                }
            }
        });
    }

    if (editCancelButton) {
        editCancelButton.addEventListener('click', function () {
            clearEditMode();
            messageInput.value = '';
            resizeInput();
            messageInput.focus();
        });
    }

    messagesContainer.addEventListener('contextmenu', function (event) {
        const messageElement = event.target.closest('.message[data-id]');
        if (!messageElement) {
            return;
        }
        if (messageElement.dataset.own !== '1' || messageElement.dataset.system === '1' || messageElement.dataset.isDeleted === '1') {
            closeContextMenu();
            return;
        }
        event.preventDefault();
        closeContextMenu();
        openContextMenuForMessage(messageElement);
    });

    messagesContainer.addEventListener('touchstart', function (event) {
        const messageElement = event.target.closest('.message[data-id]');
        if (!messageElement || messageElement.dataset.system === '1' || messageElement.dataset.own !== '1' || messageElement.dataset.isDeleted === '1') {
            return;
        }
        if (!event.touches || event.touches.length === 0) {
            return;
        }

        longPressTimer = window.setTimeout(function () {
            openContextMenuForMessage(messageElement);
        }, 520);
    }, { passive: true });

    function clearLongPress() {
        if (longPressTimer) {
            window.clearTimeout(longPressTimer);
            longPressTimer = null;
        }
    }

    messagesContainer.addEventListener('touchmove', clearLongPress, { passive: true });
    messagesContainer.addEventListener('touchend', clearLongPress, { passive: true });
    messagesContainer.addEventListener('touchcancel', clearLongPress, { passive: true });

    if (contextMenu) {
        contextMenu.addEventListener('click', async function (event) {
            const item = event.target.closest('[data-context-action]');
            if (!item || !contextTargetMessage) {
                return;
            }

            const action = item.dataset.contextAction;
            const targetMessage = contextTargetMessage;
            closeContextMenu();

            if (action === 'copy') {
                const rawText = String(targetMessage.dataset.rawContent || '').trim();
                const fallbackText = (targetMessage.querySelector('.message-content') || {}).textContent || '';
                const copied = await copyToClipboard(rawText !== '' ? rawText : fallbackText);
                showHintStatus(copied ? 'Pesan berhasil disalin.' : 'Gagal menyalin pesan.', 1800);
                return;
            }

            if (action === 'edit') {
                if (targetMessage.dataset.canEdit !== '1') {
                    showHintStatus('Pesan ini tidak bisa diedit lagi.', 2200);
                    return;
                }
                setEditMode(targetMessage);
                return;
            }

            if (action === 'delete') {
                if (targetMessage.dataset.canDelete !== '1') {
                    showHintStatus('Pesan ini tidak bisa dihapus.', 2200);
                    return;
                }

                if (!window.confirm('Hapus pesan ini?')) {
                    return;
                }

                try {
                    await deleteMessageFromServer(targetMessage);
                    showHintStatus('Pesan dihapus.', 1600);
                } catch (error) {
                    alert(error.message || 'Gagal menghapus pesan. Silakan coba lagi.');
                }
            }
        });
    }

    document.addEventListener('click', function (event) {
        if (!contextMenu || contextMenu.hidden) {
            return;
        }
        if (event.target.closest('#message-context-menu')) {
            return;
        }
        closeContextMenu();
    });

    document.addEventListener('scroll', function () {
        if (!contextMenu || contextMenu.hidden) {
            return;
        }
        closeContextMenu();
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeContextMenu();
        }
    });

    chatForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (isSending) {
            return;
        }

        const content = messageInput.value.trim();
        if (!content) {
            return;
        }

        isSending = true;
        if (sendButton) {
            sendButton.disabled = true;
        }

        const formData = new window.FormData(chatForm);
        const isEditMode = editingMessageId > 0;
        if (isEditMode) {
            formData.append('message_id', String(editingMessageId));
        }

        try {
            const endpoint = isEditMode ? '<?= BASE_URL ?>chat/editMessage' : '<?= BASE_URL ?>chat/send';
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!data.success) {
                alert(data.message || 'Aksi pesan gagal.');
                return;
            }

            if (isEditMode) {
                const target = messagesContainer.querySelector('.message[data-id="' + String(editingMessageId) + '"]');
                if (target && data.data) {
                    applyMessageUpdate(target, data.data);
                }
                clearEditMode();
                messageInput.value = '';
                resizeInput();
                showHintStatus('Pesan berhasil diperbarui.', 1800);
                return;
            }

            messageInput.value = '';
            resizeInput();
            await loadMessages();
        } catch (error) {
            alert('Pesan gagal diproses. Silakan coba lagi.');
        } finally {
            isSending = false;
            if (sendButton) {
                sendButton.disabled = false;
            }
        }
    });

    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    window.setInterval(loadMessages, 3000);
})();
</script>
