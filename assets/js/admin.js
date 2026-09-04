/**
 * Fuel Price Malaysia - Admin JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// 1. Toggle Day-of-Week visibility based on frequency
		var $freqSelect = $('#fuel_price_frequency');
		var $dowRow     = $('#row-day-of-week');

		function toggleDowRow() {
			if ($freqSelect.val() === 'weekly') {
				$dowRow.slideDown(200);
			} else {
				$dowRow.slideUp(200);
			}
		}

		$freqSelect.on('change', toggleDowRow);

		// 2. Click-to-copy shortcodes
		$(document).on('click', '.btn-copy', function(e) {
			e.preventDefault();
			var $btn  = $(this);
			var text  = $btn.data('copy') || $btn.siblings('code').text();

			if (!text) {
				return;
			}

			function onSuccess() {
				var originalText = $btn.text();
				$btn.text(fuelPriceAdmin.strings.copied || 'Copied!').addClass('copied');
				setTimeout(function() {
					$btn.text(originalText).removeClass('copied');
				}, 1500);
			}

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(onSuccess).catch(function() {
					fallbackCopy(text, onSuccess);
				});
			} else {
				fallbackCopy(text, onSuccess);
			}
		});

		function fallbackCopy(text, callback) {
			var textArea = document.createElement('textarea');
			textArea.value = text;
			textArea.style.top = '0';
			textArea.style.left = '0';
			textArea.style.position = 'fixed';
			document.body.appendChild(textArea);
			textArea.focus();
			textArea.select();
			try {
				document.execCommand('copy');
				callback();
			} catch (err) {
				console.error('Fallback copy failed', err);
			}
			document.body.removeChild(textArea);
		}

		// 3. AJAX Manual Sync Button
		var $syncBtn = $('#btn-ajax-sync');
		var $notice  = $('#fuel-price-ajax-notice');

		$syncBtn.on('click', function(e) {
			e.preventDefault();

			var $icon = $syncBtn.find('.dashicons');
			var $text = $syncBtn.find('.btn-text');
			var origText = $text.text();

			$syncBtn.prop('disabled', true);
			$icon.addClass('spin');
			$text.text(fuelPriceAdmin.strings.syncing || 'Syncing...');
			$notice.hide().removeClass('notice-success notice-error notice-info');

			$.ajax({
				url: fuelPriceAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'fuel_price_manual_sync',
					nonce: fuelPriceAdmin.syncNonce
				},
				success: function(response) {
					if (response.success && response.data) {
						var d = response.data.data;

						// Update prices on screen
						if (d.ron95 !== null && d.ron95 !== undefined) {
							$('#val-ron95').html('<span class="unit">RM</span> ' + parseFloat(d.ron95).toFixed(2));
						}
						if (d.ron97 !== null && d.ron97 !== undefined) {
							$('#val-ron97').html('<span class="unit">RM</span> ' + parseFloat(d.ron97).toFixed(2));
						}
						if (d.diesel !== null && d.diesel !== undefined) {
							$('#val-diesel').html('<span class="unit">RM</span> ' + parseFloat(d.diesel).toFixed(2));
						}
						if (d.diesel_eastmsia !== null && d.diesel_eastmsia !== undefined) {
							$('#val-diesel-east').html('<span class="unit">RM</span> ' + parseFloat(d.diesel_eastmsia).toFixed(2));
						}

						// Update changes
						if (d.changes) {
							updateDiffBadge('#chg-ron95', d.changes.ron95);
							updateDiffBadge('#chg-ron97', d.changes.ron97);
							updateDiffBadge('#chg-diesel', d.changes.diesel);
							updateDiffBadge('#chg-diesel-east', d.changes.diesel_eastmsia);
						}

						// Update effective date pill if exists
						if (d.date) {
							$('.pill-date').html('Effective Date: <strong>' + d.date + '</strong>');
						}

						// Update last sync label
						if (response.data.lastUpdated) {
							$('.last-sync-meta').text('Last Sync: ' + response.data.lastUpdated);
						}

						// Update next run info
						if (response.data.nextRun && response.data.nextRun.human_time) {
							$('#next-run-human').text(response.data.nextRun.human_time);
							if (response.data.nextRun.time_diff) {
								$('#next-run-diff').text('(in ' + response.data.nextRun.time_diff + ')');
							}
						}

						// Show success banner
						$notice.addClass('notice-success').find('p').text(response.data.message || fuelPriceAdmin.strings.syncSuccess);
						$notice.slideDown(200);

					} else {
						var err = (response.data && response.data.message) ? response.data.message : fuelPriceAdmin.strings.syncFailed;
						$notice.addClass('notice-error').find('p').text(err);
						$notice.slideDown(200);
					}
				},
				error: function(xhr, status, error) {
					$notice.addClass('notice-error').find('p').text('Network or server error during sync: ' + error);
					$notice.slideDown(200);
				},
				complete: function() {
					$syncBtn.prop('disabled', false);
					$icon.removeClass('spin');
					$text.text(origText);
				}
			});
		});

		function updateDiffBadge(selector, val) {
			var diff = parseFloat(val) || 0;
			var $el = $(selector);
			var html = '';

			if (diff > 0) {
				html = '<span class="diff-badge diff-up">▲ +RM ' + Math.abs(diff).toFixed(2) + '</span>';
			} else if (diff < 0) {
				html = '<span class="diff-badge diff-down">▼ -RM ' + Math.abs(diff).toFixed(2) + '</span>';
			} else {
				html = '<span class="diff-badge diff-neutral">━ Unchanged</span>';
			}

			$el.html(html);
		}

	});

})(jQuery);
