'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {randomUUID} = require('node:crypto');

class Element extends EventTarget {
    dataset = {};
    attributes = new Map();
    textContent = '';
    parentElement = null;

    constructor(type = '', name = '') {
        super();
        this.type = type;
        this.name = name;
    }

    setAttribute(key, value) {
        this.attributes.set(key, value);
    }

    removeAttribute(key) {
        this.attributes.delete(key);
    }
}

class Form extends Element {
    feedback = new Element();
    challenge = new Element();
    button = new Element('submit');
    error = new Element();
    email = new Element('email', 'email');
    resets = 0;

    constructor(id) {
        super();
        this.dataset.formId = id;
        this.elements = [this.email, this.button];
        this.elements.namedItem = name => this.elements.find(item => item.name === name);
        this.values = [['email', 'visitor@example.com'], ['topics[]', 'one'], ['topics[]', 'two']];
        this.error.dataset.contactErrorFor = 'email';
        this.feedback.parentElement = this;
        this.email.focus = () => { this.email.focused = true; };
    }

    reset() {
        this.resets++;
    }

    matches() {
        return true;
    }

    append(element) {
        element.parentElement = this;
    }

    querySelector(selector) {
        return selector.includes('feedback') ? this.feedback : this.challenge;
    }

    querySelectorAll() {
        return [this.error];
    }
}

test('Initializes two widgets and submits independent tokens, grouped fields and safe errors', async () => {
    const pending = [];
    const widgets = new Map();
    const reset = [];
    const requests = [];
    const window = {
        location: {search: '?utm_source=google&utm_source=other&utm_campaign=Ofertas+de+verano&token=private'},
        turnstile: {
            render: (container, config) => {
                widgets.set(config.action, config);
                return config.action;
            },
            reset: id => reset.push(id),
        },
    };
    const context = vm.createContext({
        AbortController, CustomEvent, File, HTMLFormElement: Form, TextEncoder, URL, URLSearchParams,
        setTimeout, clearTimeout, setInterval, clearInterval,
        crypto: {randomUUID},
        window,
        document: {
            readyState: 'loading',
            baseURI: 'https://example.test/',
            addEventListener: () => {},
            querySelector: () => null,
        },
        FormData: class {
            constructor(form) {
                return form.values;
            }
        },
        fetch: async (url, options) => {
            if (String(url).includes('config.php')) {
                const action = new URL(url).searchParams.get('form_id');
                return {
                    ok: true,
                    json: async () => ({
                        success: true,
                        capture_utm: true,
                        honeypot: {enabled: false},
                        turnstile: {enabled: true, site_key: 'public', action, appearance: 'interaction-only'},
                    }),
                };
            }
            requests.push({body: JSON.parse(options.body), key: options.headers['Idempotency-Key']});
            return new Promise(resolve => pending.push(resolve));
        },
    });
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/assets/contact.js'), 'utf8'), context);
    const contact = new Form('contact');
    const quote = new Form('quotation');
    await Promise.all([window.ContactModule.initialize(contact), window.ContactModule.initialize(quote)]);
    await window.ContactModule.initialize(quote);
    assert.equal(widgets.size, 2);
    widgets.get('contact').callback('contact-token');
    widgets.get('quotation').callback('quote-token');
    const submit = form => form.dispatchEvent(new Event('submit', {cancelable: true}));
    const complete = form => new Promise(resolve => form.addEventListener('contact:complete', resolve, {once: true}));
    const contactDone = complete(contact);
    const quotationDone = complete(quote);
    submit(contact);
    submit(contact);
    window.location.search = '?token=private';
    submit(quote);
    assert.equal(requests.length, 2);
    assert.equal(contact.button.disabled, true);
    assert.deepEqual(requests[0].body.topics, ['one', 'two']);
    assert.equal(requests[0].body.utm, 'utm_source=google&utm_campaign=Ofertas+de+verano');
    assert.equal(Object.hasOwn(requests[1].body, 'utm'), false);
    assert.equal(requests[0].body['cf-turnstile-response'], 'contact-token');
    assert.equal(requests[1].body['cf-turnstile-response'], 'quote-token');
    assert.notEqual(requests[0].key, requests[1].key);
    const message = '<img src=x onerror=alert(1)>';
    pending[0]({ok: false, status: 422, json: async () => ({success: false, message, errors: {email: message}})});
    pending[1]({ok: true, status: 202, json: async () => ({success: true, message: 'Recibido'})});
    await Promise.all([contactDone, quotationDone]);
    assert.equal(contact.feedback.textContent, message);
    assert.equal(contact.error.textContent, message);
    assert.equal(contact.email.focused, true);
    assert.equal(contact.dataset.contactState, 'error');
    assert.equal(quote.dataset.contactState, 'success');
    assert.equal(quote.resets, 1);
    assert.equal(contact.button.disabled, false);
    assert.deepEqual(reset, ['contact', 'quotation']);
});
