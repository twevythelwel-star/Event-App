/*
 * EventBite ticket offline copy (registered by public/js/ticket-collection.js with scope /tickets/).
 *
 * On an event day the signal is often poor. This worker keeps a copy of each ticket page the holder opens
 * (/tickets/{order id}) and the styles, scripts and images it uses, so the ticket can still be shown without a
 * connection. It is network-first: with a connection the page always comes fresh from the server and the copy is
 * replaced. Only when the network fails is the copy served, with its "Saved copy · you're offline" notice switched on.
 *
 * The copy is a display only. Admission is decided by the gate scan against the server, which refuses a replaced,
 * revoked, transferred or already-used QR code whatever this copy shows.
 *
 * Nothing else is touched: other pages, forms (POST), wallet downloads and other sites go straight to the network.
 * A copy is dropped when the server no longer shows the ticket to this browser (signed out, not the holder, gone),
 * and signing out clears everything (Clear-Site-Data on /logout).
 */
'use strict';

const PAGES = 'eb-ticket-pages-v1';
const ASSETS = 'eb-ticket-assets-v1';
const TICKET_PAGE = /^\/tickets\/[0-9a-f-]{36}$/i;
const MAX_PAGES = 12;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) {
      if (key.startsWith('eb-ticket-') && key !== PAGES && key !== ASSETS) await caches.delete(key);
    }
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (request.mode === 'navigate') {
    if (TICKET_PAGE.test(url.pathname)) event.respondWith(ticketPage(request, url));
    return;
  }
  if (['style', 'script', 'image', 'font'].includes(request.destination)) {
    event.respondWith(asset(request));
  }
});

async function ticketPage(request, url) {
  const cache = await caches.open(PAGES);
  const key = url.origin + url.pathname;
  let response;
  try {
    response = await fetch(request);
  } catch (offline) {
    const saved = await cache.match(key);
    if (!saved) throw offline;
    const html = await saved.text();
    const savedAt = saved.headers.get('x-eb-saved-at') || '';
    return new Response(
      html.replace('data-offline-copy hidden', 'data-offline-copy data-offline-source="cache" data-saved-at="' + savedAt + '"'),
      { headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } },
    );
  }

  const isPage = response.ok && !response.redirected && (response.headers.get('Content-Type') || '').includes('text/html');
  if (isPage) {
    const body = await response.clone().text();
    await cache.put(key, new Response(body, {
      headers: { 'Content-Type': 'text/html; charset=utf-8', 'x-eb-saved-at': new Date().toISOString() },
    }));
    await trim(cache);
  } else if (response.redirected || [401, 403, 404, 410].includes(response.status)) {
    // Signed out, no longer the holder, or the ticket is gone: keep no copy of it.
    await cache.delete(key);
  }
  return response;
}

async function asset(request) {
  const cache = await caches.open(ASSETS);
  try {
    const response = await fetch(request);
    if (response.ok && response.type === 'basic') await cache.put(request, response.clone());
    return response;
  } catch (offline) {
    const saved = await cache.match(request);
    if (saved) return saved;
    throw offline;
  }
}

async function trim(cache) {
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - MAX_PAGES; i++) await cache.delete(keys[i]);
}
