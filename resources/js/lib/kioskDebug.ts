// Mode debug du kiosque TV (`/screen?debug=1`).
//
// Le navigateur des télévisions Samsung (Tizen) n'a pas de DevTools : quand le
// diaporama se fige, on ne voit rien. Ce module affiche à l'écran ce qui se passe
// (changements de slide, erreurs JS, promesses rejetées, battement de cœur du
// thread principal) et charge eruda, une console DevTools rendue dans la page.
//
// Activation : `?debug=1` (mémorisé en localStorage pour survivre aux rechargements),
// désactivation : `?debug=0`. Rien de tout ceci n'est chargé hors mode debug.

const STORAGE_KEY = 'screen:debug';
const ERUDA_SRC = 'https://cdn.jsdelivr.net/npm/eruda@3.4.1/eruda.js';
const MAX_LINES = 40;

declare global {
    interface Window {
        eruda?: { init: (options?: Record<string, unknown>) => void };
    }
}

export function isKioskDebugEnabled(): boolean {
    try {
        const param = new URLSearchParams(window.location.search).get('debug');

        if (param === '1') {
            window.localStorage.setItem(STORAGE_KEY, '1');
        } else if (param === '0') {
            window.localStorage.removeItem(STORAGE_KEY);
        }

        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

export type KioskLogger = (message: string, ...details: unknown[]) => void;

function formatDetail(detail: unknown): string {
    if (detail instanceof Error) {
        return `${detail.name}: ${detail.message}\n${detail.stack ?? ''}`;
    }

    try {
        return typeof detail === 'string' ? detail : JSON.stringify(detail);
    } catch {
        return String(detail);
    }
}

function timestamp(): string {
    return new Date().toISOString().slice(11, 23);
}

export function setupKioskDebug(): KioskLogger {
    const lines: string[] = [];

    const overlay = document.createElement('div');
    overlay.setAttribute('data-kiosk-debug', '');
    overlay.style.cssText = [
        'position:fixed',
        'left:0',
        'right:0',
        'bottom:0',
        'z-index:2147483646',
        'max-height:40vh',
        'overflow:hidden',
        'background:rgba(0,0,0,0.85)',
        'color:#8f8',
        'font:14px/1.4 monospace',
        'padding:8px 12px',
        'pointer-events:none',
        'white-space:pre-wrap',
        'word-break:break-all',
    ].join(';');

    const header = document.createElement('div');
    header.style.cssText = 'color:#ff8;margin-bottom:4px';

    const body = document.createElement('div');
    overlay.append(header, body);
    document.body.append(overlay);

    const userAgent = window.navigator.userAgent;
    let heartbeat = 0;

    function renderHeader(): void {
        header.textContent = `[kiosk debug] ${timestamp()} ♥${heartbeat} — ${userAgent}`;
    }

    // Si l'horloge s'arrête, c'est le thread principal qui est bloqué (pas un
    // simple événement manquant) : c'est la distinction clé sur une TV.
    window.setInterval(() => {
        heartbeat += 1;
        renderHeader();
    }, 1000);
    renderHeader();

    function push(level: string, message: string, details: unknown[]): void {
        const suffix = details.length
            ? ' ' + details.map(formatDetail).join(' ')
            : '';

        lines.push(`${timestamp()} ${level} ${message}${suffix}`);

        while (lines.length > MAX_LINES) {
            lines.shift();
        }

        body.textContent = lines.join('\n');
        overlay.scrollTop = overlay.scrollHeight;
    }

    const log: KioskLogger = (message, ...details) => {
        push('·', message, details);
        console.info(`[kiosk] ${message}`, ...details);
    };

    window.addEventListener('error', (event) => {
        push(
            '✖ error',
            event.message,
            [event.error ?? `${event.filename}:${event.lineno}:${event.colno}`],
        );
    });

    window.addEventListener('unhandledrejection', (event) => {
        push('✖ unhandledrejection', '', [event.reason]);
    });

    const originalConsoleError = console.error.bind(console);
    console.error = (...args: unknown[]) => {
        push('✖ console.error', '', args);
        originalConsoleError(...args);
    };

    const originalConsoleWarn = console.warn.bind(console);
    console.warn = (...args: unknown[]) => {
        push('⚠ console.warn', '', args);
        originalConsoleWarn(...args);
    };

    const script = document.createElement('script');
    script.src = ERUDA_SRC;
    script.onload = () => {
        try {
            window.eruda?.init();
            log('eruda chargé');
        } catch (error) {
            push('✖ eruda', 'init impossible', [error]);
        }
    };
    script.onerror = () => {
        push('⚠ eruda', 'chargement impossible (CDN bloqué ?)', []);
    };
    document.head.append(script);

    log('mode debug actif', {
        url: window.location.href,
        screen: `${window.screen.width}x${window.screen.height}`,
        viewport: `${window.innerWidth}x${window.innerHeight}`,
        caches: 'caches' in window,
    });

    return log;
}
