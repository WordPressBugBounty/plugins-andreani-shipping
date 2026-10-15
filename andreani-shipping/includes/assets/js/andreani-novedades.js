(function ($) {
	'use strict';

	function dismiss($notice) {
		$.post(AndreaniNovedades.ajax_url, {
			action: 'andreani_novedad_vista',
			nonce: AndreaniNovedades.nonce,
			id: $notice.attr('data-novedad')
		});
	}

	$(document).on('click', '.andr-novedad .notice-dismiss', function () {
		dismiss($(this).closest('.andr-novedad'));
	});

	$(document).on('click', '.andr-novedad [data-novedad-later]', function () {
		var $notice = $(this).closest('.andr-novedad');

		dismiss($notice);
		$notice.fadeOut(150, function () {
			$notice.remove();
		});
	});
})(jQuery);
