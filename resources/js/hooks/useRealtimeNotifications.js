import { useEffect, useRef, useState } from 'react';
import getEcho from '../echo';

const NOTIFICATION_EVENT = 'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated';

function channelName(userId) {
    return `App.Models.User.${userId}`;
}

/**
 * Subscribes the signed-in user to their private notification channel and
 * surfaces any notification pushed over the WebSocket connection:
 * - `unread`: how many arrived while the page was open
 * - `items`: the incoming notifications (newest first)
 * - `toasts`: a short ring buffer of the most recent incoming notifications
 */
export default function useRealtimeNotifications(user) {
    const [unread, setUnread] = useState(0);
    const [items, setItems] = useState([]);
    const [toasts, setToasts] = useState([]);
    const channelRef = useRef(null);

    useEffect(() => {
        if (! user?.id) {
            return undefined;
        }

        const echo = getEcho();
        if (! echo) {
            return undefined;
        }

        const name = channelName(user.id);
        const channel = echo.private(name);
        channelRef.current = channel;

        channel.listen(`.${NOTIFICATION_EVENT}`, (payload) => {
            const data = payload || {};
            const item = {
                id: data.id || null,
                title: data.title || 'Notification',
                body: data.body || '',
                priority: data.priority || 'standard',
                category: data.category || 'general',
                action_text: data.action_text || null,
                action_url: data.action_url || null,
                data: data.data || {},
                read_at: null,
                created_at: new Date().toISOString(),
            };

            setUnread((count) => count + 1);
            setItems((list) => [item, ...list]);
            setToasts((list) => [item, ...list].slice(0, 4));
        });

        return () => {
            echo.leaveChannel(name);
            channelRef.current = null;
        };
    }, [user?.id]);

    return { unread, items, toasts };
}