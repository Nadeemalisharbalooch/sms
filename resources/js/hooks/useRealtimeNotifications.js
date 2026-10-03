import { useEffect, useRef, useState } from 'react';

const NOTIFICATION_EVENT = 'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated';
const DEFAULT_POLL_INTERVAL = 15000;
const MAX_TOASTS = 4;
const latestItemsByUser = new Map();
const itemListenersByUser = new Map();

export function useRealtimeNotificationItems(userId) {
    const [items, setItems] = useState(() => latestItemsByUser.get(userId) || []);

    useEffect(() => {
        const listeners = itemListenersByUser.get(userId) || new Set();
        listeners.add(setItems);
        itemListenersByUser.set(userId, listeners);
        setItems(latestItemsByUser.get(userId) || []);

        return () => {
            listeners.delete(setItems);
            if (listeners.size === 0) {
                itemListenersByUser.delete(userId);
            }
        };
    }, [userId]);

    return items;
}

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
    const seenRef = useRef(new Set());
    const baselineRef = useRef(false);

    const push = (incoming) => {
        if (! incoming.length) {
            return;
        }

        setUnread((count) => count + incoming.length);
        const sharedItems = [...incoming, ...(latestItemsByUser.get(user.id) || [])].slice(0, 50);
        latestItemsByUser.set(user.id, sharedItems);
        setItems(sharedItems);
        itemListenersByUser.get(user.id)?.forEach((listener) => listener(sharedItems));
        setToasts((list) => [...incoming, ...list].slice(0, MAX_TOASTS));
    };

    useEffect(() => {
        if (! user?.id) {
            return undefined;
        }

        const name = channelName(user.id);
        let timer = null;
        let cancelled = false;
        let pollInFlight = false;
        let echo = null;
        let unwatch = () => {};

        const poll = async () => {
            if (pollInFlight) {
                return;
            }

            pollInFlight = true;

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
            } finally {
                pollInFlight = false;
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

        const attachRealtime = async () => {
            if (! import.meta.env.VITE_REVERB_APP_KEY || import.meta.env.VITE_REVERB_ENABLED === 'false') {
                if (! cancelled) {
                    startPolling();
                }

                return;
            }

            try {
                const { default: getEcho, onRealtimeStateChange } = await import('../echo');
                if (cancelled) {
                    return;
                }

                echo = getEcho();
                unwatch = onRealtimeStateChange((state) => {
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

                if (echo) {
                    echo.private(name).listen(`.${NOTIFICATION_EVENT}`, (payload) => {
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
            } catch (error) {
                if (import.meta.env.DEV) {
                    console.warn('Realtime notifications unavailable:', error);
                }

                if (! cancelled) {
                    startPolling();
                }
            }
        };

        // Seed the baseline on mount so anything delivered later, over either
        // transport, is counted as new. Load the realtime client after the page
        // has rendered; sites without Reverb skip that code entirely.
        poll();
        attachRealtime();

        return () => {
            cancelled = true;
            stopPolling();
            unwatch();
            echo?.leaveChannel(name);
        };
    }, [user?.id]);

    return { unread, items, toasts, transport };
}
