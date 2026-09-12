// Run with: node --test tests/account-unread-counts.mjs
// Exercise the shipped bundle's account model with an in-memory mail transport.
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../app/snappymail/v/2.38.2/static/js/app.js', import.meta.url), 'utf8');
const accountCode = source.slice(source.indexOf('\tclass AccountModel '), source.indexOf('\tclass IdentityModel '));
const observable = initial => {
  let value = initial;
  return function (next) { if (arguments.length) value = next; return value; };
};

function setup() {
  let now = 100000;
  const requests = [], accounts = [], enabled = observable(true), inboxUnread = observable(16);
  const store = () => accounts;
  store.email = observable('main@example.test');
  store.forEach = callback => accounts.forEach(callback);
  const context = vm.createContext({
    AbstractModel: class {},
    addObservablesTo(target, values) { for (const [key, value] of Object.entries(values)) target[key] = observable(value); },
    IDN: { toUnicode: value => value },
    SettingsUserStore: { showUnreadCount: enabled },
    AccountUserStore: store,
    getFolderInboxName: () => 'INBOX',
    getFolderFromCacheList: () => ({ unreadEmails: inboxUnread }),
    Date: { now: () => now },
    Remote: { request(action, callback, params) { requests.push({ action, callback, params }); } }
  });
  vm.runInContext(accountCode + '\nglobalThis.Model = AccountModel; globalThis.refresh = refreshAccountUnreadCounts;', context);
  const main = new context.Model('main@example.test', '', false);
  const additional = new context.Model('other@example.test', '', true);
  accounts.push(main, additional);
  return { context, main, additional, requests, enabled, inboxUnread, accounts, store, advance: ms => now += ms };
}

test('the active inbox supplies its count; only inactive accounts need a request', () => {
  const s = setup();
  s.context.refresh();
  assert.equal(s.main.unreadEmails(), 16);
  assert.equal(s.requests.length, 1);
  assert.equal(s.requests[0].params.email, 'other@example.test');
  s.requests[0].callback(0, { Result: { unreadEmails: 5 } });
  assert.equal(s.additional.unreadEmails(), 5);
});

test('the main account is refreshed while an additional account is active', () => {
  const s = setup();
  s.store.email(s.additional.email);
  s.inboxUnread(8);
  s.context.refresh();
  assert.equal(s.additional.unreadEmails(), 8);
  assert.equal(s.requests[0].params.email, 'main@example.test');
  s.requests[0].callback(0, { Result: { unreadEmails: 12 } });
  assert.equal(s.main.unreadEmails(), 12);
});

test('repeated menu openings are throttled and overlapping requests are suppressed', () => {
  const s = setup();
  s.context.refresh();
  s.context.refresh();
  s.advance(31000);
  s.context.refresh();
  assert.equal(s.requests.length, 1);
  s.requests[0].callback(0, { Result: { unreadEmails: 5 } });
  s.context.refresh();
  assert.equal(s.requests.length, 2);
  s.requests[1].callback(0, { Result: { unreadEmails: 6 } });
  s.context.refresh();
  assert.equal(s.requests.length, 2);
});

test('zero and failed requests clear previously displayed counts', () => {
  const s = setup();
  s.additional.unreadEmails(5);
  s.context.refresh();
  s.requests[0].callback(0, { Result: { unreadEmails: 0 } });
  assert.equal(s.additional.unreadEmails(), null);
  s.advance(31000);
  s.additional.unreadEmails(5);
  s.context.refresh();
  s.requests[1].callback(1, {});
  assert.equal(s.additional.unreadEmails(), null);
});

test('disabling counts clears badges and an in-flight reply cannot restore them', () => {
  const s = setup();
  s.context.refresh();
  s.enabled(false);
  s.context.refresh();
  s.requests[0].callback(0, { Result: { unreadEmails: 5 } });
  assert.equal(s.main.unreadEmails(), null);
  assert.equal(s.additional.unreadEmails(), null);
  assert.equal(s.requests.length, 1);
});

test('late responses for removed account models are ignored', () => {
  const s = setup();
  s.context.refresh();
  s.accounts.splice(1, 1);
  s.requests[0].callback(0, { Result: { unreadEmails: 5 } });
  assert.equal(s.additional.unreadEmails(), null);
});

test('re-enabling counts immediately reloads values cleared while disabled', () => {
  const s = setup();
  s.context.refresh();
  s.requests[0].callback(0, { Result: { unreadEmails: 5 } });
  s.enabled(false);
  s.context.refresh();
  s.enabled(true);
  s.context.refresh();
  assert.equal(s.main.unreadEmails(), 16);
  assert.equal(s.requests.length, 2);
});

test('the existing mail-check timer also refreshes account counts', () => {
  let callback, refreshes = 0;
  const code = source.slice(source.indexOf('\tsetRefreshFoldersInterval = minutes => {'), source.indexOf('\n\tsortFolders ='));
  const context = vm.createContext({
    Math, pInt: Number, SettingsGet: () => 5, clearInterval() {},
    setInterval(fn) { callback = fn; return 1; },
    FolderUserStore: { currentFolderFullName: () => 'Archive' },
    getFolderInboxName: () => 'INBOX', folderInformation() {}, folderInformationMultiply() {},
    refreshAccountUnreadCounts() { refreshes++; }
  });
  vm.runInContext('let refreshInterval, refreshFoldersInterval; const ' + code.trim().replace(/,$/, ';') + '\nsetRefreshFoldersInterval(5);', context);
  callback();
  assert.equal(refreshes, 1);
});
