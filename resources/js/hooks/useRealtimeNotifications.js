import { useEffect, useRef, useState } from 'react';
import getEcho, { onRealtimeStateChange } from '../echo';

const NOTIFICATION_EVENT = 'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated';
const DEFAULT_POLL_INTERVAL = 15000;
const MAX_TOASTS = 4;

function channelName(userId) {
    return `App.Models.User.${userId}`;
}

function pollInterval() {
    return Number(import.meta.env.VITE_REALTIME_POLL_INTERVAL) || DEFAULT_POLL_INTERVAL;
}

/**
 * Shape a broadcast payload. Broadcast events carry the notification fields at
 * the top level, mirroring {@see \App\Notifications\BaseNotification::toBroadcast}.
 */
function fromBroadcast(data) {
    const payload = data || {};

    return {
        id: payload.id || null,
        title: payload.title || 'Notification',
        body: payload.body || '',
        priority: payload.priority || 'standard',
        category: payload.category || 'general',
        action_text: payload.action_text || null,
        action_url: payload.action_url || null,
        data: payload.data || {},
        read_at: null,
        created_at: new Date().toISOString(),
    };
}

/**
 * Shape a row from the polling feed, where the notification fields live under
 * "data" because that is how they are persisted.
 */
function fromFeed(entry) {
    const payload = entry?.data || {};

    return {
        id: entry?.id || null,
        title: payload.title || 'Notification',
        body: payload.body || '',
        priority: payload.priority || 'standard',
        category: payload.category || 'general',
        action_text: payload.action_text || null,
        action_url: payload.action_url || null,
        data: payload.data || {},
        read_at: entry?.read_at || null,
        created_at: entry?.created_at || new Date().toISOString(),
    };
}

/**
 * Subscribes the signed-in user to their private notification channel and
 * surfaces any notification that arrives:
 * - `unread`: how many arrived while the page was open
 * - `items`: the incoming notifications (newest first)
 * - `toasts`: a short ring buffer of the most recent incoming notifications
 * - `transport`: 'websocket' while the socket is up, 'polling' while falling
 *   back to the JSON feed, so the UI can be honest about degraded realtime.
 *
 * The polling fallback keeps the tray live when Reverb is unreachable (missing
 * reverse proxy, daemon down, offline) instead of silently going stale.
 */
export default function useRealtimeNotifications(user) {
    const [unread, setUnread] = useState(0);
    const [items, setItems] = useState([]);
    const [toasts, setToasts] = useState([]);
    const [transport, setTransport] = useState('polling');
    const channelRef = useRef(null);
    const seenRef = useRef(new Set());
    const baselineRef = useRef(false);

    const push = (incoming) => {
        if (! incoming.length) {
            return;
        }

        setUnread((count) => count + incoming.length);
        setItems((list) => [...incoming, ...list]);
        setToasts((list) => [...incoming, ...list].slice(0, MAX_TOASTS));
    };

    useEffect(() => {
        if (! user?.id) {
            return undefined;
        }

        const echo = getEcho();
        const name = channelName(user.id);
        let timer = null;
        let cancelled = false;

        const poll = async () => {
            try {
                const response = await fetch(route('notifications.feed'), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                // An expired session redirects to the login screen; Inertia owns
                // that transition, so stop polling instead of fighting it.
                if (cancelled || response.redirected || ! response.ok) {
                    return;
                }

                const payload = await response.json();
                const entries = payload?.notifications || [];
                const fresh = [];

                entries.forEach((entry) => {
                    if (entry?.id && seenRef.current.has(entry.id)) {
                        return;
                    }

                    if (entry?.id) {
                        seenRef.current.add(entry.id);
                    }

                    fresh.push(fromFeed(entry));
                });

                if (baselineRef.current) {
                    push(fresh);
                } else {
                    baselineRef.current = true;
                }
            } catch (error) {
                if (import.meta.env.DEV) {
                    console.warn('Notification poll failed:', error);
                }
            }
        };

        const startPolling = () => {
            if (timer) {
                return;
            }

            setTransport('polling');
            timer = setInterval(poll, pollInterval());
        };

        const stopPolling = () => {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        };

        const unwatch = onRealtimeStateChange((state) => {
            if (cancelled) {
                return;
            }

            if (state === 'connected') {
                setTransport('websocket');
                stopPolling();
                return;
            }

            startPolling();
        });

        // Seed the baseline on mount so anything delivered later, over either
        // transport, is counted as new.
        poll();

        if (echo) {
            const channel = echo.private(name);
            channelRef.current = channel;

            channel.listen(`.${NOTIFICATION_EVENT}`, (payload) => {
                const item = fromBroadcast(payload);
                const data = payload || {};

                if (data.id) {
                    if (seenRef.current.has(data.id)) {
                        return;
                    }

                    seenRef.current.add(data.id);
                }

                push([item]);
            });
        }

        return () => {
            cancelled = true;
            stopPolling();
            unwatch();
            echo?.leaveChannel(name);
            channelRef.current = null;
        };
    }, [user?.id]);

    return { unread, items, toasts, transport };
}