(function (window, $) {
	'use strict';

	var SHOW_DELAY = 200;
	var MIN_VISIBLE = 400;
	var PHRASE_INTERVAL = 1600;
	var KEY = 'andrLoader';
	var SIZES = { lg: [229, 150], sm: [96, 63] };

	function escape(value) {
		return String(value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function gif() {
		return (window.AndreaniLoaderConfig || {}).gif || '';
	}

	function html(options) {
		var opts = options || {};
		var size = opts.size === 'sm' ? 'sm' : 'lg';
		var phrases = opts.phrases || [];
		var text = phrases.length ? phrases[0] : (opts.text || '');

		return '<div class="andr-loader andr-loader--' + size + '" role="status" aria-live="polite">'
			+ '<img class="andr-loader__img" src="' + escape(gif()) + '" alt="" width="' + SIZES[size][0] + '" height="' + SIZES[size][1] + '">'
			+ '<span class="andr-loader__text">' + escape(text) + '</span>'
			+ '</div>';
	}

	function rotate($loader, phrases, state) {
		var index = 0;
		var $text = $loader.find('.andr-loader__text');

		if (phrases.length < 2) {
			return;
		}

		state.interval = setInterval(function () {
			if (!document.body.contains($loader.get(0))) {
				clearInterval(state.interval);
				return;
			}

			index++;
			$text.text(phrases[index]);

			if (index >= phrases.length - 1) {
				clearInterval(state.interval);
			}
		}, PHRASE_INTERVAL);
	}

	function stop(state) {
		clearTimeout(state.timer);
		clearTimeout(state.textTimer);
		clearTimeout(state.finishTimer);
		clearInterval(state.interval);
	}

	function present($container, state) {
		var opts = state.opts;
		var $loader = $container.children('.andr-loader').first();

		state.visible = true;
		state.shownAt = Date.now();

		if (!$loader.length) {
			$container.html(html(opts));
			$loader = $container.children('.andr-loader').first();
		}

		if (opts.textAfter) {
			var $text = $loader.find('.andr-loader__text').prop('hidden', true);

			state.textTimer = setTimeout(function () {
				$text.prop('hidden', false);
			}, opts.textAfter);
		}

		rotate($loader, opts.phrases || [], state);
	}

	function flush($container) {
		var state = $container.data(KEY);

		if (!state) {
			return;
		}

		if (state.finish) {
			state.finish();
		} else {
			stop(state);
		}
	}

	function show($container, options) {
		var opts = options || {};
		var state = { opts: opts, visible: false, shownAt: 0 };

		flush($container);
		$container.data(KEY, state).attr('aria-busy', 'true');

		if ($container.children('.andr-loader').length) {
			present($container, state);
			return;
		}

		state.timer = setTimeout(function () {
			present($container, state);
		}, SHOW_DELAY);
	}

	function hide($container, done) {
		var state = $container.data(KEY);

		if (!state) {
			if (done) {
				done();
			}
			return;
		}

		var wait = state.visible ? Math.max(0, MIN_VISIBLE - (Date.now() - state.shownAt)) : 0;

		state.finish = function () {
			stop(state);
			$container.removeData(KEY).removeAttr('aria-busy');

			if (state.visible) {
				$container.children('.andr-loader').remove();
			}

			if (done) {
				done();
			}
		};

		clearTimeout(state.timer);

		if (wait) {
			state.finishTimer = setTimeout(state.finish, wait);
		} else {
			state.finish();
		}
	}

	function busy(text) {
		var $box = $('<div class="andr-loader-busy"></div>').appendTo(document.body);

		show($box, { size: 'sm', text: text });

		return function () {
			hide($box, function () {
				$box.remove();
			});
		};
	}

	window.AndreaniLoader = { html: html, show: show, hide: hide, busy: busy };
})(window, window.jQuery);
