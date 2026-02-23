(function () {
  'use strict';

  if (typeof window.ATSLiveChat === 'undefined') {
    return;
  }

  var config = window.ATSLiveChat;
  var restBase = normalizeRestBase(config.restBase || '');
  var state = {
    visitorId: '',
    conversationId: '',
    lastMessageTs: 0,
    panelOpen: false,
    pollTimer: null,
    presenceTimer: null,
    typingTimer: null,
    typingCooldownTimer: null,
    agentsOnline: false,
    aiMode: 'off',
    updateReloadScheduled: false,
    presenceFailures: 0,
    lastPresenceErrorAt: 0
  };

  var els = {};

  function $(id) {
    return document.getElementById(id);
  }

  function getStorage(key) {
    try {
      return localStorage.getItem(key) || '';
    } catch (e) {
      return '';
    }
  }

  function setStorage(key, value) {
    try {
      localStorage.setItem(key, value);
    } catch (e) {
      // Ignore storage errors.
    }
  }

  function getSession(key) {
    try {
      return window.sessionStorage.getItem(key) || '';
    } catch (e) {
      return '';
    }
  }

  function setSession(key, value) {
    try {
      window.sessionStorage.setItem(key, value);
    } catch (e) {
      // Ignore storage errors.
    }
  }

  function removeSession(key) {
    try {
      window.sessionStorage.removeItem(key);
    } catch (e) {
      // Ignore storage errors.
    }
  }

  function uuidV4() {
    if (window.crypto && window.crypto.randomUUID) {
      return window.crypto.randomUUID();
    }

    var dt = new Date().getTime();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (dt + Math.random() * 16) % 16 | 0;
      dt = Math.floor(dt / 16);
      return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
  }

  function readCookie(name) {
    var parts = document.cookie.split(';');
    for (var i = 0; i < parts.length; i += 1) {
      var row = parts[i].trim();
      if (row.indexOf(name + '=') === 0) {
        return decodeURIComponent(row.substring(name.length + 1));
      }
    }
    return '';
  }

  function setCookie(name, value, days) {
    var expires = '';
    if (days) {
      var date = new Date();
      date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
      expires = '; expires=' + date.toUTCString();
    }
    document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
  }

  function escapeHTML(str) {
    if (typeof str !== 'string') {
      return '';
    }
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function refreshPublicNonce() {
    if (!restBase) {
      return Promise.resolve(false);
    }

    return fetch(restBase + '/public-nonce?ts=' + Date.now(), {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store'
    }).then(function (res) {
      return res.text().then(function (raw) {
        var json = {};
        try {
          json = raw ? JSON.parse(raw) : {};
        } catch (e) {
          json = {};
        }

        if (!res.ok || !json || !json.nonce) {
          return false;
        }

        config.nonce = json.nonce;
        return true;
      });
    }).catch(function () {
      return false;
    });
  }

  function api(path, method, payload, retriedForNonce) {
    if (!restBase) {
      return Promise.reject(new Error('REST base URL is not configured.'));
    }

    var opts = {
      method: method || 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-ATS-Chat-Nonce': config.nonce
      },
      credentials: 'same-origin'
    };

    if (payload) {
      opts.body = JSON.stringify(payload);
    }

    return fetch(restBase + path, opts).then(function (res) {
      return res.text().then(function (raw) {
        var json = {};
        try {
          json = raw ? JSON.parse(raw) : {};
        } catch (e) {
          json = {};
        }

        if (!res.ok) {
          var code = (json && json.code) ? String(json.code) : '';
          var msg = (json && json.message) ? json.message : 'Request failed';
          var isNonceError = code === 'ats_chat_bad_nonce' || msg.toLowerCase().indexOf('invalid chat nonce') !== -1;

          if (!retriedForNonce && isNonceError) {
            return refreshPublicNonce().then(function (refreshed) {
              if (refreshed) {
                return api(path, method, payload, true);
              }
              throw new Error(msg);
            });
          }

          throw new Error(msg + ' (HTTP ' + res.status + ')');
        }
        return json || {};
      });
    });
  }

  function normalizeRestBase(base) {
    if (!base || typeof base !== 'string') {
      return '';
    }

    try {
      var parsed = new URL(base, window.location.origin);
      // Force same-origin to avoid staging proxy mixed-content/cross-origin issues.
      parsed.protocol = window.location.protocol;
      parsed.host = window.location.host;
      return parsed.toString().replace(/\/+$/, '');
    } catch (e) {
      return base.replace(/\/+$/, '');
    }
  }

  function renderTextMessage(message) {
    var item = document.createElement('div');
    item.className = 'ats-chat-message ats-chat-message-' + message.sender_type;

    var bubble = document.createElement('div');
    bubble.className = 'ats-chat-bubble';
    bubble.textContent = message.content_text || '';

    var meta = document.createElement('div');
    meta.className = 'ats-chat-meta';
    meta.textContent = message.sender_type === 'visitor' ? 'You' : (message.sender_type === 'ai' ? 'AI Assistant' : 'Support');

    item.appendChild(bubble);
    item.appendChild(meta);
    return item;
  }

  function renderProductCard(message) {
    var content = message.content || {};
    var item = document.createElement('div');
    item.className = 'ats-chat-message ats-chat-message-agent';

    var bubble = document.createElement('div');
    bubble.className = 'ats-chat-bubble ats-chat-product-card';

    if (content.image) {
      var img = document.createElement('img');
      img.src = content.image;
      img.alt = content.title || 'Product';
      bubble.appendChild(img);
    }

    var title = document.createElement('strong');
    title.textContent = content.title || 'Product';
    bubble.appendChild(title);

    if (content.price) {
      var price = document.createElement('div');
      price.className = 'ats-chat-product-price';
      price.textContent = content.price;
      bubble.appendChild(price);
    }

    if (content.excerpt) {
      var excerpt = document.createElement('p');
      excerpt.textContent = content.excerpt;
      bubble.appendChild(excerpt);
    }

    if (content.url) {
      var link = document.createElement('a');
      link.href = content.url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.textContent = 'View product';
      bubble.appendChild(link);
    }

    item.appendChild(bubble);
    return item;
  }

  function appendMessage(message) {
    if (!message || !message.message_id) {
      return;
    }

    if (els.messages.querySelector('[data-message-id="' + message.message_id + '"]')) {
      return;
    }

    var node = message.message_type === 'product_card' ? renderProductCard(message) : renderTextMessage(message);
    node.setAttribute('data-message-id', message.message_id);
    els.messages.appendChild(node);
    els.messages.scrollTop = els.messages.scrollHeight;

    if (message.ts && message.ts > state.lastMessageTs) {
      state.lastMessageTs = message.ts;
    }
  }

  function setTyping(typing) {
    if (typing && typing.is_typing) {
      var text = config.strings.typingAgent || 'Agent is typing…';
      if (typing.preview) {
        text += ' ' + typing.preview;
      }
      els.typing.textContent = text;
      els.typing.style.display = 'block';
      return;
    }

    els.typing.textContent = '';
    els.typing.style.display = 'none';
  }

  function setOfflineMode(show) {
    if (show) {
      els.composer.style.display = 'none';
      els.offline.style.display = 'block';
      return;
    }

    els.composer.style.display = 'grid';
    els.offline.style.display = 'none';
  }

  function showSystemNotice(text) {
    var notice = {
      message_id: 'system-' + Date.now(),
      sender_type: 'system',
      message_type: 'text',
      content_text: text || 'Connection issue. Please try again.',
      ts: 0
    };
    appendMessage(notice);
  }

  function showUpdateRequired(message) {
    if (!els.updateRequired) {
      return;
    }

    els.updateRequired.textContent = message || '';
    els.updateRequired.style.display = 'block';
  }

  function hideUpdateRequired() {
    if (!els.updateRequired) {
      return;
    }

    els.updateRequired.textContent = '';
    els.updateRequired.style.display = 'none';
  }

  function forceReloadForBuild(buildVersion) {
    if (!buildVersion || state.updateReloadScheduled) {
      return;
    }

    state.updateReloadScheduled = true;
    setTimeout(function () {
      var nextUrl = window.location.href;

      try {
        var parsed = new URL(window.location.href);
        parsed.searchParams.set('ats_chat_build', buildVersion);
        parsed.searchParams.set('ats_chat_r', String(Date.now()));
        nextUrl = parsed.toString();
      } catch (e) {
        nextUrl = window.location.href + (window.location.href.indexOf('?') === -1 ? '?' : '&') +
          'ats_chat_build=' + encodeURIComponent(buildVersion) + '&ats_chat_r=' + Date.now();
      }

      window.location.replace(nextUrl);
    }, 1200);
  }

  function checkBuildVersion(serverVersion) {
    var serverBuild = (serverVersion || '').trim();
    var clientBuild = (config.pluginVersion || '').trim();
    var sessionKey = 'ats_chat_widget_forced_build';

    if (!serverBuild || !clientBuild) {
      return false;
    }

    if (serverBuild === clientBuild) {
      if (getSession(sessionKey) === serverBuild) {
        removeSession(sessionKey);
      }
      hideUpdateRequired();
      state.updateReloadScheduled = false;
      return false;
    }

    if (getSession(sessionKey) === serverBuild) {
      showUpdateRequired((config.strings.updateRequiredManual || 'Update required. Clear cache and refresh.') +
        ' (server: ' + serverBuild + ', loaded: ' + clientBuild + ')');
      return false;
    }

    showUpdateRequired((config.strings.updateRequired || 'Update required. New chat build detected. Refreshing…') +
      ' (server: ' + serverBuild + ', loaded: ' + clientBuild + ')');
    setSession(sessionKey, serverBuild);
    forceReloadForBuild(serverBuild);
    return false;
  }

  function sendPresence() {
    return api('/presence', 'POST', {
      nonce: config.nonce,
      visitor_id: state.visitorId,
      current_url: window.location.href,
      current_title: document.title,
      referrer: document.referrer || '',
      user_agent: navigator.userAgent,
      name: getStorage('ats_chat_name') || config.visitorName || '',
      email: getStorage('ats_chat_email') || config.visitorEmail || ''
    })
      .then(function (res) {
        if (checkBuildVersion(res.plugin_version || '')) {
          return;
        }
        state.presenceFailures = 0;
        if (res.visitor_id) {
          state.visitorId = res.visitor_id;
          setStorage('ats_chat_visitor_id', res.visitor_id);
          setCookie('ats_chat_visitor_id', res.visitor_id, 30);
        }
        if (res.conversation_id) {
          state.conversationId = res.conversation_id;
          setStorage('ats_chat_conversation_id', res.conversation_id);
        }

        state.agentsOnline = !!res.agents_online;
        state.aiMode = res.ai_mode || 'off';

        setOfflineMode(!!res.show_offline_lead_form);

        if (res.cookie_notice_enabled && res.cookie_notice_text && !getStorage('ats_chat_cookie_notice_dismissed')) {
          els.cookieNotice.textContent = res.cookie_notice_text;
          els.cookieNotice.style.display = 'block';
          els.cookieNotice.onclick = function () {
            setStorage('ats_chat_cookie_notice_dismissed', '1');
            els.cookieNotice.style.display = 'none';
          };
        }
      })
      .catch(function (err) {
        // Keep the widget usable even if presence fails temporarily.
        console.error('[ATS Chat] presence error:', err.message);
        state.presenceFailures += 1;
        if (state.presenceFailures >= 2 && (Date.now() - state.lastPresenceErrorAt) > 30000) {
          state.lastPresenceErrorAt = Date.now();
          showSystemNotice('Chat is having trouble connecting. Please refresh this page.');
        }
      });
  }

  function pollMessages(initialLoad) {
    if (!state.conversationId) {
      return;
    }

    var since = initialLoad ? 0 : Math.max(0, (state.lastMessageTs || 0) - 1);
    var query = '/messages?conversation_id=' + encodeURIComponent(state.conversationId) +
      '&visitor_id=' + encodeURIComponent(state.visitorId) +
      '&nonce=' + encodeURIComponent(config.nonce) +
      '&since=' + encodeURIComponent(since);

    api(query, 'GET')
      .then(function (res) {
        if (checkBuildVersion(res.plugin_version || '')) {
          return;
        }
        var messages = Array.isArray(res.messages) ? res.messages : [];
        if (initialLoad && messages.length) {
          els.messages.innerHTML = '';
          state.lastMessageTs = 0;
        }

        messages.forEach(function (message) {
          appendMessage(message);
        });

        setTyping(res.typing || null);
      })
      .catch(function (err) {
        console.error('[ATS Chat] messages error:', err.message);
      });
  }

  function sendMessage() {
    var text = (els.input.value || '').trim();
    if (!text) {
      return;
    }

    els.input.value = '';

    api('/message', 'POST', {
      nonce: config.nonce,
      visitor_id: state.visitorId,
      conversation_id: state.conversationId,
      message: text,
      current_url: window.location.href,
      current_title: document.title,
      referrer: document.referrer || '',
      user_agent: navigator.userAgent
    })
      .then(function (res) {
        if (checkBuildVersion(res.plugin_version || '')) {
          return;
        }
        if (res.message) {
          appendMessage(res.message);
        }
        if (res.ai_message) {
          appendMessage(res.ai_message);
        }
      })
      .catch(function (err) {
        console.error('[ATS Chat] send message error:', err.message);
        showSystemNotice('Message could not be sent right now. Please try again.');
      });
  }

  function sendTyping() {
    if (!state.conversationId) {
      return;
    }

    var preview = (els.input.value || '').trim();
    if (!preview) {
      return;
    }

    api('/typing', 'POST', {
      nonce: config.nonce,
      conversation_id: state.conversationId,
      visitor_id: state.visitorId,
      preview: preview
    }).catch(function () {
      // Silent typing failure.
    });
  }

  function submitLead() {
    var name = (els.leadName.value || '').trim();
    var email = (els.leadEmail.value || '').trim();
    var message = (els.leadMessage.value || '').trim();

    if (!name || !email || !message) {
      alert('Name, email, and message are required.');
      return;
    }

    api('/lead', 'POST', {
      nonce: config.nonce,
      visitor_id: state.visitorId,
      name: name,
      email: email,
      message: message,
      current_url: window.location.href,
      current_title: document.title
    })
      .then(function (res) {
        if (checkBuildVersion((res && res.plugin_version) || '')) {
          return;
        }
        setStorage('ats_chat_name', name);
        setStorage('ats_chat_email', email);
        els.leadMessage.value = '';
        alert(config.strings.thanks || 'Thanks! We received your message.');
      })
      .catch(function (err) {
        alert(err.message || 'Could not submit lead right now.');
      });
  }

  function openPanel() {
    state.panelOpen = true;
    els.panel.style.display = 'flex';
    els.toggle.setAttribute('aria-expanded', 'true');

    sendPresence().then(function () {
      pollMessages(true);
    });

    if (state.pollTimer) {
      clearInterval(state.pollTimer);
    }
    state.pollTimer = setInterval(function () {
      pollMessages(false);
    }, config.pollMs || 2000);

    if (state.presenceTimer) {
      clearInterval(state.presenceTimer);
    }
    state.presenceTimer = setInterval(function () {
      sendPresence();
    }, config.presenceMs || 10000);
  }

  function closePanel() {
    state.panelOpen = false;
    els.panel.style.display = 'none';
    els.toggle.setAttribute('aria-expanded', 'false');

    if (state.pollTimer) {
      clearInterval(state.pollTimer);
      state.pollTimer = null;
    }

    if (state.presenceTimer) {
      clearInterval(state.presenceTimer);
      state.presenceTimer = null;
    }

    if (state.typingTimer) {
      clearTimeout(state.typingTimer);
      state.typingTimer = null;
    }
  }

  function bindEvents() {
    els.toggle.addEventListener('click', function () {
      if (state.panelOpen) {
        closePanel();
      } else {
        openPanel();
      }
    });

    els.close.addEventListener('click', function () {
      closePanel();
    });

    els.send.addEventListener('click', sendMessage);
    els.input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
      }
    });

    els.input.addEventListener('input', function () {
      if (state.typingCooldownTimer) {
        clearTimeout(state.typingCooldownTimer);
      }
      state.typingCooldownTimer = setTimeout(function () {
        sendTyping();
      }, 450);
    });

    els.leadSend.addEventListener('click', submitLead);
  }

  function initState() {
    state.visitorId = getStorage('ats_chat_visitor_id') || readCookie('ats_chat_visitor_id') || uuidV4();
    state.conversationId = getStorage('ats_chat_conversation_id') || '';

    setStorage('ats_chat_visitor_id', state.visitorId);
    setCookie('ats_chat_visitor_id', state.visitorId, 30);
  }

  function initElements() {
    els.toggle = $('ats-chat-toggle');
    els.panel = $('ats-chat-panel');
    els.close = $('ats-chat-close');
    els.messages = $('ats-chat-messages');
    els.typing = $('ats-chat-typing');
    els.input = $('ats-chat-input');
    els.send = $('ats-chat-send');
    els.offline = $('ats-chat-offline');
    els.composer = $('ats-chat-composer');
    els.leadName = $('ats-chat-lead-name');
    els.leadEmail = $('ats-chat-lead-email');
    els.leadMessage = $('ats-chat-lead-message');
    els.leadSend = $('ats-chat-lead-send');
    els.cookieNotice = $('ats-chat-cookie-notice');
    els.updateRequired = $('ats-chat-widget-update-required');
  }

  function init() {
    initElements();
    if (!els.toggle || !els.panel) {
      return;
    }
    initState();
    bindEvents();
    sendPresence();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
