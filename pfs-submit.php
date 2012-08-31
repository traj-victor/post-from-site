<?php 
/* * *
 * Processed form data into a proper post array, uses wp_insert_post() to add post. 
 * 
 * @param array $pfs_data POSTed array of data from the form
 */
require('../../../wp-load.php');
error_reporting(E_ALL);
ini_set('display_errors',1);

/**
 * Create post from form data, including uploading images
 * @param array $post
 * @param array $files
 * @return string success or error message.
 */
function pfs_submit($post,$files){
	$pfs_options_arr = get_option('pfs_options');
	$pfs_options = $pfs_options_arr[0];
	$pfs_data = $post;
	$pfs_files = $files;
	
    $title = $pfs_data['title'];
    
	/* Forcar que todo post inserido seja indexado a categoria DOT Virtual*/
	$category = $pfs_data['cat'];
    $postcontent = $pfs_data['postcontent'];
    
    $name = (array_key_exists('name',$pfs_data)) ? esc_html($pfs_data['name'],array()) : '';
    $email = (array_key_exists('email',$pfs_data)) ? sanitize_email($pfs_data['email']) : '';
    
	$result = Array(
		'file-upload'=>"",
		'error'=>"",
		'success'=>"",
		'post'=>""
	);
	$success = False;
	$upload = False;

	// Se o usuario atual pode publicar post
    if ( current_user_can('publish_posts') ) {
    	if (array_key_exists('file-upload',$pfs_files)) {
    		 
	   		// Se o arquivo existir, iniciar processamento do mesmo para upload
    		switch (True) {
    			case (1 < count($pfs_files['file-upload']['name'])):
    				 
    				// Caso o numero de arquivos no Array submetido pelo form seja maior que 1,
    				// Upload de multiplos arquivos em qualquer formato.
    				$result['file-upload'] = "multiple";
    				$file = $pfs_files['file-upload'];
    	
    				for ( $i = 0; $i < count($file['tmp_name']); $i++ ){
    					if( '' != $file['tmp_name'][$i] ){
    						$upload[$i+1] = upload_file(array('name'=>$pfs_files["file-upload"]["name"][$i], 'tmp_name'=>$pfs_files["file-upload"]["tmp_name"][$i]));
    						if (False === $upload[$i+1]){
    							$result['error'] = __("Erro ao realizar upload do arquivo.",'pfs_domain');
    						} else {
    							$success[$i+1] = True;
    						}
    	
    					}
    				}
    				break;
    			case ((1 == count($pfs_files['file-upload']['name'])) && ('' != $pfs_files['file-upload']['name'][0]) ):
    				
    				$postarr = array();
    				$postarr['post_title'] = $title;
    				$postarr['comment_status'] = $pfs_options['comment_status'];
    				$postarr['post_status'] = $pfs_options['post_status'];
    				$postarr['post_author'] = ( is_user_logged_in() ) ? $user_ID : $pfs_options['default_author'];
    				$postarr['tax_input'] = (array_key_exists('terms',$pfs_data)) ? $pfs_data['terms'] : array();
    				$postarr['post_type'] = $pfs_options['post_type'];
    				$postarr['post_category'] = array($category);						#Neste momento, o post é associado a categoria DOTVirtual
    				 
    				$post_id = wp_insert_post($postarr);
    	
    				// Caso o numero de arquivos no Array submetido pelo form seja 1,
    				// Processar upload de um unico arquivo em qualquer formato.
    				$file = $pfs_files['file-upload'];
    				$result['file-upload'] = 'single';
    				
    				$fileToUpload = array( 'name'=>$file["name"][0], 'tmp_name'=>$file["tmp_name"][0] );
    				$uploaded = wp_upload_bits( $fileToUpload["name"], null, file_get_contents($fileToUpload["tmp_name"]));
    				
    				if (false === $uploaded['error']) {
    					$wp_filetype = wp_check_filetype(basename($uploaded['file']), null );
    					$attachment = array(
    							'post_mime_type' => $wp_filetype['type'],
    							'post_title' => preg_replace('/\.[^.]+$/', '', basename($uploaded['file'])),
    							'post_content' => '',
    							'post_status' => 'inherit',
    							'post_parent' => $post_id
    					);
    					
    					$attach_id = wp_insert_attachment( $attachment, $uploaded['file'], $post_id);
    				
    					require_once(ABSPATH . "wp-admin" . '/includes/file.php');
    					require_once(ABSPATH . 'wp-admin/includes/image.php');
    				
    					$attach_data = wp_generate_attachment_metadata( $attach_id, $uploaded['file'] );
    				
    					wp_update_attachment_metadata( $attach_id,  $attach_data );
    				
    					$upload[1] = $attach_id;
    				} else {
    					//TODO: er, error handling?
    					return false;
    				}
    	
    				if (False === $upload[1]){
    					$result['error'] = __("Houve um erro ao realizar upload do arquivo.",'pfs_domain');
    				} else {
    					$success[1] = True;
    				}
    	
    				break;
    			default:
    				$result['file-upload'] = 'none';
    		}
    	}
    	if ( '' != $result['error'] ) return $result; // Se houver erro no upload do arquivo.
    	
    	/* manipulate $pfs_data into proper post array */
    	$has_content_things = ($title != '') && ($postcontent != '');
    	if ( $has_content_things ) {
    		$content = $postcontent;
    		
    		if ( is_user_logged_in() ){
    			global $user_ID;
    			get_currentuserinfo();
    		}
    		
    		if (is_array($success)){
    			foreach(array_keys($success) as $i){
    				$imgtag = "[!--image$i--]";
    				if (False === strpos($content,$imgtag)) $content .= "\n\n$imgtag";
    				$content = str_replace($imgtag, wp_get_attachment_link( $upload[$i], $pfs_options['wp_image_size']), $content);
    			}
    		}
    		
    		//if any [!--image#--] tags remain, they are invalid and should just be deleted.
    		$content = preg_replace('/\[\!--image\d*--\]/','',$content);
    	
    		// $terms[{tax name}] = array(term1, term2, etc)
    		if ( array_key_exists('terms',$pfs_data) ) {
    			foreach ($pfs_data['terms'] as $taxon => $terms){
    				if ( !is_taxonomy_hierarchical($taxon) ) {
    					$pfs_data['terms'][$taxon] = implode(',',$terms);
    				}
    			}
    		}
    		
    		/*
    		 * Method to update the post with the correct upload.
    		 * e.g: We first insert the post, then after upload the files
    		 * In this context, we are not allowed to see the 'content' of the post, wich is the 'file we uploaded'
    		 */
    		
    		$my_post = array();
    		$my_post['ID'] = $post_id;
    		$my_post['post_content'] = $content . "<br/>"  . $post_id->post_content ;
    		
    		// Update the post into the database
    		wp_update_post( $my_post );
    		unset($my_post);
	    		
    		if (0 == $post_id) {
    			$result['error'] = __("Não foi possível inserir o post, erro desconhecido",'pfs_domain');
    		} else {
    			$result['success'] = __("Post inserido.",'pfs_domain');
    			$result['post'] = $post_id;
    		}
    	} else {
    		$result['error'] = __("You've left a field empty. All fields are required",'pfs_domain');
    	}
        
    } 
	return $result;
}

