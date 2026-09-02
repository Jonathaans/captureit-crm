@php
    $internalChatUrl =
        route(
            'admin.internal-chat.index'
        );

    $internalChatUnreadUrl =
        route(
            'admin.internal-chat.unread-summary'
        );
@endphp

<script>
    (() => {
        const chatUrl =
            @json(
                $internalChatUrl
            );

        const unreadUrl =
            @json(
                $internalChatUnreadUrl
            );

        const locateChatTargets = () => {
            const direct =
                Array.from(
                    document.querySelectorAll(
                        'a[href]'
                    )
                )
                    .filter(
                        (node) => {
                            const href =
                                String(
                                    node.href
                                    || ''
                                );

                            return href === chatUrl
                                || href.startsWith(
                                    chatUrl
                                    + '?'
                                );
                        }
                    );

            if (direct.length) {
                return direct;
            }

            return Array.from(
                document.querySelectorAll(
                    'a, button'
                )
            )
                .filter(
                    (node) =>
                        String(
                            node.textContent
                            || ''
                        )
                            .trim()
                            .toLowerCase()
                        === 'chat'
                );
        };

        const ensureBadge = (
            target
        ) => {
            let badge =
                target.querySelector(
                    '[data-global-chat-unread-badge]'
                );

            if (badge) {
                return badge;
            }

            target.style.position =
                target.style.position
                || 'relative';

            badge =
                document.createElement(
                    'span'
                );

            badge.dataset.globalChatUnreadBadge =
                '1';

            badge.style.position =
                'absolute';

            badge.style.top =
                '-7px';

            badge.style.right =
                '-7px';

            badge.style.minWidth =
                '20px';

            badge.style.height =
                '20px';

            badge.style.padding =
                '0 5px';

            badge.style.borderRadius =
                '9999px';

            badge.style.background =
                '#dc2626';

            badge.style.color =
                '#ffffff';

            badge.style.fontSize =
                '11px';

            badge.style.fontWeight =
                '700';

            badge.style.lineHeight =
                '20px';

            badge.style.textAlign =
                'center';

            badge.style.boxShadow =
                '0 0 0 2px #ffffff';

            badge.style.display =
                'none';

            target.appendChild(
                badge
            );

            return badge;
        };

        const renderCount = (
            count
        ) => {
            locateChatTargets()
                .forEach(
                    (target) => {
                        const badge =
                            ensureBadge(
                                target
                            );

                        if (count > 0) {
                            badge.textContent =
                                count > 99
                                    ? '99+'
                                    : String(
                                        count
                                    );

                            badge.style.display =
                                'inline-block';
                        } else {
                            badge.style.display =
                                'none';
                        }
                    }
                );
        };

        const pollUnread =
            async () => {
                try {
                    const response =
                        await fetch(
                            unreadUrl,
                            {
                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                credentials:
                                    'same-origin',

                                cache:
                                    'no-store',
                            }
                        );

                    if (! response.ok) {
                        return;
                    }

                    const data =
                        await response.json();

                    renderCount(
                        Number(
                            data.total
                            || 0
                        )
                    );
                } catch (error) {
                    // A failed badge poll must never break the CRM page.
                }
            };

        pollUnread();

        window.setInterval(
            pollUnread,
            5000
        );
    })();
</script>
