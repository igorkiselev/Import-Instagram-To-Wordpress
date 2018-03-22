<?php

	add_shortcode( 'instagram', function($atts){
	
		$args = array(
			'posts_per_page'   => 15,
			'post_type'        => 'instagram',
			'post_status'      => 'publish'
		);
	
		$instagram = get_posts( $args );
	
	?>
	
	<div class="instagram">
	
	<?php if($instagram): ?>
		
		<div class="carousel" data-items="1" data-loop="1">
			
			<?php foreach ( $instagram as &$image ) : ?>
				
				<div class="item" style="background-color:#000;">
					
					<?php $link = get_post_custom_values('link', $image->ID)[0]; ?> 
					
					<?php
						$size = wp_get_attachment_image_src(
							get_post_thumbnail_id( $image->ID ),
							'full');
						?>
					<?php if($link):?><a href="<?php echo $link; ?>" target="_blank"><?php endif;?>
					<?php
						echo get_the_post_thumbnail(
							$image->ID, 
							'full',
							array(
								'style' => 'width:100%; height:auto; position:absolute;'
							)
						);
						
						?>
				
					<div class="parent bottom">
						
						<div class="child">
						
							<div class="caption p-x-md p-b">
						
								<?php echo $image->post_title; ?>
						
							</div>
						
						</div>
					
					</div>
						<?php if($link):?></a><?php endif;?>
				</div>
				
			<?php endforeach; ?>
			
		</div>
		
	<?php else: _e('No image yet'); endif; ?>

</div>

<?php }); ?>