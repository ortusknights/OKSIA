(function () {
    function initChat() {
        if (typeof window.okChatData === 'undefined') {
            return;
        }

        var root = document.getElementById('oksia-chat');
        if (!root) {
            return;
        }

        var usersWrap = root.querySelector('[data-chat-users]');
        var messageWrap = root.querySelector('[data-chat-messages]');
        var titleEl = root.querySelector('[data-chat-title]');
        var statusEl = root.querySelector('[data-chat-status]');
        var form = root.querySelector('[data-chat-form]');
        var textarea = form ? form.querySelector('textarea[name="message"]') : null;
        var currentRecipientId = parseInt(root.dataset.defaultRecipientId || okChatData.defaultRecipientId || 0, 10);
        var currentRecipientLabel = '';
        var loading = false;
        var pollTimer = null;

        function setStatus(text) {
            if (statusEl) {
                statusEl.textContent = text;
            }
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        function renderEmpty(text) {
            if (!messageWrap) {
                return;
            }

            messageWrap.innerHTML = '<div class="oksia-chat-empty">' + escapeHtml(text) + '</div>';
        }

        function renderMessages(items) {
            if (!messageWrap) {
                return;
            }

            if (!items || !items.length) {
                renderEmpty(okChatData.strings.empty);
                return;
            }

            messageWrap.innerHTML = '';
            items.forEach(function (item) {
                var bubble = document.createElement('div');
                bubble.className = 'oksia-chat-message' + (item.mine ? ' is-mine' : '');
                bubble.innerHTML =
                    '<div class="oksia-chat-message__meta">' +
                    '<span>' + escapeHtml(item.sender_name || '') + '</span>' +
                    '<span>' + escapeHtml(item.time || '') + '</span>' +
                    '</div>' +
                    '<div class="oksia-chat-message__body">' + escapeHtml(item.message || '') + '</div>';
                messageWrap.appendChild(bubble);
            });
            messageWrap.scrollTop = messageWrap.scrollHeight;
        }

        function selectUser(button) {
            if (!button) {
                return;
            }

            var recipientId = parseInt(button.getAttribute('data-recipient-id') || '0', 10);
            if (!recipientId) {
                return;
            }

            currentRecipientId = recipientId;
            currentRecipientLabel = button.getAttribute('data-recipient-label') || button.textContent.trim();

            if (usersWrap) {
                usersWrap.querySelectorAll('.oksia-chat-user').forEach(function (node) {
                    node.classList.remove('is-active');
                });
            }
            button.classList.add('is-active');

            if (titleEl) {
                titleEl.textContent = currentRecipientLabel;
            }

            fetchMessages();
        }

        function fetchMessages() {
            if (!currentRecipientId || loading) {
                return;
            }

            loading = true;
            setStatus(okChatData.strings.loading);

            fetch(okChatData.restUrl + '/chat/messages?recipient_id=' + encodeURIComponent(currentRecipientId), {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': okChatData.nonce
                }
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, status: response.status, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        throw new Error((result.data && result.data.message) ? result.data.message : 'Unable to load messages.');
                    }

                    renderMessages(result.data.items || []);
                    setStatus(currentRecipientLabel || okChatData.strings.send);
                })
                .catch(function (error) {
                    renderEmpty(error.message || 'Unable to load messages.');
                    setStatus('Error');
                })
                .finally(function () {
                    loading = false;
                });
        }

        function bindUsers() {
            if (!usersWrap) {
                return;
            }

            usersWrap.addEventListener('click', function (event) {
                var button = event.target.closest('.oksia-chat-user');
                if (!button) {
                    return;
                }

                selectUser(button);
            });
        }

        function bindForm() {
            if (!form) {
                return;
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                if (!currentRecipientId) {
                    setStatus(okChatData.strings.selectUser);
                    return;
                }

                var message = textarea ? textarea.value.trim() : '';
                if (!message) {
                    return;
                }

                setStatus(okChatData.strings.send);

                fetch(okChatData.restUrl + '/chat/messages', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': okChatData.nonce
                    },
                    body: JSON.stringify({
                        recipient_id: currentRecipientId,
                        message: message
                    })
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, status: response.status, data: data };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            throw new Error((result.data && result.data.message) ? result.data.message : 'Unable to send message.');
                        }

                        if (textarea) {
                            textarea.value = '';
                        }
                        renderMessages(result.data.messages || []);
                        setStatus(currentRecipientLabel || okChatData.strings.send);
                    })
                    .catch(function (error) {
                        setStatus(error.message || 'Unable to send message.');
                    });
            });
        }

        function startPolling() {
            if (pollTimer) {
                window.clearInterval(pollTimer);
            }

            pollTimer = window.setInterval(function () {
                fetchMessages();
            }, 15000);
        }

        bindUsers();
        bindForm();

        if (currentRecipientId && usersWrap) {
            var activeButton = usersWrap.querySelector('.oksia-chat-user.is-active') || usersWrap.querySelector('.oksia-chat-user');
            if (activeButton) {
                selectUser(activeButton);
            } else {
                fetchMessages();
            }
        } else {
            renderEmpty(okChatData.strings.selectUser);
            setStatus(okChatData.strings.selectUser);
        }

        startPolling();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChat);
    } else {
        initChat();
    }
})();
