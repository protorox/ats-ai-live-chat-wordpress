(function () {
	'use strict';

	if (typeof window.atslcConfig === 'undefined') {
		return;
	}

	var config = window.atslcConfig;
	var root = document.getElementById('atslc-widget');

	if (!root) {
		return;
	}

	var launcher = root.querySelector('.atslc-launcher');
	var panel = root.querySelector('.atslc-panel');
	var transcript = root.querySelector('.atslc-transcript');
	var feedback = root.querySelector('.atslc-feedback');
	var onlineOnly = root.querySelectorAll('.atslc-online-only');
	var offlineOnly = root.querySelectorAll('.atslc-offline-only');
	var profileForm = root.querySelector('.atslc-profile-form');
	var composerForm = root.querySelector('.atslc-composer');
	var offlineForm = root.querySelector('.atslc-offline-form');
	var sessionStorageKey = 'atslc-chat-state';
	var state = loadState();

	function getDefaultState() {
		return {
			isOpen: false,
			closedByUser: false,
			profile: {
				name: config.profile && config.profile.name ? config.profile.name : '',
				email: config.profile && config.profile.email ? config.profile.email : ''
			},
			sessionId: generateSessionId(),
			messages: Array.isArray(config.initialMessages) ? config.initialMessages.slice() : []
		};
	}

	function loadState() {
		try {
			var saved = window.localStorage.getItem(sessionStorageKey);

			if (!saved) {
				return getDefaultState();
			}

			var parsed = JSON.parse(saved);

			return Object.assign(getDefaultState(), parsed, {
				profile: Object.assign(getDefaultState().profile, parsed.profile || {}),
				messages: Array.isArray(parsed.messages) && parsed.messages.length ? parsed.messages : getDefaultState().messages
			});
		} catch (error) {
			return getDefaultState();
		}
	}

	function saveState() {
		try {
			window.localStorage.setItem(sessionStorageKey, JSON.stringify(state));
		} catch (error) {
			// Continue without persistence if browser storage is unavailable.
		}
	}

	function generateSessionId() {
		return 'atslc_' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36);
	}

	function setOpen(isOpen, closedByUser) {
		state.isOpen = isOpen;
		state.closedByUser = !!closedByUser;
		saveState();
		root.classList.toggle('is-open', isOpen);
		launcher.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
	}

	function setFeedback(message, isError) {
		if (!message) {
			feedback.hidden = true;
			feedback.textContent = '';
			feedback.classList.remove('is-error');
			return;
		}

		feedback.hidden = false;
		feedback.textContent = message;
		feedback.classList.toggle('is-error', !!isError);
	}

	function renderTranscript() {
		transcript.innerHTML = '';

		state.messages.forEach(function (entry) {
			var bubble = document.createElement('div');
			bubble.className = 'atslc-message ' + (entry.role === 'user' ? 'is-user' : 'is-agent');
			bubble.textContent = entry.message;
			transcript.appendChild(bubble);
		});

		transcript.scrollTop = transcript.scrollHeight;
	}

	function renderMode() {
		var hasProfile = !!state.profile.name;

		onlineOnly.forEach(function (node) {
			node.classList.toggle('atslc-hidden', !config.isOnline);
		});

		offlineOnly.forEach(function (node) {
			node.classList.toggle('atslc-hidden', config.isOnline);
		});

		if (config.isOnline && profileForm && composerForm) {
			profileForm.parentElement.classList.toggle('atslc-hidden', hasProfile);
			composerForm.querySelector('textarea').disabled = !hasProfile;
			if (hasProfile) {
				setFeedback(config.strings.chatReady, false);
			}
		}
	}

	function appendMessage(role, message) {
		state.messages.push({ role: role, message: message });
		saveState();
		renderTranscript();
	}

	function playTone() {
		if (!config.soundEnabled || typeof window.AudioContext === 'undefined' && typeof window.webkitAudioContext === 'undefined') {
			return;
		}

		var AudioContext = window.AudioContext || window.webkitAudioContext;
		var context = new AudioContext();
		var oscillator = context.createOscillator();
		var gain = context.createGain();

		oscillator.type = 'sine';
		oscillator.frequency.setValueAtTime(920, context.currentTime);
		gain.gain.setValueAtTime(0.0001, context.currentTime);
		gain.gain.exponentialRampToValueAtTime(0.05, context.currentTime + 0.01);
		gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.18);
		oscillator.connect(gain);
		gain.connect(context.destination);
		oscillator.start();
		oscillator.stop(context.currentTime + 0.18);
	}

	function sendPayload(payload) {
		var body = new URLSearchParams();
		body.append('action', 'atslc_send_message');
		body.append('nonce', config.nonce);
		body.append('session_id', state.sessionId);
		body.append('page_url', config.pageUrl);

		Object.keys(payload).forEach(function (key) {
			body.append(key, payload[key] || '');
		});

		return window.fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		}).then(function (response) {
			return response.json().then(function (json) {
				if (!response.ok || !json.success) {
					throw new Error(json && json.data && json.data.message ? json.data.message : config.strings.submitError);
				}

				return json.data;
			});
		});
	}

	launcher.addEventListener('click', function () {
		setOpen(!state.isOpen, false);
	});

	root.querySelectorAll('.atslc-icon-button').forEach(function (button) {
		button.addEventListener('click', function () {
			var action = button.getAttribute('data-action');

			if (action === 'minimize') {
				setOpen(false, false);
			}

			if (action === 'close') {
				setOpen(false, true);
			}
		});
	});

	if (profileForm) {
		profileForm.addEventListener('submit', function (event) {
			event.preventDefault();
			var formData = new FormData(profileForm);
			var name = (formData.get('visitor_name') || '').toString().trim();
			var email = (formData.get('visitor_email') || '').toString().trim();

			if (!name) {
				setFeedback(config.strings.startChatError, true);
				return;
			}

			state.profile = { name: name, email: email };
			saveState();
			renderMode();
		});
	}

	if (composerForm) {
		composerForm.addEventListener('submit', function (event) {
			event.preventDefault();
			var messageField = composerForm.querySelector('textarea');
			var message = (messageField.value || '').trim();

			if (!message) {
				setFeedback(config.strings.messageError, true);
				return;
			}

			appendMessage('user', message);
			setFeedback('', false);
			messageField.value = '';
			messageField.disabled = true;
			composerForm.querySelector('button').disabled = true;

			sendPayload({
				visitor_name: state.profile.name,
				visitor_email: state.profile.email,
				message: message
			}).then(function (data) {
				if (data.system_reply) {
					appendMessage('agent', data.system_reply);
					playTone();
				}
			}).catch(function (error) {
				appendMessage('agent', error.message || config.strings.submitError);
				setFeedback(error.message || config.strings.submitError, true);
			}).finally(function () {
				messageField.disabled = false;
				composerForm.querySelector('button').disabled = false;
				messageField.focus();
			});
		});
	}

	if (offlineForm) {
		offlineForm.addEventListener('submit', function (event) {
			event.preventDefault();
			var formData = new FormData(offlineForm);
			var payload = {
				visitor_name: (formData.get('visitor_name') || '').toString().trim(),
				visitor_email: (formData.get('visitor_email') || '').toString().trim(),
				message: (formData.get('message') || '').toString().trim()
			};

			if (!payload.visitor_name || !payload.visitor_email || !payload.message) {
				setFeedback(config.strings.offlineError, true);
				return;
			}

			offlineForm.querySelector('button').disabled = true;
			setFeedback('', false);

			sendPayload(payload).then(function (data) {
				setFeedback(data.system_reply || config.strings.offlineThanks, false);
				offlineForm.reset();
				playTone();
			}).catch(function (error) {
				setFeedback(error.message || config.strings.submitError, true);
			}).finally(function () {
				offlineForm.querySelector('button').disabled = false;
			});
		});
	}

	renderTranscript();
	renderMode();
	setOpen(!!state.isOpen, !!state.closedByUser);

	if (config.autoOpenSeconds > 0 && !state.closedByUser && !state.isOpen) {
		window.setTimeout(function () {
			if (!state.closedByUser && !state.isOpen) {
				setOpen(true, false);
			}
		}, config.autoOpenSeconds * 1000);
	}
})();