/*
 * Method to upload any filetype.
 * @author: Victor Kurauchi
 * @since: 23/08/2012
 */
function upload_file($fileToUpload) {

	$file = wp_upload_bits( $fileToUpload["name"], null, file_get_contents($fileToUpload["tmp_name"]));

	if (false === $file['error']) {
		$wp_filetype = wp_check_filetype(basename($file['file']), null );
		$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => preg_replace('/\.[^.]+$/', '', basename($file['file'])),
				'post_content' => '',
				'post_status' => 'inherit',
				'post_parent' => $post->ID
		);
		$attach_id = wp_insert_attachment( $attachment, $file['file']);

		require_once(ABSPATH . "wp-admin" . '/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		$attach_key = 'document_file_id';
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file['file'] );

		wp_update_attachment_metadata( $attach_id,  $attach_data );

		return $attach_id;
	} else {
		//TODO: er, error handling?
		return false;
	}

}

if (!empty($_POST)){
	$pfs = pfs_submit($_POST,$_FILES);
	echo json_encode($pfs);

	wp_redirect("http://localhost/wdot/wordpress/?page_id=77");
	exit;
} else {
	/* TODO: translate following */
	_e('You should not be seeing this page, something went wrong.','pfs_domain');
	echo "<a href='".get_bloginfo('url')."'>" . __('Go home?','pfs_domain') . "</a>";
}

//get_footer();
?>
