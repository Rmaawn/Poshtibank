/**
 * Poshtibank Admin Scripts
 *
 * @package Poshtibank
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		// Initialize Color Pickers
		if ($.fn.wpColorPicker) {
			$('.poshtibank-color-picker').wpColorPicker();
		}

		// Tab Switching
		$('#poshtibank-tabs-nav .poshtibank-tab-link').on('click', function (e) {
			e.preventDefault();
			var target = $(this).attr('href');

			$('#poshtibank-tabs-nav .poshtibank-tab-link').removeClass('active');
			$(this).addClass('active');

			$('.poshtibank-tab-panel').removeClass('active');
			$(target).addClass('active');

			// Store active tab in localStorage
			if (window.localStorage) {
				localStorage.setItem('poshtibank_active_tab', target);
			}
		});

		// Restore active tab
		if (window.localStorage) {
			var savedTab = localStorage.getItem('poshtibank_active_tab');
			if (savedTab && $(savedTab).length) {
				$('#poshtibank-tabs-nav a[href="' + savedTab + '"]').trigger('click');
			}
		}

		// Helper function: Convert serialized array to nested object
		function parseFormObject($form) {
			var formData = $form.serializeArray();
			var result = {};

			$.each(formData, function () {
				var name = this.name;
				var value = this.value;

				// Match patterns like settings[group][subgroup][key]
				var matches = name.match(/\[(.*?)\]/g);
				if (matches) {
					var keys = matches.map(function (m) {
						return m.replace(/[\[\]]/g, '');
					});

					var current = result;
					for (var i = 0; i < keys.length; i++) {
						var k = keys[i];
						if (i === keys.length - 1) {
							current[k] = value;
						} else {
							if (!current[k]) {
								current[k] = {};
							}
							current = current[k];
						}
					}
				} else {
					result[name] = value;
				}
			});

			return result;
		}

		// AJAX Save Form
		function saveSettings() {
			var $status = $('#poshtibank-save-status');
			var $btnTop = $('#poshtibank-save-top');
			var $btnBottom = $('#poshtibank-save-bottom');

			$status.removeClass('success error').text(poshtibankAdmin.i18n.saving);
			$btnTop.add($btnBottom).prop('disabled', true);

			var settingsData = parseFormObject($('#poshtibank-settings-form'));

			$.ajax({
				url: poshtibankAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'poshtibank_save_settings',
					nonce: poshtibankAdmin.nonce,
					settings: settingsData
				},
				success: function (res) {
					$btnTop.add($btnBottom).prop('disabled', false);
					if (res.success) {
						$status.addClass('success').text(res.data.message || poshtibankAdmin.i18n.saved);
						setTimeout(function () {
							$status.fadeOut(400, function () {
								$(this).text('').show();
							});
						}, 4000);
					} else {
						$status.addClass('error').text(res.data.message || poshtibankAdmin.i18n.error);
					}
				},
				error: function () {
					$btnTop.add($btnBottom).prop('disabled', false);
					$status.addClass('error').text(poshtibankAdmin.i18n.error);
				}
			});
		}

		$('#poshtibank-settings-form').on('submit', function (e) {
			e.preventDefault();
			saveSettings();
		});

		$('#poshtibank-save-top').on('click', function (e) {
			e.preventDefault();
			saveSettings();
		});

		// Telegram: Test Bot Connection
		$('#btn-tg-test').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $status = $('#tg-webhook-status');
			var token = $('#poshtibank-tg-token').val();
			var proxy = $('#poshtibank-tg-proxy').val();

			$status.removeClass('success error').addClass('loading').text(poshtibankAdmin.i18n.testing).show();
			$btn.prop('disabled', true);

			$.post(poshtibankAdmin.ajaxUrl, {
				action: 'poshtibank_test_telegram',
				nonce: poshtibankAdmin.nonce,
				token: token,
				proxy_url: proxy
			}, function (res) {
				$btn.prop('disabled', false);
				$status.removeClass('loading');
				if (res.success) {
					$status.addClass('success').text(res.data.message);
				} else {
					$status.addClass('error').text(res.data.message);
				}
			}, 'json').fail(function () {
				$btn.prop('disabled', false);
				$status.removeClass('loading').addClass('error').text(poshtibankAdmin.i18n.error);
			});
		});

		// Telegram: Set Webhook
		$('#btn-tg-set-webhook').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $status = $('#tg-webhook-status');
			var token = $('#poshtibank-tg-token').val();
			var proxy = $('#poshtibank-tg-proxy').val();

			if (!poshtibankAdmin.isSSL && window.location.protocol !== 'https:') {
				if (!confirm(poshtibankAdmin.i18n.sslWarning + '\nآیا باز هم مایل به ادامه هستید؟')) {
					return;
				}
			}

			$status.removeClass('success error').addClass('loading').text(poshtibankAdmin.i18n.settingWebhook).show();
			$btn.prop('disabled', true);

			$.post(poshtibankAdmin.ajaxUrl, {
				action: 'poshtibank_set_telegram_webhook',
				nonce: poshtibankAdmin.nonce,
				token: token,
				proxy_url: proxy
			}, function (res) {
				$btn.prop('disabled', false);
				$status.removeClass('loading');
				if (res.success) {
					$status.addClass('success').text(res.data.message);
				} else {
					$status.addClass('error').text(res.data.message);
				}
			}, 'json').fail(function () {
				$btn.prop('disabled', false);
				$status.removeClass('loading').addClass('error').text(poshtibankAdmin.i18n.error);
			});
		});

		// Telegram: Delete Webhook
		$('#btn-tg-delete-webhook').on('click', function (e) {
			e.preventDefault();
			if (!confirm('آیا از حذف وبهوک تلگرام مطمئن هستید؟')) {
				return;
			}
			var $status = $('#tg-webhook-status');
			$status.removeClass('success error').addClass('loading').text(poshtibankAdmin.i18n.deletingWebhook).show();

			$.post(poshtibankAdmin.ajaxUrl, {
				action: 'poshtibank_delete_telegram_webhook',
				nonce: poshtibankAdmin.nonce
			}, function (res) {
				$status.removeClass('loading');
				if (res.success) {
					$status.addClass('success').text(res.data.message);
				} else {
					$status.addClass('error').text(res.data.message);
				}
			}, 'json');
		});

		// Bale: Test Bot
		$('#btn-bale-test').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $status = $('#bale-webhook-status');
			var token = $('#poshtibank-bale-token').val();

			$status.removeClass('success error').addClass('loading').text(poshtibankAdmin.i18n.testing).show();
			$btn.prop('disabled', true);

			$.post(poshtibankAdmin.ajaxUrl, {
				action: 'poshtibank_test_bale',
				nonce: poshtibankAdmin.nonce,
				token: token
			}, function (res) {
				$btn.prop('disabled', false);
				$status.removeClass('loading');
				if (res.success) {
					$status.addClass('success').text(res.data.message);
				} else {
					$status.addClass('error').text(res.data.message);
				}
			}, 'json').fail(function () {
				$btn.prop('disabled', false);
				$status.removeClass('loading').addClass('error').text(poshtibankAdmin.i18n.error);
			});
		});

		// Bale: Set Webhook
		$('#btn-bale-set-webhook').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $status = $('#bale-webhook-status');
			var token = $('#poshtibank-bale-token').val();

			$status.removeClass('success error').addClass('loading').text(poshtibankAdmin.i18n.settingWebhook).show();
			$btn.prop('disabled', true);

			$.post(poshtibankAdmin.ajaxUrl, {
				action: 'poshtibank_set_bale_webhook',
				nonce: poshtibankAdmin.nonce,
				token: token
			}, function (res) {
				$btn.prop('disabled', false);
				$status.removeClass('loading');
				if (res.success) {
					$status.addClass('success').text(res.data.message);
				} else {
					$status.addClass('error').text(res.data.message);
				}
			}, 'json').fail(function () {
				$btn.prop('disabled', false);
				$status.removeClass('loading').addClass('error').text(poshtibankAdmin.i18n.error);
			});
		});

		// Bale: Delete Webhook
		$('#btn-bale-delete-webhook').on('click', function (e) {
			e.preventDefault();
			if (!confirm('آیا از حذف وبهوک بله اطمینان دارید؟')) {
				return;
			}
			var $status = $('#bale-webhook-status');
			$status.removeClass('success error').addClass('loading').text(poshtibankAdmin.i18n.deletingWebhook).show();

			$.post(poshtibankAdmin.ajaxUrl, {
				action: 'poshtibank_delete_bale_webhook',
				nonce: poshtibankAdmin.nonce
			}, function (res) {
				$status.removeClass('loading');
				if (res.success) {
					$status.addClass('success').text(res.data.message);
				} else {
					$status.addClass('error').text(res.data.message);
				}
			}, 'json');
		});

		// AI Key Test
		$('#btn-test-ai').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $status = $('#ai-test-status');
			var provider = $('#poshtibank-ai-provider').val();
			var apiKey = $('#poshtibank-ai-key').val();

			$status.removeClass('success error').addClass('loading').text(poshtibankAdmin.i18n.testing).show();
			$btn.prop('disabled', true);

			$.post(poshtibankAdmin.ajaxUrl, {
				action: 'poshtibank_test_ai',
				nonce: poshtibankAdmin.nonce,
				provider: provider,
				api_key: apiKey
			}, function (res) {
				$btn.prop('disabled', false);
				$status.removeClass('loading');
				if (res.success) {
					$status.addClass('success').text(res.data.message);
				} else {
					$status.addClass('error').text(res.data.message);
				}
			}, 'json').fail(function () {
				$btn.prop('disabled', false);
				$status.removeClass('loading').addClass('error').text(poshtibankAdmin.i18n.error);
			});
		});
		// ==================================================================
		// Conversation screen: live replies
		// ==================================================================
		(function initConversationScreen() {
			var $stream = $('#poshtibank-admin-stream');

			if (!$stream.length) {
				return;
			}

			var $reply = $('#poshtibank-admin-reply');
			var $send = $('#poshtibank-admin-send');
			var $error = $('#poshtibank-admin-reply-error');
			var sessionId = $stream.attr('data-session-id');
			var lastId = parseInt($stream.attr('data-last-id'), 10) || 0;
			var sending = false;

			function escapeHtml(value) {
				return $('<div>').text(value === null || value === undefined ? '' : value).html();
			}

			function contentHtml(message) {
				if (message.type === 'image') {
					return '<a href="' + escapeHtml(message.content) + '" target="_blank" rel="noopener noreferrer">مشاهده تصویر</a>';
				}
				if (message.type === 'voice') {
					return '<audio controls preload="none" src="' + escapeHtml(message.content) + '"></audio>';
				}
				if (message.type === 'file') {
					return '<a href="' + escapeHtml(message.content) + '" target="_blank" rel="noopener noreferrer">دریافت فایل ضمیمه</a>';
				}
				return escapeHtml(message.content).replace(/\n/g, '<br>');
			}

			function senderMeta(message) {
				if (message.sender === 'admin') {
					return { className: 'bubble-admin', label: message.sender_name || 'پشتیبان' };
				}
				if (message.sender === 'ai') {
					return { className: 'bubble-ai', label: 'دستیار هوشمند' };
				}
				if (message.sender === 'system') {
					return { className: 'bubble-system', label: 'سیستم' };
				}
				return { className: 'bubble-user', label: 'کاربر' };
			}

			function updateStatusBadge(status) {
				var labels = { open: 'باز', closed: 'بسته‌شده', blocked: 'مسدود' };
				var classes = { open: 'badge-open', closed: 'badge-closed', blocked: 'badge-blocked' };

				$('.poshtibank-header .poshtibank-status-badge')
					.removeClass('badge-open badge-closed badge-blocked')
					.addClass(classes[status] || 'badge-open')
					.text(labels[status] || labels.open);
			}

			function isNearBottom() {
				var el = $stream[0];
				return (el.scrollHeight - el.scrollTop - el.clientHeight) < 90;
			}

			function scrollToBottom() {
				$stream.scrollTop($stream[0].scrollHeight);
			}

			function appendMessage(message) {
				if (message.message_id && $stream.find('[data-message-id="' + message.message_id + '"]').length) {
					return;
				}

				var meta = senderMeta(message);
				var bubble = $('<div class="poshtibank-chat-bubble"></div>')
					.addClass(meta.className)
					.attr('data-message-id', message.message_id || '');

				$('<span class="bubble-sender"></span>').text(meta.label).appendTo(bubble);
				$('<div class="bubble-content"></div>').html(contentHtml(message)).appendTo(bubble);
				$('<span class="bubble-time" dir="ltr"></span>').text(message.time || '').appendTo(bubble);

				$stream.find('.poshtibank-chat-empty').remove();
				$stream.append(bubble);

				if (message.id && message.id > lastId) {
					lastId = message.id;
				}
			}

			function poll() {
				if (document.hidden) {
					return;
				}

				$.post(poshtibankAdmin.ajaxUrl, {
					action: 'poshtibank_poll_conversation',
					nonce: poshtibankAdmin.nonce,
					session_id: sessionId,
					after_id: lastId
				}, function (res) {
					if (!res.success || !res.data.messages || !res.data.messages.length) {
						return;
					}

					if (res.data.status) {
						updateStatusBadge(res.data.status);
					}

					var stick = isNearBottom();

					$.each(res.data.messages, function (index, message) {
						appendMessage(message);
					});

					if (stick) {
						scrollToBottom();
					}
				}, 'json');
			}

			function sendReply() {
				if (!$reply.length || sending) {
					return;
				}

				var text = $.trim($reply.val());

				if (!text) {
					return;
				}

				sending = true;
				$send.prop('disabled', true);
				$error.hide().text('');

				$.post(poshtibankAdmin.ajaxUrl, {
					action: 'poshtibank_send_admin_reply',
					nonce: poshtibankAdmin.nonce,
					session_id: sessionId,
					message: text
				}, function (res) {
					sending = false;
					$send.prop('disabled', false);

					if (!res.success) {
						$error.text((res.data && res.data.message) || poshtibankAdmin.i18n.error).show();
						return;
					}

					$reply.val('').css('height', 'auto').trigger('focus');
					appendMessage(res.data.message);

					if (res.data.status) {
						updateStatusBadge(res.data.status);
					}

					scrollToBottom();
				}, 'json').fail(function () {
					sending = false;
					$send.prop('disabled', false);
					$error.text(poshtibankAdmin.i18n.error).show();
				});
			}

			if ($reply.length && $send.length) {
				$send.on('click', function (e) {
					e.preventDefault();
					sendReply();
				});

				$reply.on('keydown', function (e) {
					if (e.key === 'Enter' && !e.shiftKey) {
						e.preventDefault();
						sendReply();
					}
				});

				$reply.on('input', function () {
					this.style.height = 'auto';
					this.style.height = Math.min(this.scrollHeight, 140) + 'px';
				});
			}

			scrollToBottom();
			window.setInterval(poll, 5000);
		})();

	});
})(jQuery);
