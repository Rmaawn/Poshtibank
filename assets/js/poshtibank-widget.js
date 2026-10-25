/**
 * Poshtibank Frontend Widget Engine
 *
 * 100% Modular Vanilla JavaScript (Zero jQuery)
 * - Adaptive Short Polling (3s -> 10s -> 30s -> Paused when closed)
 * - Web Audio API synthesized notification sound
 * - Tab Title flashing on background updates
 * - FAQ Accordion & Multi-channel communication hub
 *
 * @package Poshtibank
 */

(function () {
	'use strict';

	// Check if configuration exists
	if (typeof window.poshtibankConfig === 'undefined') {
		return;
	}

	var config = window.poshtibankConfig;

	// State
	var state = {
		sessionId: localStorage.getItem('poshtibank_session_id') || null,
		userName: localStorage.getItem('poshtibank_user_name') || (config.user.name || ''),
		userPhone: localStorage.getItem('poshtibank_user_phone') || '',
		isOpen: false,
		currentView: 'hub', // 'hub', 'prechat', 'chat'
		lastMessageId: 0,
		unreadCount: 0,
		isOnline: true,
		mode: '', // 'ai' | 'human' — resolved by the server on session init.
		pollingTimer: null,
		pollingInterval: 3000,
		isUserActive: true,
		idleTimer: null,
		originalTitle: document.title,
		titleFlashInterval: null,
		isWindowFocused: true,
	};

	// DOM Elements Cache
	var elements = {};

	/**
	 * Initialize the widget once DOM is ready.
	 */
	function init() {
		cacheElements();
		if (!elements.root) return;

		bindEvents();
		renderChannels();
		renderFaqs();
		initProactiveTriggers();

		// If user already had an active chat session, resume it
		if (state.sessionId) {
			initSession();
		}
	}

	/**
	 * Cache DOM element references.
	 */
	function cacheElements() {
		elements.root = document.getElementById('poshtibank-widget-root');
		if (!elements.root) return;

		elements.launcher = document.getElementById('poshtibank-launcher');
		elements.badge = document.getElementById('poshtibank-unread-badge');
		elements.iconChat = elements.launcher.querySelector('.icon-chat');
		elements.iconClose = elements.launcher.querySelector('.icon-close');
		elements.window = document.getElementById('poshtibank-window');

		// Header buttons
		elements.btnBack = document.getElementById('poshtibank-btn-back');
		elements.btnMinimize = document.getElementById('poshtibank-btn-minimize');
		elements.agentStatus = document.getElementById('poshtibank-agent-status');
		elements.headerSubtitle = document.getElementById('poshtibank-header-subtitle');

		// Views
		elements.viewHub = document.getElementById('poshtibank-view-hub');
		elements.viewPrechat = document.getElementById('poshtibank-view-prechat');
		elements.viewChat = document.getElementById('poshtibank-view-chat');

		// Hub Elements
		elements.modeAi = document.getElementById('poshtibank-mode-ai');
		elements.modeHuman = document.getElementById('poshtibank-mode-human');
		elements.channelsGrid = document.getElementById('poshtibank-channels-grid');
		elements.channelsSection = document.getElementById('poshtibank-channels-section');
		elements.faqList = document.getElementById('poshtibank-faq-list');
		elements.faqsSection = document.getElementById('poshtibank-faqs-section');

		// Prechat Form Elements
		elements.prechatForm = document.getElementById('poshtibank-prechat-form');
		elements.inputPreName = document.getElementById('pb-pre-name');
		elements.inputPrePhone = document.getElementById('pb-pre-phone');
		elements.prechatError = document.getElementById('poshtibank-prechat-error');

		// Chat Elements
		elements.modeStrip = document.getElementById('poshtibank-mode-strip');
		elements.modeStripText = document.getElementById('poshtibank-mode-strip-text');
		elements.btnAskHuman = document.getElementById('poshtibank-btn-ask-human');
		elements.stream = document.getElementById('poshtibank-messages-stream');
		elements.inputText = document.getElementById('poshtibank-input-text');
		elements.btnSend = document.getElementById('poshtibank-btn-send');
		elements.inputHoneypot = document.getElementById('pb-hp-check');
		elements.typingIndicator = document.getElementById('poshtibank-typing');

		// Rich Media Elements (Phase 5)
		elements.btnVoice = document.getElementById('poshtibank-btn-voice');
		elements.btnAttach = document.getElementById('poshtibank-btn-attach');
		elements.btnScreenshot = document.getElementById('poshtibank-btn-screenshot');
		elements.fileInput = document.getElementById('poshtibank-file-input');
		elements.voiceBar = document.getElementById('poshtibank-voice-bar');
		elements.voiceTimer = document.getElementById('poshtibank-voice-timer');
		elements.btnCancelVoice = document.getElementById('poshtibank-btn-cancel-voice');
		elements.btnSendVoice = document.getElementById('poshtibank-btn-send-voice');
	}

	/**
	 * Attach event listeners.
	 */
	function bindEvents() {
		// Launcher click: toggle chat window
		elements.launcher.addEventListener('click', toggleWindow);

		// Header buttons
		elements.btnMinimize.addEventListener('click', closeWindow);
		elements.btnBack.addEventListener('click', function () {
			switchView('hub');
		});

		// Hub responder chooser
		[elements.modeAi, elements.modeHuman].forEach(function (card) {
			if (!card) return;
			card.addEventListener('click', function () {
				chooseMode(card.getAttribute('data-mode'));
			});
		});

		// Hand the conversation from the assistant to a human
		if (elements.btnAskHuman) {
			elements.btnAskHuman.addEventListener('click', function () {
				switchMode('human');
			});
		}

		// Pre-chat form submit
		elements.prechatForm.addEventListener('submit', function (e) {
			e.preventDefault();
			handlePrechatSubmit();
		});

		// Send message button & Enter key
		elements.btnSend.addEventListener('click', handleSendMessage);
		elements.inputText.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				handleSendMessage();
			}
		});

		// Rich Media Listeners (Phase 5)
		if (elements.btnVoice) {
			elements.btnVoice.addEventListener('click', startVoiceRecording);
			elements.btnCancelVoice.addEventListener('click', cancelVoiceRecording);
			elements.btnSendVoice.addEventListener('click', sendVoiceRecording);
		}

		if (elements.btnAttach) {
			elements.btnAttach.addEventListener('click', function () {
				elements.fileInput.click();
			});
			elements.fileInput.addEventListener('change', handleFileSelected);
		}

		if (elements.btnScreenshot) {
			elements.btnScreenshot.addEventListener('click', handleCaptureScreenshot);
		}

		// Delegated click for coupon copy (Phase 6)
		elements.stream.addEventListener('click', function (e) {
			var copyBtn = e.target.closest('.poshtibank-btn-copy-coupon');
			if (copyBtn) {
				var code = copyBtn.getAttribute('data-code');
				if (navigator.clipboard) {
					navigator.clipboard.writeText(code).then(function () {
						copyBtn.textContent = config.i18n.couponCopied;
						copyBtn.classList.add('copied');
						setTimeout(function () {
							copyBtn.textContent = config.i18n.copyCoupon;
							copyBtn.classList.remove('copied');
						}, 2500);
					});
				}
			}
		});

		// Textarea auto-resize
		elements.inputText.addEventListener('input', function () {
			this.style.height = 'auto';
			this.style.height = Math.min(this.scrollHeight, 100) + 'px';
		});

		// Track user activity for Adaptive Polling
		['mousemove', 'keydown', 'click', 'touchstart'].forEach(function (evt) {
			window.addEventListener(evt, resetUserIdle, { passive: true });
		});

		// Window focus & visibility for tab title flashing & sound
		window.addEventListener('focus', function () {
			state.isWindowFocused = true;
			stopTitleFlashing();
		});

		window.addEventListener('blur', function () {
			state.isWindowFocused = false;
		});

		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				state.isWindowFocused = true;
				stopTitleFlashing();
			}
		});
	}

	/**
	 * Toggle chat window visibility.
	 */
	function toggleWindow() {
		if (state.isOpen) {
			closeWindow();
		} else {
			openWindow();
		}
	}

	/**
	 * Open chat window.
	 */
	function openWindow() {
		state.isOpen = true;
		elements.window.style.display = 'flex';
		elements.iconChat.style.display = 'none';
		elements.iconClose.style.display = 'flex';

		// Clear unread badge
		clearUnreadBadge();

		// If already in chat, start adaptive polling
		if (state.currentView === 'chat') {
			startAdaptivePolling();
			scrollStreamToBottom();
		}
	}

	/**
	 * Close / Minimize chat window.
	 */
	function closeWindow() {
		state.isOpen = false;
		elements.window.style.display = 'none';
		elements.iconChat.style.display = 'flex';
		elements.iconClose.style.display = 'none';

		// Stop polling to reduce server load
		stopAdaptivePolling();
	}

	/**
	 * Switch between views ('hub', 'prechat', 'chat').
	 *
	 * @param {string} viewName View to activate.
	 */
	function switchView(viewName) {
		state.currentView = viewName;

		elements.viewHub.classList.remove('active');
		elements.viewPrechat.classList.remove('active');
		elements.viewChat.classList.remove('active');

		if (viewName === 'hub') {
			elements.viewHub.classList.add('active');
			elements.btnBack.style.display = 'none';
			stopAdaptivePolling();
			renderModeStrip();
		} else if (viewName === 'prechat') {
			elements.viewPrechat.classList.add('active');
			elements.btnBack.style.display = 'flex';
			if (state.userName) elements.inputPreName.value = state.userName;
			if (state.userPhone) elements.inputPrePhone.value = state.userPhone;
			elements.inputPreName.focus();
		} else if (viewName === 'chat') {
			elements.viewChat.classList.add('active');
			elements.btnBack.style.display = 'flex';
			renderHandoffBanner();
			renderModeStrip();
			startAdaptivePolling();
			setTimeout(function () {
				elements.inputText.focus();
				scrollStreamToBottom();
			}, 100);
		}
	}

	/**
	 * Open the chat in the responder the customer picked.
	 *
	 * @param {string} mode 'ai' or 'human'.
	 */
	function chooseMode(mode) {
		if (mode === 'ai' && !(config.ai && config.ai.available)) {
			mode = 'human';
		}

		state.mode = mode;

		// The lead form sits between the choice and the conversation.
		if (config.settings.requireContact && (!state.userName || !state.userPhone)) {
			switchView('prechat');
			return;
		}

		// Switch to chat view and initialize session.
		switchView('chat');
		initSession();
	}

	/**
	 * Paint the responder strip above the message stream.
	 */
	function renderModeStrip() {
		if (!elements.modeStrip) return;

		if (state.currentView !== 'chat' || !state.mode) {
			elements.modeStrip.style.display = 'none';
			return;
		}

		var isAi = state.mode === 'ai';

		elements.modeStrip.classList.toggle('is-ai', isAi);
		elements.modeStripText.textContent = isAi ? config.i18n.modeAiNotice : config.i18n.modeHumanNotice;
		elements.btnAskHuman.style.display = isAi ? '' : 'none';
		elements.modeStrip.style.display = 'flex';
	}

	/**
	 * Move a live conversation between the assistant and a human operator.
	 *
	 * @param {string} mode Target mode.
	 */
	function switchMode(mode) {
		if (state.mode === mode) return;

		if (!state.sessionId) {
			state.mode = mode;
			renderModeStrip();
			return;
		}

		if (elements.btnAskHuman) elements.btnAskHuman.disabled = true;

		apiRequest('session/mode', 'POST', { session_id: state.sessionId, mode: mode }, function (err, res) {
			if (elements.btnAskHuman) elements.btnAskHuman.disabled = false;

			if (err || !res || !res.mode) {
				console.error('[Poshtibank mode error]', err || res);
				return;
			}

			state.mode = res.mode;
			renderModeStrip();

			// The handoff notice is stored server-side; pull it in right away
			// instead of waiting for the next scheduled poll.
			startAdaptivePolling();
		});
	}

	/**
	 * Handle pre-chat lead form submission.
	 */
	function handlePrechatSubmit() {
		var name = elements.inputPreName.value.trim();
		var phone = elements.inputPrePhone.value.trim();

		elements.prechatError.style.display = 'none';

		if (!name || !phone) {
			showPrechatError(config.i18n.requiredFields);
			return;
		}

		// Validate Iranian phone number (09xx)
		var phoneRegex = /^(\+98|0)?9\d{9}$/;
		if (!phoneRegex.test(phone)) {
			showPrechatError(config.i18n.phoneInvalid);
			return;
		}

		state.userName = name;
		state.userPhone = phone;
		localStorage.setItem('poshtibank_user_name', name);
		localStorage.setItem('poshtibank_user_phone', phone);

		initSession(function () {
			switchView('chat');
		}, function (err) {
			// Session init failed — show error in prechat form.
			showPrechatError(config.i18n.connectionError);
		});
	}

	/**
	 * Show error in pre-chat form.
	 *
	 * @param {string} msg Error message.
	 */
	function showPrechatError(msg) {
		elements.prechatError.textContent = msg;
		elements.prechatError.style.display = 'block';
	}

	/**
	 * REST API request wrapper.
	 *
	 * @param {string} endpoint Endpoint path.
	 * @param {string} method   HTTP method.
	 * @param {object} data     Payload.
	 * @param {function} callback Success callback.
	 */
	function apiRequest(endpoint, method, data, callback, retried) {
		var xhr = new XMLHttpRequest();
		xhr.open(method, config.restUrl + endpoint, true);
		xhr.setRequestHeader('Content-Type', 'application/json');

		if (config.nonce) {
			xhr.setRequestHeader('X-WP-Nonce', config.nonce);
		}

		xhr.onload = function () {
			if (xhr.status >= 200 && xhr.status < 300) {
				try {
					var response = JSON.parse(xhr.responseText);
					if (callback) callback(null, response);
				} catch (e) {
					if (callback) callback(e);
				}
				return;
			}

			var err = null;
			try { err = JSON.parse(xhr.responseText); } catch (e) {}

			// A page cache can serve a page whose nonce has already expired.
			// These endpoints are public, so dropping the nonce and retrying
			// is better than leaving the visitor with a dead chat.
			if (!retried && xhr.status === 403 && err && err.code === 'rest_cookie_invalid_nonce') {
				config.nonce = '';
				apiRequest(endpoint, method, data, callback, true);
				return;
			}

			if (callback) callback(err || new Error('Request failed with status ' + xhr.status));
		};

		xhr.onerror = function () {
			if (callback) callback(new Error('Network error.'));
		};

		xhr.send(data ? JSON.stringify(data) : null);
	}

	/**
	 * Initialize or resume session with server.
	 *
	 * @param {function} onComplete Completion callback (called on success).
	 * @param {function} onError    Error callback (called on failure).
	 */
	function initSession(onComplete, onError) {
		var payload = {
			session_id: state.sessionId,
			user_name: state.userName,
			user_phone: state.userPhone,
			mode: state.mode || '',
			current_page: config.page.url,
			referer: document.referrer || '',
			wc: config.wc || null,
		};

		apiRequest('session/init', 'POST', payload, function (err, res) {
			if (err) {
				console.error('[Poshtibank]', err);
				// If resuming a stored session failed, clear stale id and retry as a fresh session
				if (state.sessionId && !payload._retriedFresh) {
					state.sessionId = null;
					localStorage.removeItem('poshtibank_session_id');
					payload.session_id = '';
					payload._retriedFresh = true;
					initSession(onComplete, onError);
					return;
				}
				reportFailure(err, config.i18n.connectionError);
				if (onError) onError(err);
				return;
			}

			if (res.session_id) {
				state.sessionId = res.session_id;
				localStorage.setItem('poshtibank_session_id', res.session_id);
			}

			// The server owns the responder mode: it normalises legacy sessions.
			if (res.mode) {
				state.mode = res.mode;
			}
			renderModeStrip();

			// Update Online/Offline status indicator
			state.isOnline = res.is_online;
			updateOnlineStatusIndicator(res.is_online);

			// Render historical messages
			if (res.messages && res.messages.length) {
				renderMessages(res.messages, true);
			}

			if (onComplete) onComplete();
		});
	}

	/**
	 * Explain a failed request inside the conversation.
	 *
	 * A silent console error looks like a broken widget, so the visitor gets
	 * the server's own message when there is one.
	 *
	 * @param {object|string} err      Error from apiRequest.
	 * @param {string}        fallback Localised message to use otherwise.
	 */
	function reportFailure(err, fallback) {
		var message = fallback;

		if (err && typeof err.error === 'string' && err.error) {
			message = err.error;
		} else if (err && err.code && typeof err.message === 'string' && err.message) {
			message = err.message;
		}

		appendSystemNotice(message);
	}

	/**
	 * Append a centred system notice to the stream.
	 *
	 * @param {string} text Notice text.
	 */
	function appendSystemNotice(text) {
		if (!elements.stream) return;

		var last = elements.stream.lastElementChild;
		if (last && last.classList.contains('msg-system') && last.textContent === text) {
			return;
		}

		var notice = document.createElement('div');
		notice.className = 'poshtibank-msg msg-system';
		notice.textContent = text;
		elements.stream.appendChild(notice);
		scrollStreamToBottom();
	}

	/**
	 * Update online dot indicator and subtitle.
	 *
	 * @param {boolean} isOnline
	 */
	function updateOnlineStatusIndicator(isOnline) {
		if (isOnline) {
			elements.agentStatus.classList.add('online');
			elements.headerSubtitle.textContent = config.settings.widgetSubtitle || config.i18n.online;
		} else {
			elements.agentStatus.classList.remove('online');
			elements.headerSubtitle.textContent = config.i18n.offline;
		}
	}

	/**
	 * Handle user sending a message.
	 */
	function handleSendMessage() {
		var text = elements.inputText.value.trim();
		if (!text) return;

		// Honeypot check
		if (elements.inputHoneypot.value) {
			return;
		}

		// Guard: if session is not ready yet, initialize it and retry sending.
		if (!state.sessionId) {
			initSession(function () {
				handleSendMessage();
			});
			return;
		}

		elements.inputText.value = '';
		elements.inputText.style.height = 'auto';

		// Create optimistic bubble in UI
		var tempId = 'temp-' + Date.now();
		var tempMsg = {
			message_id: tempId,
			sender: 'user',
			content: text,
			created_at: formatTime(new Date()),
		};
		appendMessageBubble(tempMsg);
		scrollStreamToBottom();

		// Send to server
		var payload = {
			session_id: state.sessionId,
			content: text,
			type: 'text',
			hp_check: elements.inputHoneypot.value,
			metadata: {
				page_url: config.page.url,
				page_title: config.page.title,
			},
		};

		apiRequest('messages/send', 'POST', payload, function (err, res) {
			if (err) {
				console.error('[Poshtibank send error]', err);

				// Withdraw the bubble and give the text back, so the visitor
				// can retry without retyping.
				var failed = document.getElementById('msg-' + tempId);
				if (failed) failed.remove();

				elements.inputText.value = text;
				elements.inputText.style.height = 'auto';
				reportFailure(err, config.i18n.sendFailed);
				return;
			}

			// If AI First-Line Responder provided an instant response (Phase 7)
			if (res && res.ai_reply) {
				elements.typingIndicator.style.display = 'flex';
				scrollStreamToBottom();
				setTimeout(function () {
					elements.typingIndicator.style.display = 'none';

					// Prefer the stored row: it carries the id the poll will
					// return, so the same answer is never painted twice.
					var aiMsg = res.ai_message || {
						message_id: 'ai-' + Date.now(),
						sender: 'ai',
						content: res.ai_reply,
						created_at: formatTime(new Date()),
					};
					appendMessageBubble(aiMsg);
					scrollStreamToBottom();
					playNotificationSound();
				}, 500);
			}

			// Reset polling interval to active (3s)
			state.pollingInterval = 3000;
			resetUserIdle();
		});
	}

	/**
	 * Adaptive Short Polling loop.
	 */
	function startAdaptivePolling() {
		stopAdaptivePolling();
		pollOnce();
	}

	/**
	 * Stop Adaptive Polling.
	 */
	function stopAdaptivePolling() {
		if (state.pollingTimer) {
			clearTimeout(state.pollingTimer);
			state.pollingTimer = null;
		}
	}

	/**
	 * Execute one poll iteration.
	 */
	function pollOnce() {
		if (!state.isOpen || state.currentView !== 'chat' || !state.sessionId) {
			return;
		}

		var url = 'messages/poll?session_id=' + encodeURIComponent(state.sessionId) + '&after_id=' + state.lastMessageId;

		apiRequest(url, 'GET', null, function (err, res) {
			if (!err && res && res.new_messages && res.new_messages.length) {
				renderMessages(res.new_messages, false);

				// Play sound & flash title if user is in background tab
				if (!state.isWindowFocused || !state.isOpen) {
					playNotificationSound();
					startTitleFlashing();
					incrementUnreadBadge(res.new_messages.length);
				}
			}

			// The server may have handed the thread to a human meanwhile.
			if (res && res.mode && res.mode !== state.mode) {
				state.mode = res.mode;
				renderModeStrip();
			}

			// Render CSAT Survey if session was closed by admin
			if (res && res.status === 'closed' && !document.getElementById('poshtibank-csat-box')) {
				renderCsatSurvey();
			}

			// Schedule next poll
			state.pollingTimer = setTimeout(pollOnce, state.pollingInterval);
		});
	}

	/**
	 * Reset idle timer and restore fast polling rate (3s).
	 */
	function resetUserIdle() {
		state.isUserActive = true;
		state.pollingInterval = 3000;

		if (state.idleTimer) {
			clearTimeout(state.idleTimer);
		}

		// After 30s of inactivity, increase to 10s
		state.idleTimer = setTimeout(function () {
			state.isUserActive = false;
			state.pollingInterval = 10000;

			// After another 90s (total 2 min), increase to 30s
			state.idleTimer = setTimeout(function () {
				state.pollingInterval = 30000;
			}, 90000);
		}, 30000);
	}

	/**
	 * Render messages into stream.
	 *
	 * @param {array}   messages List of message objects.
	 * @param {boolean} isInitial True if loading history.
	 */
	function renderMessages(messages, isInitial) {
		messages.forEach(function (msg) {
			if (parseInt(msg.id, 10) > state.lastMessageId) {
				state.lastMessageId = parseInt(msg.id, 10);
			}
			appendMessageBubble(msg);
		});

		scrollStreamToBottom();
	}

	/**
	 * Append single message bubble to chat DOM with Rich Media support (Phase 5).
	 *
	 * @param {object} msg Message object.
	 */
	function appendMessageBubble(msg) {
		var bubbleId = 'msg-' + (msg.message_id || msg.id);

		// History, polls and optimistic bubbles can describe the same message.
		if (document.getElementById(bubbleId)) {
			return;
		}

		var bubble = document.createElement('div');
		bubble.className = 'poshtibank-msg msg-' + (msg.sender || 'user');
		bubble.id = bubbleId;

		var senderName = '';
		if (msg.sender === 'admin') {
			senderName = msg.sender_name || 'پشتیبان';
		} else if (msg.sender === 'ai') {
			senderName = 'دستیار هوشمند';
		}

		var html = '';
		if (senderName) {
			html += '<div class="poshtibank-msg-sender">' + escapeHtml(senderName) + '</div>';
		}

		var type = msg.type || 'text';
		var contentHtml = '';

		if (type === 'voice') {
			// Rich Voice Audio Player
			var audioId = 'audio-' + (msg.message_id || msg.id);
			contentHtml = '<div class="poshtibank-audio-player" data-src="' + escapeHtml(msg.content) + '">' +
				'<audio id="' + audioId + '" src="' + escapeHtml(msg.content) + '" preload="metadata"></audio>' +
				'<button type="button" class="poshtibank-audio-play-btn" aria-label="پخش صدا">' +
					'<svg class="icon-play" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>' +
					'<svg class="icon-pause" viewBox="0 0 24 24" fill="currentColor" style="display:none;"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>' +
				'</button>' +
				'<div class="poshtibank-audio-track">' +
					'<div class="poshtibank-audio-progress"><div class="poshtibank-audio-progress-bar"></div></div>' +
					'<span class="poshtibank-audio-time">00:00</span>' +
				'</div>' +
			'</div>';
		} else if (type === 'image') {
			// Image preview bubble
			contentHtml = '<div class="poshtibank-msg-image">' +
				'<a href="' + escapeHtml(msg.content) + '" target="_blank" rel="noopener noreferrer">' +
					'<img src="' + escapeHtml(msg.content) + '" alt="تصویر" loading="lazy">' +
				'</a>' +
			'</div>';
		} else if (type === 'file') {
			// File / Document bubble
			var meta = msg.metadata ? (typeof msg.metadata === 'string' ? JSON.parse(msg.metadata) : msg.metadata) : {};
			var fileName = meta.file_name || msg.content.split('/').pop() || 'فایل ضمیمه';
			var fileSize = meta.file_size ? formatBytes(meta.file_size) : '';

			contentHtml = '<a class="poshtibank-msg-file" href="' + escapeHtml(msg.content) + '" target="_blank" download rel="noopener noreferrer">' +
				'<div class="poshtibank-file-icon">' +
					'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>' +
				'</div>' +
				'<div class="poshtibank-file-info">' +
					'<span class="poshtibank-file-name">' + escapeHtml(fileName) + '</span>' +
					(fileSize ? '<span class="poshtibank-file-size">' + escapeHtml(fileSize) + '</span>' : '') +
				'</div>' +
			'</a>';
		} else if (type === 'product_card') {
			// WooCommerce Product Card
			var meta = msg.metadata ? (typeof msg.metadata === 'string' ? JSON.parse(msg.metadata) : msg.metadata) : {};
			contentHtml = '<div class="poshtibank-product-card">' +
				(meta.image ? '<div class="poshtibank-product-thumb"><img src="' + escapeHtml(meta.image) + '" alt="' + escapeHtml(meta.title || '') + '"></div>' : '') +
				'<div class="poshtibank-product-details">' +
					'<h6 class="poshtibank-product-title">' + escapeHtml(meta.title || msg.content) + '</h6>' +
					(meta.price_formatted ? '<span class="poshtibank-product-price">' + escapeHtml(meta.price_formatted) + '</span>' : '') +
					(meta.url ? '<a class="poshtibank-btn-product-buy" href="' + escapeHtml(meta.url) + '" target="_blank" rel="noopener noreferrer">' + config.i18n.viewProduct + '</a>' : '') +
				'</div>' +
			'</div>';
		} else if (type === 'coupon') {
			// WooCommerce Coupon Card
			var meta = msg.metadata ? (typeof msg.metadata === 'string' ? JSON.parse(msg.metadata) : msg.metadata) : {};
			var couponCode = meta.code || msg.content;
			contentHtml = '<div class="poshtibank-coupon-card">' +
				'<span class="poshtibank-coupon-badge">' + escapeHtml(meta.label || 'کد تخفیف ویژه') + '</span>' +
				'<div class="poshtibank-coupon-code">' + escapeHtml(couponCode) + '</div>' +
				'<button type="button" class="poshtibank-btn-copy-coupon" data-code="' + escapeHtml(couponCode) + '">' + config.i18n.copyCoupon + '</button>' +
			'</div>';
		} else {
			// Plain text message
			contentHtml = formatMessageContent(msg.content);
		}

		html += '<div class="poshtibank-msg-content">' + contentHtml + '</div>';
		html += '<div class="poshtibank-msg-time">' + formatTime(msg.created_at) + '</div>';

		bubble.innerHTML = html;
		elements.stream.appendChild(bubble);

		// Initialize audio player if voice bubble
		if (type === 'voice') {
			initAudioPlayer(bubble.querySelector('.poshtibank-audio-player'));
		}
	}

	/**
	 * Initialize interactive audio player inside voice message bubble.
	 *
	 * @param {HTMLElement} playerContainer
	 */
	function initAudioPlayer(playerContainer) {
		if (!playerContainer) return;

		var audio = playerContainer.querySelector('audio');
		var btn = playerContainer.querySelector('.poshtibank-audio-play-btn');
		var iconPlay = btn.querySelector('.icon-play');
		var iconPause = btn.querySelector('.icon-pause');
		var progressBar = playerContainer.querySelector('.poshtibank-audio-progress-bar');
		var timeDisplay = playerContainer.querySelector('.poshtibank-audio-time');
		var progressContainer = playerContainer.querySelector('.poshtibank-audio-progress');

		audio.addEventListener('loadedmetadata', function () {
			timeDisplay.textContent = formatTimer(Math.floor(audio.duration || 0));
		});

		btn.addEventListener('click', function () {
			if (audio.paused) {
				// Pause any other playing audio
				document.querySelectorAll('audio').forEach(function (a) {
					if (a !== audio && !a.paused) a.pause();
				});
				audio.play();
			} else {
				audio.pause();
			}
		});

		audio.addEventListener('play', function () {
			iconPlay.style.display = 'none';
			iconPause.style.display = 'block';
		});

		audio.addEventListener('pause', function () {
			iconPlay.style.display = 'block';
			iconPause.style.display = 'none';
		});

		audio.addEventListener('timeupdate', function () {
			if (audio.duration) {
				var pct = (audio.currentTime / audio.duration) * 100;
				progressBar.style.width = pct + '%';
				timeDisplay.textContent = formatTimer(Math.floor(audio.currentTime));
			}
		});

		audio.addEventListener('ended', function () {
			iconPlay.style.display = 'block';
			iconPause.style.display = 'none';
			progressBar.style.width = '0%';
			timeDisplay.textContent = formatTimer(Math.floor(audio.duration || 0));
		});

		// Seek click
		progressContainer.addEventListener('click', function (e) {
			if (audio.duration) {
				var rect = progressContainer.getBoundingClientRect();
				var pos = (e.clientX - rect.left) / rect.width;
				audio.currentTime = pos * audio.duration;
			}
		});
	}

	/*
	|--------------------------------------------------------------------------
	| Voice Messaging, Uploads & Screenshot (Phase 5)
	|--------------------------------------------------------------------------
	*/

	var mediaRecorderInstance = null;
	var audioStreamInstance = null;
	var voiceChunks = [];
	var voiceTimerInt = null;
	var voiceTotalSecs = 0;
	var shouldSendVoiceMsg = false;

	/**
	 * Start voice recording using MediaRecorder API.
	 */
	function startVoiceRecording() {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			alert('قابلیت ضبط صدا در این مرورگر پشتیبانی نمی‌شود.');
			return;
		}

		navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
			audioStreamInstance = stream;
			voiceChunks = [];
			shouldSendVoiceMsg = false;
			voiceTotalSecs = 0;

			elements.voiceTimer.textContent = '00:00';
			elements.voiceBar.style.display = 'flex';

			var mimeType = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : 'audio/ogg';
			mediaRecorderInstance = new MediaRecorder(stream, { mimeType: mimeType });

			mediaRecorderInstance.ondataavailable = function (e) {
				if (e.data && e.data.size > 0) {
					voiceChunks.push(e.data);
				}
			};

			mediaRecorderInstance.onstop = function () {
				clearInterval(voiceTimerInt);
				elements.voiceBar.style.display = 'none';

				if (shouldSendVoiceMsg && voiceChunks.length > 0) {
					var blob = new Blob(voiceChunks, { type: mimeType });
					var ext = mimeType.indexOf('ogg') !== -1 ? 'ogg' : 'webm';
					var file = new File([blob], 'voice_' + Date.now() + '.' + ext, { type: mimeType });
					uploadMediaFile(file, 'voice', voiceTotalSecs);
				}

				if (audioStreamInstance) {
					audioStreamInstance.getTracks().forEach(function (t) { t.stop(); });
					audioStreamInstance = null;
				}
			};

			mediaRecorderInstance.start();

			voiceTimerInt = setInterval(function () {
				voiceTotalSecs++;
				elements.voiceTimer.textContent = formatTimer(voiceTotalSecs);
			}, 1000);
		}).catch(function (err) {
			console.error(err);
			alert('دسترسی به میکروفون تایید نشد یا میکروفونی متصل نیست.');
		});
	}

	/**
	 * Cancel voice recording.
	 */
	function cancelVoiceRecording() {
		shouldSendVoiceMsg = false;
		if (mediaRecorderInstance && mediaRecorderInstance.state !== 'inactive') {
			mediaRecorderInstance.stop();
		}
	}

	/**
	 * Finish and send voice recording.
	 */
	function sendVoiceRecording() {
		shouldSendVoiceMsg = true;
		if (mediaRecorderInstance && mediaRecorderInstance.state !== 'inactive') {
			mediaRecorderInstance.stop();
		}
	}

	/**
	 * Handle file selection from attachment input.
	 */
	function handleFileSelected(e) {
		var file = e.target.files[0];
		if (!file) return;

		e.target.value = '';
		var category = file.type.indexOf('image/') === 0 ? 'image' : 'file';
		uploadMediaFile(file, category, 0);
	}

	/**
	 * Built-in screenshot capture of current window/screen.
	 */
	function handleCaptureScreenshot() {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
			alert('قابلیت تصویر صفحه در این مرورگر پشتیبانی نمی‌شود.');
			return;
		}

		navigator.mediaDevices.getDisplayMedia({ video: { displaySurface: 'browser' } }).then(function (stream) {
			var video = document.createElement('video');
			video.srcObject = stream;
			video.play();

			video.onloadedmetadata = function () {
				setTimeout(function () {
					var canvas = document.createElement('canvas');
					canvas.width = video.videoWidth;
					canvas.height = video.videoHeight;
					var ctx = canvas.getContext('2d');
					ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

					stream.getTracks().forEach(function (t) { t.stop(); });

					canvas.toBlob(function (blob) {
						if (blob) {
							var file = new File([blob], 'screenshot_' + Date.now() + '.png', { type: 'image/png' });
							uploadMediaFile(file, 'screenshot', 0);
						}
					}, 'image/png');
				}, 350);
			};
		}).catch(function () {
			// User dismissed capture prompt
		});
	}

	/**
	 * Upload media file (voice, image, screenshot, document) via REST API.
	 *
	 * @param {File}   file     File object.
	 * @param {string} category 'voice', 'image', 'screenshot', 'file'.
	 * @param {number} duration Voice duration in seconds.
	 */
	function uploadMediaFile(file, category, duration) {
		if (!state.sessionId) {
			initSession(function () {
				uploadMediaFile(file, category, duration);
			});
			return;
		}

		// Optimistic bubble
		var optimisticType = (category === 'screenshot' || category === 'image') ? 'image' : category;
		var tempUrl = URL.createObjectURL(file);
		var tempMsg = {
			message_id: 'temp-' + Date.now(),
			sender: 'user',
			type: optimisticType,
			content: tempUrl,
			created_at: new Date(),
			metadata: {
				file_name: file.name,
				file_size: file.size,
				duration: duration,
			},
		};
		appendMessageBubble(tempMsg);
		scrollStreamToBottom();

		// FormData upload
		var formData = new FormData();
		formData.append('file', file);
		formData.append('session_id', state.sessionId);
		formData.append('category', category);
		if (duration > 0) {
			formData.append('duration', duration);
		}

		var xhr = new XMLHttpRequest();
		xhr.open('POST', config.restUrl + 'media/upload', true);
		xhr.setRequestHeader('X-WP-Nonce', config.nonce);

		xhr.onload = function () {
			if (xhr.status >= 200 && xhr.status < 300) {
				try {
					var res = JSON.parse(xhr.responseText);
					if (res.message && res.message.id) {
						state.lastMessageId = Math.max(state.lastMessageId, parseInt(res.message.id, 10));
					}
				} catch (e) {}
			} else {
				console.error('[Poshtibank media upload error]', xhr.responseText);
			}
		};

		xhr.send(formData);
	}

	/**
	 * Format seconds to MM:SS string.
	 *
	 * @param {number} sec
	 * @return {string}
	 */
	function formatTimer(sec) {
		var m = Math.floor(sec / 60).toString().padStart(2, '0');
		var s = (sec % 60).toString().padStart(2, '0');
		return m + ':' + s;
	}

	/**
	 * Format file bytes into KB or MB.
	 *
	 * @param {number} bytes
	 * @return {string}
	 */
	function formatBytes(bytes) {
		if (bytes < 1024) return bytes + ' B';
		if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
		return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
	}

	/**
	 * Format message content (links, linebreaks).
	 *
	 * @param {string} content
	 * @return {string}
	 */
	function formatMessageContent(content) {
		if (!content) return '';
		var safe = escapeHtml(content);
		// Convert URLs to clickable links
		var urlRegex = /(\b(https?):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
		safe = safe.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
		// Convert linebreaks
		return safe.replace(/\n/g, '<br>');
	}

	/**
	 * Format time string (HH:MM).
	 *
	 * @param {string|Date} dateStr
	 * @return {string}
	 */
	function formatTime(dateStr) {
		var d = dateStr ? new Date(dateStr) : new Date();
		if (isNaN(d.getTime())) return '';
		var h = d.getHours().toString().padStart(2, '0');
		var m = d.getMinutes().toString().padStart(2, '0');
		return h + ':' + m;
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} str
	 * @return {string}
	 */
	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	/**
	 * Scroll stream to bottom.
	 */
	function scrollStreamToBottom() {
		elements.stream.scrollTop = elements.stream.scrollHeight;
	}

	/**
	 * Render Pre-chat FAQs.
	 */
	function renderFaqs() {
		if (!config.faqs || !config.faqs.length) {
			elements.faqsSection.style.display = 'none';
			return;
		}

		elements.faqList.innerHTML = '';
		config.faqs.forEach(function (faq) {
			var item = document.createElement('div');
			item.className = 'poshtibank-faq-item';

			var q = document.createElement('div');
			q.className = 'poshtibank-faq-q';
			q.innerHTML = '<span>' + escapeHtml(faq.question) + '</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>';

			var a = document.createElement('div');
			a.className = 'poshtibank-faq-a';
			a.textContent = faq.answer;

			q.addEventListener('click', function () {
				item.classList.toggle('open');
			});

			item.appendChild(q);
			item.appendChild(a);
			elements.faqList.appendChild(item);
		});
	}

	/**
	 * Render Social Channels Grid.
	 */
	function renderChannels() {
		var channels = config.settings.channels || {};
		var list = [];

		if (channels.telegram_url) {
			list.push({ title: 'تلگرام', url: channels.telegram_url, bg: '#0088cc', icon: '✈️' });
		}
		if (channels.whatsapp_url) {
			list.push({ title: 'واتساپ', url: channels.whatsapp_url, bg: '#25D366', icon: '💬' });
		}
		if (channels.bale_url) {
			list.push({ title: 'بله', url: channels.bale_url, bg: '#00a693', icon: '🌿' });
		}
		if (channels.eitaa_url) {
			list.push({ title: 'ایتا', url: channels.eitaa_url, bg: '#f27a24', icon: '🟠' });
		}
		if (channels.instagram_url) {
			list.push({ title: 'اینستاگرام', url: channels.instagram_url, bg: '#e1306c', icon: '📸' });
		}
		if (channels.phone_number) {
			list.push({ title: 'تماس تلفنی', url: 'tel:' + channels.phone_number, bg: '#475569', icon: '📞' });
		}

		if (!list.length) {
			elements.channelsSection.style.display = 'none';
			return;
		}

		elements.channelsGrid.innerHTML = '';
		list.forEach(function (ch) {
			var a = document.createElement('a');
			a.className = 'poshtibank-channel-card';
			a.href = ch.url;
			a.target = '_blank';
			a.rel = 'noopener noreferrer';
			a.innerHTML = '<div class="poshtibank-channel-icon" style="background:' + ch.bg + ';">' + ch.icon + '</div><span>' + ch.title + '</span>';
			elements.channelsGrid.appendChild(a);
		});
	}

	/**
	 * Synthesize notification sound via Web Audio API.
	 */
	function playNotificationSound() {
		try {
			var AudioContextClass = window.AudioContext || window.webkitAudioContext;
			if (!AudioContextClass) return;

			var ctx = new AudioContextClass();
			var now = ctx.currentTime;

			var osc1 = ctx.createOscillator();
			var osc2 = ctx.createOscillator();
			var gain = ctx.createGain();

			osc1.type = 'sine';
			osc1.frequency.setValueAtTime(587.33, now); // D5

			osc2.type = 'sine';
			osc2.frequency.setValueAtTime(880, now + 0.08); // A5

			gain.gain.setValueAtTime(0.12, now);
			gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);

			osc1.connect(gain);
			osc2.connect(gain);
			gain.connect(ctx.destination);

			osc1.start(now);
			osc1.stop(now + 0.12);
			osc2.start(now + 0.08);
			osc2.stop(now + 0.35);
		} catch (e) {
			// Browser autoplay restrictions until first user interaction
		}
	}

	/**
	 * Flash browser tab title when message arrives in background.
	 */
	function startTitleFlashing() {
		if (state.titleFlashInterval) return;

		var isFlashing = false;
		state.titleFlashInterval = setInterval(function () {
			document.title = isFlashing ? config.i18n.newMsgTitle + ' ' + state.originalTitle : state.originalTitle;
			isFlashing = !isFlashing;
		}, 1000);
	}

	/**
	 * Stop flashing tab title.
	 */
	function stopTitleFlashing() {
		if (state.titleFlashInterval) {
			clearInterval(state.titleFlashInterval);
			state.titleFlashInterval = null;
			document.title = state.originalTitle;
		}
	}

	/**
	 * Increment unread badge counter.
	 *
	 * @param {number} count
	 */
	function incrementUnreadBadge(count) {
		state.unreadCount += count;
		elements.badge.textContent = state.unreadCount;
		elements.badge.style.display = 'flex';
	}

	/**
	 * Clear unread badge counter.
	 */
	function clearUnreadBadge() {
		state.unreadCount = 0;
		elements.badge.textContent = '0';
		elements.badge.style.display = 'none';
	}

	/*
	|--------------------------------------------------------------------------
	| Proactive Triggers, Handoff & CSAT (Phase 6)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Smart Proactive Triggers: Exit-Intent and Checkout Delay.
	 */
	function initProactiveTriggers() {
		// 1. Exit-Intent Trigger (detect mouse moving towards close button)
		if (!sessionStorage.getItem('pb_exit_triggered')) {
			document.addEventListener('mouseleave', function (e) {
				if (e.clientY <= 0 && !state.isOpen && !sessionStorage.getItem('pb_exit_triggered')) {
					sessionStorage.setItem('pb_exit_triggered', '1');
					showProactiveToast(config.i18n.exitPrompt);
				}
			});
		}

		// 2. Checkout Delay / Hesitation Trigger (30 seconds on checkout page)
		if (config.wc && config.wc.type === 'checkout' && !sessionStorage.getItem('pb_checkout_triggered')) {
			setTimeout(function () {
				if (!state.isOpen && !sessionStorage.getItem('pb_checkout_triggered')) {
					sessionStorage.setItem('pb_checkout_triggered', '1');
					showProactiveToast(config.i18n.checkoutPrompt);
				}
			}, 30000);
		}
	}

	/**
	 * Show proactive toast prompt above launcher button.
	 *
	 * @param {string} promptText
	 */
	function showProactiveToast(promptText) {
		if (document.getElementById('poshtibank-proactive-toast')) return;

		var toast = document.createElement('div');
		toast.id = 'poshtibank-proactive-toast';
		toast.className = 'poshtibank-proactive-toast';
		toast.innerHTML = '<div class="poshtibank-proactive-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></div>' +
			'<div class="poshtibank-proactive-content"><p>' + escapeHtml(promptText) + '</p></div>' +
			'<button type="button" class="poshtibank-proactive-close" aria-label="بستن">&times;</button>';

		toast.addEventListener('click', function (e) {
			if (e.target.classList.contains('poshtibank-proactive-close')) {
				e.stopPropagation();
				toast.remove();
				return;
			}
			toast.remove();
			openWindow();
			chooseMode(state.mode || '');
		});

		elements.root.appendChild(toast);

		// Play pleasant chime
		playNotificationSound();

		// Auto dismiss after 15 seconds
		setTimeout(function () {
			if (toast.parentNode) toast.remove();
		}, 15000);
	}

	/**
	 * Render CSAT Satisfaction Survey Card inside message stream.
	 */
	function renderCsatSurvey() {
		if (document.getElementById('poshtibank-csat-box')) return;

		var card = document.createElement('div');
		card.id = 'poshtibank-csat-box';
		card.className = 'poshtibank-csat-card';

		card.innerHTML = '<h5>' + escapeHtml(config.i18n.ratingTitle) + '</h5>' +
			'<div class="poshtibank-csat-stars" id="pb-csat-stars">' +
				'<button type="button" class="poshtibank-star-btn" data-val="1">★</button>' +
				'<button type="button" class="poshtibank-star-btn" data-val="2">★</button>' +
				'<button type="button" class="poshtibank-star-btn" data-val="3">★</button>' +
				'<button type="button" class="poshtibank-star-btn" data-val="4">★</button>' +
				'<button type="button" class="poshtibank-star-btn active" data-val="5">★</button>' +
			'</div>' +
			'<textarea class="poshtibank-csat-comment" id="pb-csat-comment" rows="2" placeholder="توضیحات یا نظر شما (اختیاری)..."></textarea>' +
			'<button type="button" class="poshtibank-btn-csat-submit" id="pb-btn-csat">' + escapeHtml(config.i18n.ratingSubmit) + '</button>';

		var selectedRating = 5;
		var starBtns = card.querySelectorAll('.poshtibank-star-btn');
		starBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				selectedRating = parseInt(this.getAttribute('data-val'), 10);
				starBtns.forEach(function (b) {
					var v = parseInt(b.getAttribute('data-val'), 10);
					if (v <= selectedRating) {
						b.classList.add('active');
					} else {
						b.classList.remove('active');
					}
				});
			});
		});

		var submitBtn = card.querySelector('#pb-btn-csat');
		submitBtn.addEventListener('click', function () {
			var comment = card.querySelector('#pb-csat-comment').value.trim();
			submitBtn.disabled = true;

			apiRequest('session/feedback', 'POST', {
				session_id: state.sessionId,
				rating: selectedRating,
				comment: comment
			}, function () {
				card.innerHTML = '<p style="color:#16a34a; font-weight:700; margin:4px 0;">' + escapeHtml(config.i18n.ratingThanks) + '</p>';
			});
		});

		elements.stream.appendChild(card);
		scrollStreamToBottom();
	}

	/**
	 * Render Seamless Handoff Banner at the bottom of the chat view.
	 */
	function renderHandoffBanner() {
		if (!config.handoff || (!config.handoff.telegram && !config.handoff.bale)) return;
		if (document.getElementById('poshtibank-handoff-banner')) return;

		var banner = document.createElement('div');
		banner.id = 'poshtibank-handoff-banner';
		banner.className = 'poshtibank-handoff-banner';

		var linksHtml = '';
		if (config.handoff.telegram) {
			var tgUrl = config.handoff.telegram + (state.sessionId ? '?start=pb_' + encodeURIComponent(state.sessionId) : '');
			linksHtml += '<a href="' + escapeHtml(tgUrl) + '" target="_blank" rel="noopener noreferrer" class="poshtibank-btn-handoff">✈️ تلگرام</a>';
		}
		if (config.handoff.bale) {
			var baleUrl = config.handoff.bale + (state.sessionId ? '?start=pb_' + encodeURIComponent(state.sessionId) : '');
			linksHtml += '<a href="' + escapeHtml(baleUrl) + '" target="_blank" rel="noopener noreferrer" class="poshtibank-btn-handoff">🌿 بله</a>';
		}

		banner.innerHTML = '<span>' + escapeHtml(config.i18n.handoffText) + ':</span><div class="poshtibank-handoff-links">' + linksHtml + '</div>';
		elements.viewChat.insertBefore(banner, elements.stream.nextSibling);
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
