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

		// Live Chats: View History Modal
		$('.btn-view-chat').on('click', function () {
			var sessionId = $(this).data('session-id');
			var $modal = $('#poshtibank-chat-modal');
			var $body = $('#modal-chat-messages');

			$modal.fadeIn(200);
			$body.html('<p style="text-align:center;">در حال بارگذاری گفتگو...</p>');

			$.post(poshtibankAdmin.ajaxUrl, {
				action: 'poshtibank_get_session_messages',
				nonce: poshtibankAdmin.nonce,
				session_id: sessionId
			}, function (res) {
				if (res.success && res.data.messages) {
					if (res.data.messages.length === 0) {
						$body.html('<p style="text-align:center; color:#94a3b8;">هنوز پیامی در این گفتگو رد و بدل نشده است.</p>');
						return;
					}

					var html = '';
					$.each(res.data.messages, function (idx, msg) {
						var bubbleClass = 'bubble-user';
						var senderLabel = 'کاربر';

						if (msg.sender === 'admin') {
							bubbleClass = 'bubble-admin';
							senderLabel = 'پشتیبان';
						} else if (msg.sender === 'ai') {
							bubbleClass = 'bubble-ai';
							senderLabel = 'هوش مصنوعی';
						}

						html += '<div class="poshtibank-chat-bubble ' + bubbleClass + '">';
						html += '<strong>' + senderLabel + ':</strong> ' + msg.content;
						html += '<span class="bubble-time" dir="ltr">' + msg.created_at + '</span>';
						html += '</div>';
					});

					$body.html(html);
				} else {
					$body.html('<p style="color:#dc2626;">خطا در دریافت پیام‌ها.</p>');
				}
			}, 'json');
		});

		// Live Chats: Block Session
		$('.btn-block-chat').on('click', function () {
			if (!confirm(poshtibankAdmin.i18n.confirmBlock)) {
				return;
			}
			var sessionId = $(this).data('session-id');
			var $row = $('#session-row-' + sessionId);

			$.post(poshtibankAdmin.ajaxUrl, {
				action: 'poshtibank_update_session_status',
				nonce: poshtibankAdmin.nonce,
				session_id: sessionId,
				status: 'blocked'
			}, function (res) {
				if (res.success) {
					$row.find('.poshtibank-status-badge').removeClass('badge-open badge-closed').addClass('badge-blocked').text('بلاک');
				} else {
					alert(res.data.message || poshtibankAdmin.i18n.error);
				}
			}, 'json');
		});

		// Modal Close
		$('.poshtibank-modal-close, .poshtibank-modal-overlay').on('click', function () {
			$('#poshtibank-chat-modal').fadeOut(200);
		});
	});
})(jQuery);
