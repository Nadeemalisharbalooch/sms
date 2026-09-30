import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const DEFAULT_LOCAL_PORT = 8080;
const DEFAULT_TLS_PORT = 443;
const MAX_FAILED_ATTEMPTS = 4;
const FAILED_STATES = ['unavailable', 'failed'];
const COUNT_WINDOW_MS = 3000;

let echo = null;
let connected = false;
let failedAttempts = 0;
let lastFailureAt = 0;
let givenUp = false;
const stateListeners = new Set();

function isLoopbackHost(host) {
    return (
        host === 'localhost' ||
        host === '127.0.0.1' ||
        host === '::1' ||
        host === '[::1]' ||
        host.endsWith('.test') ||
        host.endsWith('.local')
    );
}

/**
 * One bundle has to work both locally (plain ws straight to the Reverb daemon)
 * and against the live site (wss on 443, terminated by the web server and
 * proxied to Reverb), so the endpoint is resolved at runtime:
 *
 * - an explicit non-loopback VITE_REVERB_HOST is the live/dedicated endpoint
 *   and is always dialled over TLS, no matter which origin serves the page
 * - a loopback host is only honoured for a local page (ws://127.0.0.1:8080)
 * - otherwise the page origin is used, which keeps a bundle built in one
 *   environment from dialling the other
 */
function resolveEndpoint() {
    const envHost = import.meta.env.VITE_REVERB_HOST;
    const local = isLoopbackHost(window.location.hostname);
    const tlsPort = Number(import.meta.env.VITE_REVERB_HTTPS_PORT) || DEFAULT_TLS_PORT;

    if (envHost && ! isLoopbackHost(envHost)) {
        return { host: envHost, port: tlsPort, forceTLS: true };
    }

    if (local) {
        return {
            host: envHost || '127.0.0.1',
            port: Number(import.meta.env.VITE_REVERB_PORT) || DEFAULT_LOCAL_PORT,
            forceTLS: false,
        };
    }

    return { host: window.location.hostname, port: tlsPort, forceTLS: true };
}

function notifyState(state) {
    stateListeners.forEach((listener) => {
        try {
            listener(state);
        } catch (error) {
            console.warn('Realtime state listener failed:', error);
        }
    });
}

function registerFailure() {
    const now = Date.now();

    if (now - lastFailureAt < COUNT_WINDOW_MS) {
        return false;
    }

    lastFailureAt = now;
    failedAttempts += 1;

    return failedAttempts >= MAX_FAILED_ATTEMPTS;
}

function giveUp() {
    if (givenUp) {
        return;
    }

    givenUp = true;
    echo?.connector?.pusher?.disconnect();
    notifyState('disconnected');
    console.warn(
        `Realtime notifications disabled after ${MAX_FAILED_ATTEMPTS} failed attempts. ` +
            'The notification tray falls back to polling.'
    );
}

function handleStateChange(state) {
    if (state === 'connected') {
        connected = true;
        failedAttempts = 0;
        lastFailureAt = 0;
        givenUp = false;
        notifyState('connected');
        return;
    }

    connected = false;
    notifyState('disconnected');

    if (FAILED_STATES.includes(state) && registerFailure()) {
        giveUp();
    }
}

/**
 * A refused handshake reports a WebSocketError on every retry, which is the
 * only reliable signal that the endpoint is unreachable: the connection state
 * just oscillates between "connecting" and a scheduled retry.
 */
function handleConnectionError(error) {
    connected = false;
    notifyState('disconnected');

    if (import.meta.env.DEV) {
        console.warn('Reverb WebSocket error:', error?.type || error);
    }

    if (registerFailure()) {
        giveUp();
    }
}

export function getEcho() {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    const isEnabled = import.meta.env.VITE_REVERB_ENABLED !== 'false';

    if (! key || ! isEnabled) {
        return null;
    }

    if (! echo) {
        try {
            const endpoint = resolveEndpoint();

            echo = new Echo({
                broadcaster: 'reverb',
                key: key,
                wsHost: endpoint.host,
                wsPort: endpoint.port,
                wssPort: endpoint.port,
                forceTLS: endpoint.forceTLS,
                enabledTransports: ['ws', 'wss'],
            });

            if (echo.connector?.pusher?.connection) {
                echo.connector.pusher.connection.bind('state_change', ({ current }) => handleStateChange(current));
                echo.connector.pusher.connection.bind('error', (error) => handleConnectionError(error));
            }
        } catch (e) {
            console.warn('Failed to initialize Laravel Echo / Reverb:', e);
            echo = null;
        }
    }

    return echo;
}

/**
 * Subscribe to connection state changes: 'connected' or 'disconnected'.
 */
export function onRealtimeStateChange(listener) {
    stateListeners.add(listener);
    listener(connected ? 'connected' : 'disconnected');

    return () => stateListeners.delete(listener);
}

export function isRealtimeConnected() {
    return connected;
}

export default getEcho;
