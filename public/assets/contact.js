(() => {
    'use strict';

    const initialized = new WeakSet();
    const configuration = new Map();
    const utmKeys = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content',
        'utm_source_platform', 'utm_creative_format', 'utm_marketing_tactic',
    ];
    let sdk;
    const defaults = {
        error: 'No se pudo enviar el mensaje. Inténtalo de nuevo.',
        checking: 'Espera a que termine la verificación.',
        loading: 'Enviando…',
        unavailable: 'No se pudo preparar el formulario. Recarga la página.',
    };

    async function json(url, options = {}, timeout = 15000) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeout);
        try {
            const response = await fetch(url, {...options, credentials: 'same-origin', signal: controller.signal});
            return {response, data: await response.json()};
        } finally {
            clearTimeout(timer);
        }
    }

    function publicConfig(url) {
        if (!configuration.has(url.href)) {
            configuration.set(url.href, json(url).then(({response, data}) => {
                if (!response.ok || data.success !== true) {
                    throw new Error('config');
                }
                return data;
            }).catch(error => {
                configuration.delete(url.href);
                throw error;
            }));
        }
        return configuration.get(url.href);
    }

    function turnstile() {
        if (!sdk) {
            sdk = new Promise((resolve, reject) => {
                let timer;
                const check = () => {
                    if (window.turnstile ?.render) {
                        clearInterval(timer);
                        resolve(window.turnstile);
                    } else if (Date.now() >= deadline) {
                        clearInterval(timer);
                        reject(new Error('sdk'));
                    }
                };
                const deadline = Date.now() + 12000;
                timer = setInterval(check, 75);
                const existing = document.querySelector('script[src^="https://challenges.cloudflare.com/turnstile/v0/api.js"]');
                if (!window.turnstile && !existing) {
                    const script = document.createElement('script');
                    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
                    script.async = true;
                    script.onerror = () => {
                        clearInterval(timer);
                        script.remove();
                        reject(new Error('sdk'));
                    };
                    document.head.append(script);
                }
                check();
            }).catch(error => {
                sdk = undefined;
                throw error;
            });
        }
        return sdk;
    }

    function speak(element, message) {
        element.textContent = message;
    }

    function trigger(form, name, detail, cancelable = false) {
        return form.dispatchEvent(new CustomEvent('contact:' + name, {bubbles: true, detail, cancelable}));
    }

    function payload(form, submitter) {
        const values = Object.create(null);
        for (const [raw, value] of new FormData(form, submitter)) {
            const name = raw.endsWith('[]') ? raw.slice(0, -2) : raw;
            if (value instanceof File) {
                if (value.name) {
                    throw new Error('files');
                }
                continue;
            }
            if (Object.hasOwn(values, name)) {
                values[name] = [].concat(values[name], value);
            } else {
                values[name] = raw === name ? value : [value];
            }
        }
        return values;
    }

    function fieldErrors(form, errors) {
        for (const item of form.querySelectorAll('[data-contact-error-for]')) {
            item.textContent = typeof errors[item.dataset.contactErrorFor] === 'string'
                ? errors[item.dataset.contactErrorFor] : '';
        }
        let first;
        for (const element of form.elements) {
            if (!element.name || element.type === 'hidden') {
                continue;
            }
            if (element.dataset.contactInvalid === 'true') {
                element.removeAttribute('aria-invalid');
                delete element.dataset.contactInvalid;
            }
            if (Object.hasOwn(errors, element.name.replace(/\[\]$/, ''))) {
                element.setAttribute('aria-invalid', 'true');
                element.dataset.contactInvalid = 'true';
                first ??= element;
            }
        }
        return first;
    }

    function utmFromPage() {
        const query = new URLSearchParams(window.location.search);
        const attribution = new URLSearchParams();
        const encoder = new TextEncoder();
        for (const key of utmKeys) {
            const value = query.getAll(key).find(item =>
                item.trim() && encoder.encode(item).length <= 256 && !/[\x00-\x1F\x7F-\x9F]/.test(item)
            );
            if (value !== undefined) {
                attribution.set(key, value.trim());
            }
        }
        return attribution.toString();
    }

    async function initialize(form) {
        if (initialized.has(form)) {
            return;
        }
        initialized.add(form);
        const formId = form.dataset.formId || 'contact';
        const feedback = form.querySelector('[data-contact-feedback]') || document.createElement('div');
        if (!feedback.parentElement) {
            form.append(feedback);
        }
        feedback.setAttribute('aria-live', 'polite');
        feedback.setAttribute('aria-atomic', 'true');
        const setState = (state, message) => {
            form.dataset.contactState = state;
            speak(feedback, message);
        };
        const errorMessage = (name) => form.dataset['contact' + name[0].toUpperCase() + name.slice(1)] || defaults[name];
        let config;
        let widget;
        let client;
        let token = '';
        let active = false;
        let signature;
        let key;

        setState('idle', '');
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (active) {
                return;
            }
            if (!config || (config.turnstile.enabled && !token)) {
                setState('error', errorMessage(config ? 'checking' : 'unavailable'));
                return;
            }
            if (!trigger(form, 'before-submit', {formId}, true)) {
                return;
            }
            active = true;
            fieldErrors(form, {});
            const buttons = Array.from(form.elements).filter(item => ['submit', 'image'].includes(item.type) && !item.disabled);
            try {
                const values = payload(form, event.submitter);
                values.form_id = formId;
                delete values['cf-turnstile-response'];
                delete values.csrf_token;
                if (config.capture_utm) {
                    delete values.utm;
                    const utm = utmFromPage();
                    if (utm) {
                        values.utm = utm;
                    }
                }
                const nextSignature = JSON.stringify(values);
                if (signature !== nextSignature || !key) {
                    key = globalThis.crypto ?.randomUUID ?.();
                    signature = nextSignature;
                }
                if (config.turnstile.enabled) {
                    values['cf-turnstile-response'] = token;
                }
                if (config.csrf_token) {
                    values.csrf_token = config.csrf_token;
                }
                const headers = {'Content-Type': 'application/json'};
                if (key) {
                    headers['Idempotency-Key'] = key;
                }
                for (const button of buttons) {
                    button.disabled = true;
                }
                form.setAttribute('aria-busy', 'true');
                setState('loading', errorMessage('loading'));
                const endpoint = form.dataset.endpoint || '/contact/contact.php';
                const timeout = Number(form.dataset.contactTimeout) || 60000;
                const {response, data} = await json(endpoint, {method: 'POST', headers, body: JSON.stringify(values)}, timeout);
                if (response.ok && data.success === true) {
                    form.reset();
                    key = undefined;
                    setState('success', typeof data.message === 'string' ? data.message : '');
                    trigger(form, 'success', {formId, status: response.status, response: data});
                } else {
                    const errors = data.errors && typeof data.errors === 'object' ? data.errors : {};
                    const first = fieldErrors(form, errors);
                    setState('error', typeof data.message === 'string' ? data.message : errorMessage('error'));
                    first ?.focus();
                    trigger(form, 'error', {formId, status: response.status, response: data});
                }
            } catch (_) {
                setState('error', errorMessage('error'));
                trigger(form, 'error', {formId, status: 0});
            } finally {
                active = false;
                for (const button of buttons) {
                    button.disabled = false;
                }
                form.removeAttribute('aria-busy');
                token = '';
                if (widget !== undefined) {
                    try {
                        client.reset(widget); } catch (_) {
                        setState('error', errorMessage('unavailable')); }
                }
                trigger(form, 'complete', {formId});
            }
        });

        try {
            const endpoint = new URL(form.dataset.configEndpoint || '/contact/config.php', document.baseURI);
            endpoint.searchParams.set('form_id', formId);
            const data = await publicConfig(endpoint);
            if (data.honeypot.enabled && !form.elements.namedItem(data.honeypot.field)) {
                const container = document.createElement('div');
                container.hidden = true;
                const trap = document.createElement('input');
                trap.type = 'text';
                trap.name = data.honeypot.field;
                trap.tabIndex = -1;
                trap.autocomplete = 'off';
                container.append(trap);
                form.append(container);
            }
            if (data.turnstile.enabled) {
                let container = form.querySelector('[data-contact-turnstile]');
                if (!container) {
                    container = document.createElement('div');
                    form.append(container);
                }
                client = await turnstile();
                widget = client.render(container, {
                    sitekey: data.turnstile.site_key,
                    action: data.turnstile.action,
                    theme: data.turnstile.theme,
                    appearance: data.turnstile.appearance,
                    size: data.turnstile.size,
                    'response-field': false,
                    callback: value => { token = value; },
                    'expired-callback': () => { token = ''; },
                    'timeout-callback': () => { token = ''; },
                    'error-callback': () => {
                        token = '';
                        if (!active) {
                            setState('error', errorMessage('unavailable'));
                        }
                    },
                });
            }
            config = data;
            trigger(form, 'ready', {formId});
        } catch (_) {
            setState('error', errorMessage('unavailable'));
            trigger(form, 'error', {formId, status: 0});
        }
    }

    window.ContactModule = Object.freeze({
        initialize(root = document) {
            if (root instanceof HTMLFormElement && root.matches('[data-contact-form]')) {
                return initialize(root);
            }
            return Promise.all(Array.from(root.querySelectorAll('form[data-contact-form]'), initialize));
        },
    });
if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.ContactModule.initialize(), {once: true});
} else {
    window.ContactModule.initialize();
}
})();
