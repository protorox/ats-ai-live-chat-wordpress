(function () {
  'use strict';

  if (typeof window.ATSLiveChatAdmin === 'undefined') {
    return;
  }

  var config = window.ATSLiveChatAdmin;
  var restBase = normalizeRestBase(config.restBase || '');

  var state = {
    visitors: [],
    selectedVisitorId: '',
    selectedConversationId: '',
    selectedVisitorEmail: '',
    lastVisitorsSince: 0,
    lastMessageTs: 0,
    visitorsPollCount: 0,
    visitorsTimer: null,
    messagesTimer: null,
    typingTimer: null,
    productSearchTimer: null,
    draft: '',
    updateReloadScheduled: false
  };

  var els = {};

  function $(id) {
    return document.getElementById(id);
  }

  function api(path, method, payload) {
    if (!restBase) {
      return Promise.reject(new Error('REST base URL is not configured.'));
    }

    var opts = {
      method: method || 'GET',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce
      }
    };

    if (payload) {
      opts.body = JSON.stringify(payload);
    }

    return fetch(restBase + path, opts).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok) {
          var msg = json && json.message ? json.message : 'Request failed';
          throw new Error(msg);
        }
        return json;
      });
    });
  }

  function normalizeRestBase(base) {
    if (!base || typeof base !== 'string') {
      return '';
    }

    try {
      var parsed = new URL(base, window.location.origin);
      parsed.protocol = window.location.protocol;
      parsed.host = window.location.host;
      return parsed.toString().replace(/\/+$/, '');
    } catch (e) {
      return base.replace(/\/+$/, '');
    }
  }

  function setTopError(message) {
    if (!message) {
      return;
    }

    if (!els.errorBar) {
      els.errorBar = document.createElement('div');
      els.errorBar.className = 'notice notice-error ats-chat-admin-error';
      els.errorBar.style.margin = '10px 0';
      var wrap = document.querySelector('.ats-chat-admin-wrap');
      if (wrap) {
        wrap.insertBefore(els.errorBar, wrap.firstChild.nextSibling);
      }
    }

    els.errorBar.textContent = 'ATS Live Chat error: ' + message;
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
    var sessionKey = 'ats_chat_admin_forced_build';

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
      return true;
    }

    showUpdateRequired((config.strings.updateRequired || 'Update required. New build detected. Refreshing…') +
      ' (server: ' + serverBuild + ', loaded: ' + clientBuild + ')');
    setSession(sessionKey, serverBuild);
    forceReloadForBuild(serverBuild);
    return true;
  }

  function formatAgo(seconds) {
    if (seconds < 1) {
      return 'just now';
    }
    if (seconds < 60) {
      return seconds + 's ago';
    }
    var mins = Math.floor(seconds / 60);
    if (mins < 60) {
      return mins + 'm ago';
    }
    var hours = Math.floor(mins / 60);
    return hours + 'h ago';
  }

  function renderVisitors() {
    els.visitors.innerHTML = '';

    if (!state.visitors.length) {
      var empty = document.createElement('div');
      empty.className = 'ats-chat-empty';
      empty.textContent = config.strings.noVisitors || 'No visitors';
      els.visitors.appendChild(empty);
      return;
    }

    state.visitors.forEach(function (visitor) {
      var row = document.createElement('button');
      row.type = 'button';
      row.className = 'ats-chat-visitor-row';
      if (visitor.visitor_id === state.selectedVisitorId) {
        row.className += ' is-active';
      }

      var top = document.createElement('div');
      top.className = 'ats-chat-row-top';

      var name = document.createElement('strong');
      name.textContent = visitor.name || 'Anonymous';
      top.appendChild(name);

      var ago = document.createElement('span');
      ago.className = 'ats-chat-time';
      ago.textContent = formatAgo(visitor.last_seen_ago || 0);
      top.appendChild(ago);

      var page = document.createElement('a');
      page.href = visitor.current_url || '#';
      page.target = '_blank';
      page.rel = 'noopener noreferrer';
      page.className = 'ats-chat-row-page';
      page.textContent = visitor.current_title || visitor.current_url || '(no page)';

      var details = document.createElement('div');
      details.className = 'ats-chat-row-details';
      details.textContent = visitor.device && visitor.device.label ? visitor.device.label : '';

      row.appendChild(top);
      row.appendChild(page);
      row.appendChild(details);

      if (visitor.referrer) {
        var ref = document.createElement('div');
        ref.className = 'ats-chat-row-ref';
        ref.textContent = 'Referrer: ' + visitor.referrer;
        row.appendChild(ref);
      }

      row.addEventListener('click', function () {
        state.selectedVisitorId = visitor.visitor_id;
        state.selectedConversationId = visitor.conversation_id;
        state.selectedVisitorEmail = visitor.email || '';
        state.lastMessageTs = 0;
        renderVisitors();
        loadConversation();
      });

      els.visitors.appendChild(row);
    });
  }

  function renderHeader(visitor) {
    if (!visitor) {
      els.header.textContent = config.strings.selectVisitor || 'Select a visitor';
      return;
    }

    var parts = [];
    parts.push((visitor.name || 'Anonymous') + ' (' + (visitor.visitor_id || '') + ')');
    if (visitor.email) {
      parts.push(visitor.email);
    }
    if (visitor.current_title) {
      parts.push('On: ' + visitor.current_title);
    }

    els.header.textContent = parts.join(' • ');
  }

  function renderPageHistory(history) {
    els.pageHistory.innerHTML = '';
    if (!Array.isArray(history) || !history.length) {
      els.pageHistory.textContent = 'No recent page views.';
      return;
    }

    history.slice().reverse().forEach(function (item) {
      var row = document.createElement('div');
      row.className = 'ats-chat-mini-row';

      var link = document.createElement('a');
      link.href = item.url || '#';
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.textContent = item.title || item.url || '(unknown page)';

      var meta = document.createElement('span');
      meta.textContent = item.seen_at || '';

      row.appendChild(link);
      row.appendChild(meta);
      els.pageHistory.appendChild(row);
    });
  }

  function renderCart(cart) {
    els.cart.innerHTML = '';
    if (!Array.isArray(cart) || !cart.length) {
      els.cart.textContent = config.wooEnabled ? 'No cart activity yet.' : 'WooCommerce is not active.';
      return;
    }

    cart.forEach(function (item) {
      var row = document.createElement('div');
      row.className = 'ats-chat-mini-row';

      var label = document.createElement('span');
      label.textContent = (item.title || 'Product') + ' × ' + (item.qty || 1);

      var value = document.createElement('span');
      value.textContent = item.price || '';

      row.appendChild(label);
      row.appendChild(value);
      els.cart.appendChild(row);
    });
  }

  function renderMessage(message) {
    var wrap = document.createElement('div');
    wrap.className = 'ats-chat-thread-row ats-chat-thread-' + (message.sender_type || 'system');
    wrap.setAttribute('data-message-id', message.message_id || '');

    var bubble = document.createElement('div');
    bubble.className = 'ats-chat-thread-bubble';

    if (message.message_type === 'product_card') {
      var card = message.content || {};

      if (card.image) {
        var img = document.createElement('img');
        img.src = card.image;
        img.alt = card.title || 'Product';
        bubble.appendChild(img);
      }

      var title = document.createElement('strong');
      title.textContent = card.title || 'Product';
      bubble.appendChild(title);

      if (card.price) {
        var price = document.createElement('div');
        price.className = 'ats-chat-product-price';
        price.textContent = card.price;
        bubble.appendChild(price);
      }

      if (card.excerpt) {
        var excerpt = document.createElement('div');
        excerpt.textContent = card.excerpt;
        bubble.appendChild(excerpt);
      }

      if (card.url) {
        var link = document.createElement('a');
        link.href = card.url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = 'View product';
        bubble.appendChild(link);
      }
    } else {
      bubble.textContent = message.content_text || '';
    }

    var meta = document.createElement('div');
    meta.className = 'ats-chat-thread-meta';

    var sender = message.sender_type === 'agent' ? 'You' : (message.sender_type === 'ai' ? 'AI' : 'Visitor');
    var time = message.created_at || '';
    meta.textContent = sender + (time ? ' • ' + time : '');

    wrap.appendChild(bubble);
    wrap.appendChild(meta);
    return wrap;
  }

  function appendMessages(messages, replace) {
    if (replace) {
      els.thread.innerHTML = '';
      state.lastMessageTs = 0;
    }

    (messages || []).forEach(function (message) {
      if (!message || !message.message_id) {
        return;
      }
      if (els.thread.querySelector('[data-message-id="' + message.message_id + '"]')) {
        return;
      }
      els.thread.appendChild(renderMessage(message));
      if (message.ts && message.ts > state.lastMessageTs) {
        state.lastMessageTs = message.ts;
      }
    });

    els.thread.scrollTop = els.thread.scrollHeight;
  }

  function updateTyping(typing) {
    if (typing && typing.is_typing) {
      var text = config.strings.typingVisitor || 'Visitor is typing...';
      if (typing.preview) {
        text += ' ' + typing.preview;
      }
      els.typing.textContent = text;
      return;
    }
    els.typing.textContent = '';
  }

  function loadMessages(initial) {
    if (!state.selectedConversationId) {
      return;
    }

    var since = initial ? 0 : Math.max(0, (state.lastMessageTs || 0) - 1);

    api('/messages?conversation_id=' + encodeURIComponent(state.selectedConversationId) + '&since=' + encodeURIComponent(since), 'GET')
      .then(function (res) {
        if (checkBuildVersion(res.plugin_version || '')) {
          return;
        }
        appendMessages(res.messages || [], !!initial);
        updateTyping(res.typing || null);
      })
      .catch(function (err) {
        console.error('[ATS Chat admin] messages error:', err.message);
        setTopError(err.message);
      });
  }

  function loadConversation() {
    if (!state.selectedVisitorId) {
      renderHeader(null);
      return;
    }

    api('/conversation?visitor_id=' + encodeURIComponent(state.selectedVisitorId) + '&conversation_id=' + encodeURIComponent(state.selectedConversationId), 'GET')
      .then(function (res) {
        if (checkBuildVersion(res.plugin_version || '')) {
          return;
        }
        if (!res || !res.conversation || !res.visitor) {
          return;
        }

        state.selectedConversationId = res.conversation.conversation_id;
        renderHeader(res.visitor);
        renderPageHistory(res.visitor.page_history || []);
        renderCart(res.visitor.cart || []);
        loadMessages(true);
      })
      .catch(function (err) {
        console.error('[ATS Chat admin] conversation error:', err.message);
        setTopError(err.message);
      });
  }

  function syncVisitors() {
    state.visitorsPollCount += 1;

    var useFull = state.visitorsPollCount % 5 === 0;
    var since = useFull ? 0 : state.lastVisitorsSince;

    api('/visitors?since=' + encodeURIComponent(since), 'GET')
      .then(function (res) {
        if (checkBuildVersion(res.plugin_version || '')) {
          return;
        }
        var incoming = Array.isArray(res.visitors) ? res.visitors : [];
        var now = Math.floor(Date.now() / 1000);

        if (useFull || since === 0) {
          state.visitors = incoming;
        } else {
          var map = {};
          state.visitors.forEach(function (row) {
            map[row.visitor_id] = row;
          });
          incoming.forEach(function (row) {
            map[row.visitor_id] = row;
          });

          state.visitors = Object.keys(map).map(function (id) {
            return map[id];
          }).filter(function (row) {
            return (row.last_seen_ts || 0) >= (now - 130);
          }).sort(function (a, b) {
            return (b.last_seen_ts || 0) - (a.last_seen_ts || 0);
          });
        }

        state.lastVisitorsSince = res.server_ts || now;

        if (els.agentStatus) {
          els.agentStatus.textContent = 'Agents online: ' + (res.online_agents || 0);
        }

        if (state.selectedVisitorId) {
          var stillExists = state.visitors.some(function (v) {
            return v.visitor_id === state.selectedVisitorId;
          });
          if (!stillExists) {
            state.selectedVisitorId = '';
            state.selectedConversationId = '';
            state.lastMessageTs = 0;
            els.thread.innerHTML = '';
            renderHeader(null);
            renderPageHistory([]);
            renderCart([]);
          }
        }

        if (!state.selectedVisitorId && state.visitors.length) {
          state.selectedVisitorId = state.visitors[0].visitor_id;
          state.selectedConversationId = state.visitors[0].conversation_id;
          loadConversation();
        }

        renderVisitors();
      })
      .catch(function (err) {
        console.error('[ATS Chat admin] visitors error:', err.message);
        setTopError(err.message);
      });
  }

  function sendMessage() {
    var text = (els.reply.value || '').trim();
    if (!text || !state.selectedConversationId) {
      return;
    }

    els.reply.value = '';

    api('/agent/message', 'POST', {
      conversation_id: state.selectedConversationId,
      message_type: 'text',
      message: text
    })
      .then(function (res) {
        if (checkBuildVersion(res.plugin_version || '')) {
          return;
        }
        if (res.message) {
          appendMessages([res.message], false);
        }
      })
      .catch(function (err) {
        alert(err.message || 'Could not send message.');
      });
  }

  function sendTyping() {
    var preview = (els.reply.value || '').trim();
    if (!preview || !state.selectedConversationId || !state.selectedVisitorId) {
      return;
    }

    api('/typing', 'POST', {
      conversation_id: state.selectedConversationId,
      visitor_id: state.selectedVisitorId,
      preview: preview
    }).catch(function () {
      // Ignore typing errors.
    });
  }

  function openProductModal() {
    if (!config.wooEnabled) {
      alert('WooCommerce is not active. Product cards are unavailable.');
      return;
    }
    els.modal.style.display = 'flex';
    els.productSearch.focus();
    els.productResults.innerHTML = '';
  }

  function closeProductModal() {
    els.modal.style.display = 'none';
  }

  function renderProductResults(results) {
    els.productResults.innerHTML = '';

    if (!results.length) {
      var empty = document.createElement('div');
      empty.className = 'ats-chat-empty';
      empty.textContent = 'No products found.';
      els.productResults.appendChild(empty);
      return;
    }

    results.forEach(function (product) {
      var row = document.createElement('div');
      row.className = 'ats-chat-product-row';

      if (product.image) {
        var img = document.createElement('img');
        img.src = product.image;
        img.alt = product.title || 'Product';
        row.appendChild(img);
      }

      var body = document.createElement('div');
      body.className = 'ats-chat-product-body';

      var title = document.createElement('strong');
      title.textContent = product.title || 'Product';
      body.appendChild(title);

      var meta = document.createElement('div');
      meta.textContent = (product.price || '') + (product.sku ? ' • SKU: ' + product.sku : '');
      body.appendChild(meta);

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'button button-small';
      btn.textContent = 'Send';
      btn.addEventListener('click', function () {
        if (!state.selectedConversationId) {
          alert('Select a conversation first.');
          return;
        }

        api('/agent/message', 'POST', {
          conversation_id: state.selectedConversationId,
          message_type: 'product_card',
          product_id: product.product_id || product.id
        }).then(function (res) {
          if (checkBuildVersion(res.plugin_version || '')) {
            return;
          }
          if (res.message) {
            appendMessages([res.message], false);
          }
          closeProductModal();
        }).catch(function (err) {
          alert(err.message || 'Could not send product card.');
        });
      });

      row.appendChild(body);
      row.appendChild(btn);
      els.productResults.appendChild(row);
    });
  }

  function searchProducts(query) {
    query = (query || '').trim();
    if (!query) {
      els.productResults.innerHTML = '';
      return;
    }

    api('/products/search?q=' + encodeURIComponent(query), 'GET')
      .then(function (res) {
        if (checkBuildVersion(res.plugin_version || '')) {
          return;
        }
        renderProductResults(Array.isArray(res.results) ? res.results : []);
      })
      .catch(function (err) {
        console.error('[ATS Chat admin] product search error:', err.message);
      });
  }

  function requestAIDraft() {
    if (!state.selectedConversationId) {
      alert('Select a conversation first.');
      return;
    }

    api('/ai/reply', 'POST', {
      conversation_id: state.selectedConversationId,
      send: false
    })
      .then(function (res) {
        if (checkBuildVersion(res.plugin_version || '')) {
          return;
        }
        state.draft = (res.draft || '').trim();
        if (!state.draft) {
          alert('AI did not return a draft.');
          return;
        }

        els.draft.style.display = 'block';
        els.useDraft.style.display = 'inline-block';
        els.draft.textContent = state.draft;
      })
      .catch(function (err) {
        alert(err.message || 'Could not generate AI draft.');
      });
  }

  function bindEvents() {
    els.send.addEventListener('click', sendMessage);

    els.reply.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
      }
    });

    els.reply.addEventListener('input', function () {
      if (state.typingTimer) {
        clearTimeout(state.typingTimer);
      }
      state.typingTimer = setTimeout(sendTyping, 350);
    });

    els.sendProduct.addEventListener('click', openProductModal);
    els.modalClose.addEventListener('click', closeProductModal);

    els.aiDraft.addEventListener('click', requestAIDraft);
    els.useDraft.addEventListener('click', function () {
      if (!state.draft) {
        return;
      }
      els.reply.value = state.draft;
      els.reply.focus();
    });

    els.productSearch.addEventListener('input', function () {
      var term = els.productSearch.value;
      if (state.productSearchTimer) {
        clearTimeout(state.productSearchTimer);
      }
      state.productSearchTimer = setTimeout(function () {
        searchProducts(term);
      }, 250);
    });

    window.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeProductModal();
      }
    });
  }

  function initElements() {
    els.visitors = $('ats-chat-visitors');
    els.header = $('ats-chat-conversation-header');
    els.pageHistory = $('ats-chat-page-history');
    els.cart = $('ats-chat-cart-context');
    els.thread = $('ats-chat-thread');
    els.typing = $('ats-chat-typing');
    els.reply = $('ats-chat-reply');
    els.send = $('ats-chat-send');
    els.sendProduct = $('ats-chat-send-product');
    els.aiDraft = $('ats-chat-ai-draft');
    els.useDraft = $('ats-chat-use-draft');
    els.draft = $('ats-chat-draft');
    els.agentStatus = $('ats-chat-agent-status');

    els.modal = $('ats-chat-product-modal');
    els.modalClose = $('ats-chat-modal-close');
    els.productSearch = $('ats-chat-product-search');
    els.productResults = $('ats-chat-product-results');
    els.updateRequired = $('ats-chat-admin-update-required');
  }

  function startPolling() {
    syncVisitors();

    state.visitorsTimer = setInterval(function () {
      syncVisitors();
    }, 2000);

    state.messagesTimer = setInterval(function () {
      if (state.selectedConversationId) {
        loadMessages(false);
      }
    }, 2000);
  }

  function init() {
    initElements();

    if (!els.visitors || !els.thread) {
      return;
    }

    bindEvents();
    startPolling();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
