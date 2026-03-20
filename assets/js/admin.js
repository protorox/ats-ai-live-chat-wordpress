(function ($) {
	'use strict';

	$(function () {
		var $tabButtons = $('.atslc-tab-button');
		var $panels = $('.atslc-settings-panel');

		$tabButtons.on('click', function () {
			var targetId = $(this).data('target');

			$tabButtons.removeClass('is-active');
			$(this).addClass('is-active');

			$panels.removeClass('is-active');
			$('#' + targetId).addClass('is-active');
		});

		$('.atslc-media-field').each(function () {
			var $field = $(this);
			var $input = $('#' + $field.data('target-input'));
			var $preview = $field.find('.atslc-media-preview');

			$field.find('.atslc-media-select').on('click', function (event) {
				event.preventDefault();

				var frame = wp.media({
					title: 'Select agent avatar',
					button: {
						text: 'Use this image'
					},
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();

					$input.val(attachment.id);
					$preview.addClass('has-image').html('<img src="' + attachment.url + '" alt="" />');
				});

				frame.open();
			});

			$field.find('.atslc-media-remove').on('click', function (event) {
				event.preventDefault();
				$input.val('');
				$preview.removeClass('has-image').html('<span>No image selected</span>');
			});
		});
	});
})(jQuery);
