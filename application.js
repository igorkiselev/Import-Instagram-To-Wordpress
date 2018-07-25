/* Ajax function for updating */
(function($) {
	$(document).ready(function() {

		$('#ajax_instagram_update').click(function(e) {

			$b = $(this);

			$b.addClass('ajax_loader');
			$.post(ajaxurl, { 'action': 'instagram_update' }, function( e ){
				$b.removeClass('ajax_loader');
			});
			e.preventDefault();
		});
		
		$('#ajax_instagram_preview').click(function(e) {

			$c = $(this);

			$c.addClass('ajax_loader');
			
			$.post(ajaxurl, { 'action': 'instagram_preview' }, function( e ){
				console.log(e);
				$('#instagram_preview').addClass('loaded');
				$('#instagram_preview pre').html(e);
				$c.removeClass('ajax_loader');
			});
			
			e.preventDefault();
		});

	});
})(jQuery);