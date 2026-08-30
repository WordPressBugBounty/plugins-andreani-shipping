/**
 * Andreani Cotizador Widget
 *
 * @package AndreaniPlugin
 */

(function($) {
	'use strict';

	var AndreaniCalc = {
		cacheKey: 'andreani_calc_cache',

		init: function() {
			this.bindEvents();
		},

		escapeHtml: function(str) {
			if (!str) return '';
			var div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		},

		bindEvents: function() {
			$(document).on('mouseenter', '.andreani-calc-bubble', this.handleHover.bind(this));
			$(document).on('click', '.andreani-calc-bubble', this.handleOpen.bind(this));
			$(document).on('click', '.andreani-calc-button', this.handleCalculate.bind(this));
			$(document).on('keypress', '.andreani-calc-postcode', this.handleKeypress.bind(this));
			$(document).on('click', this.handleClickOutside.bind(this));
		},

		handleHover: function(e) {
			var $widget = $(e.target).closest('.andreani-calc-widget');
			this.checkAlignment($widget);
		},

		handleOpen: function(e) {
			e.preventDefault();
			e.stopPropagation();
			var $widget = $(e.target).closest('.andreani-calc-widget');

			this.checkAlignment($widget);
			$widget.addClass('andreani-calc-widget--expanded');

			setTimeout(function() {
				$widget.find('.andreani-calc-postcode').focus();
			}, 350);
		},

		checkAlignment: function($widget) {
			var widgetOffset = $widget.offset();
			var widgetLeft = widgetOffset ? widgetOffset.left : 0;
			var windowWidth = $(window).width();
			var panelWidth = 280;

			if (widgetLeft + panelWidth > windowWidth - 16) {
				$widget.addClass('andreani-calc-widget--align-right');
			} else {
				$widget.removeClass('andreani-calc-widget--align-right');
			}
		},

		handleClickOutside: function(e) {
			var $target = $(e.target);
			var $widget = $('.andreani-calc-widget--expanded');

			if ($widget.length && !$target.closest('.andreani-calc-content').length) {
				$widget.removeClass('andreani-calc-widget--expanded andreani-calc-widget--loading');
			}
		},

		handleKeypress: function(e) {
			if (e.which === 13) {
				e.preventDefault();
				$(e.target).closest('.andreani-calc-content').find('.andreani-calc-button')[0].click();
			}
		},

		handleCalculate: function(e) {
			e.preventDefault();

			var $widget = $(e.target).closest('.andreani-calc-widget');
			var postcode = $widget.find('.andreani-calc-postcode').val().trim();
			var productId = $widget.data('product-id');
			var quantity = this.getQuantity();

			if (!this.validatePostcode(postcode)) {
				this.showError($widget, andreaniCotizador.i18n.invalidPostcode);
				return;
			}

			if (!productId) {
				productId = this.findProductId();
			}

			if (!productId) {
				this.showError($widget, andreaniCotizador.i18n.error);
				return;
			}

			var cached = this.getCache(productId, postcode, quantity);
			if (cached) {
				this.showResults($widget, cached);
				return;
			}

			this.fetchRates($widget, productId, postcode, quantity);
		},

		validatePostcode: function(postcode) {
			if (/^\d{4}$/.test(postcode)) {
				return true;
			}

			if (/^[A-Za-z]\d{4}[A-Za-z]{3}$/.test(postcode)) {
				return true;
			}

			return false;
		},

		getQuantity: function() {
			var $qty = $('input.qty, input[name="quantity"]');
			if ($qty.length) {
				var val = parseInt($qty.val(), 10);
				return val > 0 ? val : 1;
			}
			return 1;
		},

		findProductId: function() {
			var $form = $('form.cart, form.variations_form');
			if ($form.length) {
				var $input = $form.find('input[name="product_id"], input[name="add-to-cart"]');
				if ($input.length) {
					return parseInt($input.val(), 10);
				}

				var action = $form.attr('action');
				if (action) {
					var match = action.match(/add-to-cart=(\d+)/);
					if (match) {
						return parseInt(match[1], 10);
					}
				}
			}

			var $btn = $('button[name="add-to-cart"]');
			if ($btn.length && $btn.val()) {
				return parseInt($btn.val(), 10);
			}

			return 0;
		},

		fetchRates: function($widget, productId, postcode, quantity) {
			var self = this;

			this.showLoading($widget);
			this.clearError($widget);

			$.ajax({
				url: andreaniCotizador.ajaxUrl,
				type: 'POST',
				data: {
					action: 'andreani_cotizar_envio',
					nonce: andreaniCotizador.nonce,
					product_id: productId,
					postcode: postcode,
					quantity: quantity
				},
				success: function(response) {
					self.hideLoading($widget);

					if (response.success && response.data.rates) {
						self.setCache(productId, postcode, quantity, response.data.rates);
						self.showResults($widget, response.data.rates);
					} else {
						var msg = response.data && response.data.message
							? response.data.message
							: andreaniCotizador.i18n.noRates;
						self.showError($widget, msg);
					}
				},
				error: function() {
					self.hideLoading($widget);
					self.showError($widget, andreaniCotizador.i18n.error);
				}
			});
		},

		showResults: function($widget, rates) {
			var $results = $widget.find('.andreani-calc-results');
			$results.empty();

			if (!rates || rates.length === 0) {
				this.showError($widget, andreaniCotizador.i18n.noRates);
				return;
			}

			var $list = $('<ul class="andreani-calc-rates"></ul>');
			var self = this;

			rates.forEach(function(rate) {
				var isFree = rate.cost_raw === 0 || rate.cost_raw === '0';
				var itemClass = 'andreani-calc-rate' + (isFree ? ' andreani-calc-rate--free' : '');
				var costText = isFree ? andreaniCotizador.i18n.free : rate.cost;

				var $item = $('<li class="' + itemClass + '"></li>');
				$item.append($('<span class="andreani-calc-rate-label"></span>').text(rate.label));
				$item.append($('<span class="andreani-calc-rate-cost"></span>').html(costText));
				$list.append($item);
			});

			$results.append($list);
			this.clearError($widget);
		},

		showError: function($widget, message) {
			var $error = $widget.find('.andreani-calc-error');
			$error.text(message).addClass('andreani-calc-error--visible');
			$widget.find('.andreani-calc-results').empty();
		},

		clearError: function($widget) {
			$widget.find('.andreani-calc-error').removeClass('andreani-calc-error--visible').empty();
		},

		showLoading: function($widget) {
			$widget.addClass('andreani-calc-widget--loading');
			$widget.find('.andreani-calc-button').prop('disabled', true);
			$widget.find('.andreani-calc-results').empty();
		},

		hideLoading: function($widget) {
			$widget.removeClass('andreani-calc-widget--loading');
			$widget.find('.andreani-calc-button').prop('disabled', false);
		},

		getCacheKey: function(productId, postcode, quantity) {
			return this.cacheKey + '_' + productId + '_' + postcode + '_' + quantity;
		},

		getCache: function(productId, postcode, quantity) {
			if (typeof sessionStorage === 'undefined') {
				return null;
			}

			var key = this.getCacheKey(productId, postcode, quantity);
			var cached = sessionStorage.getItem(key);

			if (!cached) {
				return null;
			}

			try {
				var data = JSON.parse(cached);
				var now = Date.now();

				if (data.expires && data.expires > now) {
					return data.rates;
				}

				sessionStorage.removeItem(key);
			} catch (e) {
				sessionStorage.removeItem(key);
			}

			return null;
		},

		setCache: function(productId, postcode, quantity, rates) {
			if (typeof sessionStorage === 'undefined') {
				return;
			}

			var key = this.getCacheKey(productId, postcode, quantity);
			var data = {
				rates: rates,
				expires: Date.now() + andreaniCotizador.cacheTime
			};

			try {
				sessionStorage.setItem(key, JSON.stringify(data));
			} catch (e) {}
		}
	};

	$(document).ready(function() {
		AndreaniCalc.init();
	});

})(jQuery);
