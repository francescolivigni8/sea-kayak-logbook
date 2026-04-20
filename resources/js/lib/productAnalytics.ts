interface PosthogConfig {
    enabled?: boolean;
    key?: string | null;
    host?: string | null;
}

let activeConfig: PosthogConfig | null = null;
let initialized = false;

export function initProductAnalytics(config?: PosthogConfig | null) {
    if (!config?.enabled || !config.key || typeof window === 'undefined') {
        return;
    }

    activeConfig = config;

    if (initialized) {
        return;
    }

    initialized = true;
    capturePageview();
}

export function capturePageview() {
    capture('$pageview', {
        current_url: window.location.href,
        pathname: window.location.pathname,
        title: document.title,
    });
}

function capture(event: string, properties: Record<string, unknown>) {
    if (
        !activeConfig?.enabled ||
        !activeConfig.key ||
        typeof window === 'undefined'
    ) {
        return;
    }

    const host = (activeConfig.host || 'https://eu.i.posthog.com').replace(
        /\/$/,
        '',
    );
    const payload = JSON.stringify({
        api_key: activeConfig.key,
        event,
        properties: {
            distinct_id: distinctId(),
            app: 'your-kayaking-journal',
            ...properties,
        },
    });

    if (navigator.sendBeacon) {
        navigator.sendBeacon(
            `${host}/capture/`,
            new Blob([payload], { type: 'application/json' }),
        );

        return;
    }

    void fetch(`${host}/capture/`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: payload,
        keepalive: true,
    }).catch(() => {
        // Analytics should never interrupt journal use.
    });
}

function distinctId(): string {
    const key = 'ykj:anonymous-id';
    const existing = window.localStorage.getItem(key);

    if (existing) {
        return existing;
    }

    const id = crypto.randomUUID();
    window.localStorage.setItem(key, id);

    return id;
}
