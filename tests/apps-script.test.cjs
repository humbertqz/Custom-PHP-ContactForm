'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {createHash} = require('node:crypto');

const source = fs.readFileSync(path.join(__dirname, '../integrations/google-apps-script/Code.gs'), 'utf8');

function fixture() {
    const storage = [];
    const token = 'x'.repeat(48);
    let locked = false;
    const props = {
        CONTACT_TOKEN: token,
        CONTACT_SPREADSHEET_ID: 'owner-spreadsheet',
        CONTACT_FORM_MAP: JSON.stringify({contact: {tab: 'Contact', fields: ['name', 'message', 'topics']}}),
    };
    const context = vm.createContext({
        ContentService: {
            createTextOutput: text => ({setMimeType: () => JSON.parse(text)}),
            MimeType: {JSON: 'json'},
        },
        LockService: {getScriptLock: () => ({
            tryLock: () => { locked = true; return true; },
            hasLock: () => locked,
            releaseLock: () => { locked = false; },
        })},
        PropertiesService: {getScriptProperties: () => ({getProperty: name => props[name]})},
        Utilities: {
            DigestAlgorithm: {SHA_256: 'sha256'},
            Charset: {UTF_8: 'utf8'},
            computeDigest: (algorithm, val, encoding) => Array.from(createHash(algorithm).update(val, encoding).digest()),
        },
        SpreadsheetApp: {
            flush: () => assert.equal(locked, true),
            openById: id => {
                assert.equal(id, 'owner-spreadsheet');
                return {getSheetByName: name => {
                    assert.equal(name, 'Contact');
                    return {
                        getLastRow: () => storage.length,
                        getMaxRows: () => 100,
                        getMaxColumns: () => 12,
                        getRange: row => ({
                            getValues: () => [storage[row - 1]],
                            setNumberFormat: () => assert.equal(locked, true),
                            setValues: values => {
                                assert.equal(locked, true);
                                storage[row - 1] = Array.from(values[0]);
                            },
                            createTextFinder: uuid => ({
                                matchEntireCell: () => ({matchCase: () => ({
                                    findNext: () => storage.slice(1).find(existing => existing[0] === uuid),
                                })}),
                            }),
                        }),
                    };
                }};
            },
        },
    });
    vm.runInContext(source, context);
    const submission = {
        id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        form_id: 'contact',
        created_at: '2026-01-01T12:00:00Z',
        fields: {name: 'Juan', message: '  =1+2', topics: ['uno']},
    };
    const post = (value = {token, submission}) => {
        const contents = JSON.stringify(value);
        return context.doPost({postData: {type: 'application/json', contents, length: Buffer.byteLength(contents)}});
    };
    return {post, submission, token, storage};
}

test('Appends sanitized values and repeated UUID preserves manual fields', () => {
    const {post, storage} = fixture();
    assert.equal(post().status, 'stored');
    assert.equal(storage[1][7], "'  =1+2");
    assert.equal(storage[1][8], '{"topics":["uno"]}');
    storage[1][10] = 'Atendido';
    assert.equal(post().status, 'already_stored');
    assert.equal(storage.length, 2);
    assert.equal(storage[1][10], 'Atendido');
});

test('Rejects unauthorized token, route, field, date and spreadsheet selection', () => {
    const {post, token, submission, storage} = fixture();
    assert.equal(post({token: 'bad', submission}).code, 'UNAUTHORIZED');
    for (const changed of [
        {route: 'another'},
        {form_id: 'unknown'},
        {fields: {unauthorized: 'test'}},
        {created_at: '2026-02-30T12:00:00Z'},
        {spreadsheet_id: 'not-owner'},
    ]) {
        assert.equal(post({token, submission: {...submission, ...changed}}).code, 'INVALID_REQUEST');
    }
    assert.equal(storage.length, 0);
});

test('Stores UTM attribution in one additional field without changing the form mapping', () => {
    const {post, token, submission, storage} = fixture();
    const fields = {...submission.fields, utm: 'utm_campaign=Ofertas%20de%20verano&utm_source=google'};
    assert.equal(post({token, submission: {...submission, fields: {...fields, utm: 'token=private'}}}).code,
        'INVALID_REQUEST');
    assert.equal(post({token, submission: {...submission, fields}}).status, 'stored');
    assert.equal(JSON.parse(storage[1][8]).utm, fields.utm);
});
