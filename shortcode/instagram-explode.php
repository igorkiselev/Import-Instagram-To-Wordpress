<?php
add_shortcode( 'instagram-explode', function($atts){
	
	$args = array(
		'posts_per_page'   => 20,
		'post_type'        => 'instagram',
		'post_status'      => 'publish'
	);
	$instagram = get_posts( $args );
	
	?>
<div class="car_1">
	<? foreach ( $instagram as &$image ) : ?>
		<div class="item">
			<?php
			
				$attached = get_attached_media( '', $image->ID );
				unset($attached[get_post_thumbnail_id( $image->ID )]);
				
				if(count($attached)){?><div class="car_2"><div class="item"><?}
			
				echo get_the_post_thumbnail( $image->ID, 'full', array('style' => 'width:100%; height:auto;') );
			
				if(count($attached)){?></div><?
					
					foreach ( $attached as &$image ) :
						?><div class="item"><?
						echo wp_get_attachment_image( $image->ID, 'full', false , array('style' => 'width:100%; height:auto;') );
						?></div><?
					endforeach;
					?></div><?
				}
				
				
				?>
		</div>
	<? endforeach; ?>
</div>

<script>
jQuery(document).ready(function() {
	jQuery('.car_2').owlCarousel({
		autoplay: true,
		autoplayTimeout: 2000,

		
		
		loop: true,
		items: 1,
	
		navText : ['',''],
		nav:true,
		lazyLoad: true,

	});
	jQuery('.car_1').owlCarousel({

		animateOut: 'fadeOut',
		animateIn: 'fadeIn',
		
		items: 1,
	
		navText : ['',''],
		nav:true,

	});
});
</script>



<?
});
