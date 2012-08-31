<?php 
/*
 Template Name: Canal do Usu&aacute;rio
*/
get_header(); ?>

<div id="primary-channel">
	<div id="content-channel" role="main">	
		
		<?php 
		$pfs_options = $pfs_options_arr[0];
		
		if ( is_user_logged_in() ) {
			
			wp_get_current_user();
			$theauthorid = $current_user->ID;
			echo 'User ID: ' . $theauthorid . '<br />';
			echo 'Usu&aacute;rio: ' . $current_user->user_firstname . '<br />';
			echo 'Username: ' . $current_user->user_login . '<br />';
			
			// Query		
			query_posts( 'author=' . $theauthorid );
			
			?>
			<h1>Seus posts: </h1>
			
			<div id="post-byuser">
			<?php		
			if (!have_posts()) {
				?> <h2>Voce ainda n&atilde;o tem nenhum post! <a href="http://localhost/wdot/wordpress/?page_id=6">Clique aqui para criar um!</a></h2><?php
			} else { 			
			
				while ( have_posts() ) : the_post(); ?>			
					<div class="single">
					<?php  
					global $post;
					echo $post->ID;
					
					$files = array(
							'numberposts' => -1,
							'post_parent' => $post->ID, 
							'post_status' => 'inherit', 
							'post_type' => 'attachment', 
							'order' => 'ASC', 
							'orderby' => 'menu_order ID'
							 );
					$attachments = get_children($files);
					
					foreach ( $attachments as $attachment ) {
						//echo wp_get_attachment_link( $attachment->ID, 'thumbnail', true );
						echo wp_get_attachment_link($attachment->ID, 'medium');
					}
					
					?>
					<hr>
					</div>
				<?php endwhile; // end of the loop. 
				
				// Reset Query
				wp_reset_query();?>
				
				
				<?php 
			}
			?>
			
			</div>
			<!-- #post-byuser -->
			<h2 style="text-align: center"><a href="http://localhost/wdot/wordpress/?page_id=6">Fa&ccedil;a novos uploads</a></h2>	
			
			<?php 
		} else {
			echo "VOCE NAO TEM ACESSO";
		}
		
		?>

	</div>
	<!-- #content-channel -->
</div>
<!-- #primary-channel -->
<?php get_footer();?>