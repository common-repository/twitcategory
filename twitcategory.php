<?php
/*
Plugin Name: TwitCategory
Plugin URI: http://gabriel.nagmay.com/2009/10/twitcategory/
Description: Announces new posts (from a selected category) on twitter. 
Version: 0.1.9
Author: Gabriel Nagmay
Author URI: http://gabriel.nagmay.com
*/

/*
	Where credit is due:
	
	This plugin is a updated/modified version of Twitpress by Thomas Purnell  (email : tom@thomaspurnell.com)
	Twitpress v0.3.2 - http://wordpress.org/extend/plugins/twitpress/
	We should all thank Thomas fro creating the great plugin and releasing it under a GPLlicense!
*/

/*  Copyright 2009  Gabriel Nagmay (email : gabriel@nagmay.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  
    02110-1301  USA
*/

//Hooks and wordpress options
register_activation_hook( __FILE__, 'twitcategory_install' );
add_action( 'delete_post', 'twitcategory_db_delete_post' );
add_action( 'admin_menu', 'twitcategory_add_menu' );
add_action( 'wp_insert_post', 'twitcategory_run' );

add_option( 'twitcategory_uid', '', '', 'yes' );
add_option( 'twitcategory_password', '', '', 'yes' );
add_option( 'twitcategory_category', '0', '', 'yes' );
add_option( 'twitcategory_message', 'New blog entry: [title] [permalink] [guid] [category]', '', 'yes' );

//Runs when a post record is inserted into the database
function twitcategory_run( $postID ) {
	//get the post
	$post = get_post( $postID );

	//we only want to do anything if the post was not previously twittered
	if ( !twitcategory_was_twittered( $postID ) ){
		//Update the post to reflect it's current status
		twitcategory_db_update_post( $postID, $post->post_status );
	}
	//process the posts, including twittering newly published posts
	twitcategory_process_posts();
}

//Install the twitcategory database
function twitcategory_install() {

	global $wpdb;
	$table_name = "twitcategory";
	
	twitcategory_db_drop_table();

   	if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE " . $table_name . " (
	 	 id mediumint(9) NOT NULL,
	 	 status enum( 'publish', 'draft', 'private', 'static', 'object', 'attachment', 'inherit', 'future', 'twittered' ) NOT NULL,
	 	 UNIQUE KEY id (id)
		);";

		if (version_compare($wp_version, '2.3', '>='))		
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		else
			require_once(ABSPATH . 'wp-admin/upgrade-functions.php');

		dbDelta( $sql );

		add_option( "twitcategory_db_version", "0.3.2" );
	}

	//Populate table to avoid twittering previously published posts when they are edited
	$sql = "SELECT ID, post_status from " . $wpdb->posts;
	$posts = $wpdb->get_results( $sql, OBJECT );
	foreach ($posts as $post){
		if ( $post->post_status == "publish" ){
			twitcategory_db_update_post( $post->ID, "twittered" );
		} else {
			twitcategory_db_update_post( $post->ID, $post->post_status );
		}
	}
}

//Delete twitcategory table
function twitcategory_db_drop_table() {
	global $wpdb;
	$table_name = "twitcategory";
   	if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "DROP TABLE " . $table_name;
	}

	if (version_compare($wp_version, '2.3', '>='))		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	else
		require_once(ABSPATH . 'wp-admin/upgrade-functions.php');

	dbDelta( $sql );
}

//Add / update a twitcategory record of a post
function twitcategory_db_update_post( $postID, $status ){
	global $wpdb;
	$table_name = "twitcategory";

	//delete any old record of this entry (could use update instead)
	twitcategory_db_delete_post( $postID );

	//insert the new version of the record
	$query = "INSERT INTO " . $table_name .
		" (id, status) " .
		"VALUES (" . $wpdb->escape( $postID ) . ", '" .
		$wpdb->escape( $status ) . "')";
	return $wpdb->query( $query );
}

//Get a wordpress post status
function twitcategory_db_get_post_status( $postID ){
	global $wpdb;
	$table_name = "twitcategory";

	//find post by id
	$query = "SELECT id, status from " . $wpdb->posts .
		" WHERE id = " . $wpdb->escape( $postID );
	return $wpdb->get_var( $query, 1 );
}

//Check if a post was previously twittered
function twitcategory_was_twittered( $postID ){
	global $wpdb;
	$table_name = "twitcategory";
	
	$query = "SELECT id, status from " . $table_name . 
		" WHERE id = " . $wpdb->escape( $postID );

	return $wpdb->get_var( $query, 1 ) == 'twittered'; 
}

//Delete a twitcategory post status (n.b this doesnt delete any wordpress posts :) )
function twitcategory_db_delete_post( $postID ){
	global $wpdb;
	$table_name = "twitcategory";

	$query = "DELETE FROM " . $table_name .
		" WHERE id like " . $wpdb->escape( $postID );
	return $wpdb->query( $query );
}

//Process posts currently stored in the twitcategory database table
function twitcategory_process_posts() {
	global $wpdb;
	$table_name = "twitcategory";

	$query = "SELECT * FROM " . $table_name;

	//get the currently stored posts
	$tp_rows = $wpdb->get_results( $query, ARRAY_A );

	//for each entry in the twitcategory table
	foreach( $tp_rows as $row ){
		$tp_wp_status = $row["status"]; 
		//if the entry has been published, we can publish to twitter and mark
		//that this post has been twittered
		if ( $tp_wp_status == "publish" ){
			twitcategory_publish( $row["id"] );
			twitcategory_db_update_post( $row["id"], 'twittered' );
		}
	}
}

//Add the twitcategory menu to the management page
function twitcategory_add_menu() {
	add_management_page( 'TwitCategory', 'TwitCategory', 8, 'twitcategoryoptions', 'twitcategory_options_page' );
}

//Options page code and html
function twitcategory_options_page() {
	global $wpdb;
	$table_name = "twitcategory";
	$username = get_option( 'twitcategory_uid' );
	$password = get_option( 'twitcategory_password' );
	$categories = get_option( 'twitcategory_category' );
	$message = get_option( 'twitcategory_message' );
	$submitFieldID = 'twitcategory_submit_hidden';
	if ( $_POST[ $submitFieldID ] == 'Y' ) {
		update_option( 'twitcategory_uid', $_POST[ 'twitcategory_form_username' ] );
		update_option( 'twitcategory_password', str_rot13($_POST[ 'twitcategory_form_password' ]) );
		update_option( 'twitcategory_category', $_POST[ 'twitcategory_form_category' ] );
		update_option( 'twitcategory_message', $_POST[ 'twitcategory_form_message' ] );
	?>
		<div class="updated"><p><strong><?php _e('Options saved.', 'mt_trans_domain' ); ?></strong></p></div>
	<?php

	}

	echo '<div class="wrap">';
	echo "<h2>" . __( 'TwitCategory Plugin Options', 'mt_trans_domain' ) . "</h2>"; ?>

	<form name="twitcategory_form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI'] ); ?>">
	<input type="hidden" name="twitcategory_submit_hidden" value="Y" />
	
    <table class="form-table">
    <tr>
    	<th>Twitter Username:</th>
      	<td><input type="text" name="twitcategory_form_username" value="<?php echo ( get_option ( 'twitcategory_uid' ) ); ?>" /></td>
    </tr> 
    <tr>
    	<th>Twitter Password:</th>
      	<td><input type="password" name="twitcategory_form_password" value="<?php echo str_rot13( get_option( 'twitcategory_password' ) ); ?>" /></td>
    </tr> 
    <tr>
    	<th>Only Tweet From:</th>
      	<td><?php  /* display category list */
				$args = array(
				'show_option_all'    => 'Tweet All Categories',
				'name'               => 'twitcategory_form_category',
				'class'              => 'postform',
				'hide_empty'		 =>	0,
				'hierarchical'  	 => 1,
				'selected'			 => get_option( 'twitcategory_category' ),
				'depth'              => 0 ); 
				wp_dropdown_categories( $args ); ?> 
    </td>
    </tr> 
    <tr>
    	<th>Format the Message:</th>
      	<td><textarea name="twitcategory_form_message" style="width:70%;" /><?php echo (get_option('twitcategory_message')); ?></textarea></td>
    </tr> 
     <tr>
    	<th></th>
      	<td><input type="submit" name="Submit" value="<?php _e( 'Update Options', 'mt_trans_domain' ) ?>" /></td>
    </tr>
    </table>
	<h3>Formatting:</h3>
    <p>The following message format tags are supported:</p>
    <ul>
        <li><strong>[title]</strong> is replaced with the post title.</li>
        <li><strong>[category]</strong> is replaced with the selected category name. Blank if "All Categories" is selected.</li>
        <li><strong>[post]</strong> replaces any remaining space with text from the post.</li>
        <li><strong>[permalink]</strong> is replaced with the post's permalink using your permalink format.</li>
        <li><strong>[guid]</strong> is replaced with the post's default URL (www.yourblog.com/?p=123). This is often shorter then the permalink.</li>
        <li><strong>[tinyurl]</strong> is replaced with a short URL from <a href="http://tinyurl.com">tinyurl.com</a>. </li>
        <li><strong>[isgd]</strong> is replaced with a short URL from <a href="http://is.gd">is.gd</a>. </li>
        <li><strong>[bitly:username:apikey]</strong> is replaced with a short URL from <a href="http://bit.ly">bit.ly</a>. Note that bitly requires a username and api key.</li>


    </ul>
    
	</form><!--[Coming Soon]
	<hr />
	<p>twitcategory stores it's data in a table ( '<? echo $table_name ?>' on this wordpress installation ) in your wordpress database. If you want to delete this table (and cause twitcategory to stop working in the process, click here. There is no undo. You will need to deactivate and then activate twitcategory again, regenerating this table, before twitcategory will work again.</p>
	<form action=" <? echo __FILE__ ?> ">
		<p class="submit"><input type="submit" value="Drop table" /></p>
	</form>
-->
	</div>



	<?php
}

//Publishes a tweet given only the postID
function twitcategory_publish( $postID ){	
	//get the post
	$post = get_post( $postID );
	
	//Now redundant check to make sure the post is the correct category and has been published

	if ( !get_option('twitcategory_category') && $post->post_status == "publish" ){ // All Categories selected
		$message = twitcategory_get_message( $postID );
		twitcategory_postMessage( get_option( 'twitcategory_uid' ), get_option( 'twitcategory_password' ), $message );
	}
	else if($post->post_status == "publish" ){
		$categories = get_the_category($post->ID);
		foreach($categories as $category){
			if($category->cat_ID == get_option('twitcategory_category')){	// Match Category
				$message = twitcategory_get_message( $postID );
				twitcategory_postMessage( get_option( 'twitcategory_uid' ), get_option( 'twitcategory_password' ), $message );
			}
		}
	}
}

//Converts a wordpress post into a string with user formatting for twitter posting
function twitcategory_get_message( $postID ){
	$proto = get_option( 'twitcategory_message' );
	$post = get_post( $postID );
	$proto = str_replace( "[title]", $post->post_title, $proto );
	$proto = str_replace( "[guid]", $post->guid, $proto );
	$proto = str_replace( "[permalink]", get_permalink($post->ID), $proto );
	$proto = str_replace( "[tinyurl]", get_tiny_url(get_permalink($post->ID)), $proto );
	$proto = str_replace( "[isgd]", get_isgd_url(get_permalink($post->ID)), $proto );
	
	// bitly is a bit more complex
	$bitly_pattern = "/\[bitly:(?P<user>[^:]+):(?P<key>[^\]]+)\]/";
	if(preg_match($bitly_pattern, $proto, $bitly_info)){
		$proto = preg_replace($bitly_pattern,make_bitly_url(get_permalink($post->ID),$bitly_info["user"],$bitly_info["key"],'json'), $proto); 
	}
	
	// as is category
	if(get_option('twitcategory_category')){
		$proto = str_replace( "[category]", get_cat_name(get_option( 'twitcategory_category' )), $proto );
	}
	else{
		$proto = str_replace( "[category]", "", $proto );
	}
	
	// now, fill remaining chars with post
	$post_pos = strpos( $proto, "[post]");
	if($post_pos){
		$proto = str_replace( "[post]", "", $proto );
		$twit_len = strlen($proto);
		$proto = substr_replace( $proto, substr($post->post_content,0,(140-$twit_len)), $post_pos, 0);
	}
	
	return $proto;
}

//Standard curl function, handles actual submission of message to twitter
function twitcategory_postMessage( $twitter_username, $twitter_password, $message ){
	$url = 'http://twitter.com/statuses/update.xml';
	$curl_handle = curl_init();
	curl_setopt( $curl_handle, CURLOPT_URL, "$url" );
	curl_setopt( $curl_handle, CURLOPT_CONNECTTIMEOUT, 2 );
	curl_setopt( $curl_handle, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $curl_handle, CURLOPT_POST, 1 );
	curl_setopt( $curl_handle, CURLOPT_POSTFIELDS, "status=$message&source=twitcategory" );
	curl_setopt( $curl_handle, CURLOPT_USERPWD, "$twitter_username:".str_rot13( $twitter_password ) );
	$buffer = curl_exec( $curl_handle );
	curl_close( $curl_handle );
}

//TODO:
//Investigate removal of curl deps, unconfuse various checks occuring (will probably need a rewrite)
//Add drop table functionality. Consider storing only twittered entries in database and filtering non relevant
//items.


//Shorten the URLs - thanks to http://davidwalsh.name/ 
function get_tiny_url($url)  
{  
	$ch = curl_init();  
	$timeout = 5;  
	curl_setopt($ch,CURLOPT_URL,'http://tinyurl.com/api-create.php?url='.$url);  
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);  
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);  
	$data = curl_exec($ch);  
	curl_close($ch);  
	return $data;  
}
function get_isgd_url($url)  
{  
	//get content
	$ch = curl_init();  
	$timeout = 5;  
	curl_setopt($ch,CURLOPT_URL,'http://is.gd/api.php?longurl='.$url);  
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);  
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);  
	$content = curl_exec($ch);  
	curl_close($ch);
	
	//return the data
	return $content;  
}
function make_bitly_url($url,$login,$appkey,$format = 'xml',$version = '2.0.1')
{
	//create the URL
	$bitly = 'http://api.bit.ly/shorten?version='.$version.'&longUrl='.urlencode($url).'&login='.$login.'&apiKey='.$appkey.'&format='.$format;
	
	//get the url
	//could also use cURL here
	$response = file_get_contents($bitly);
	
	//parse depending on desired format
	if(strtolower($format) == 'json')
	{
		$json = @json_decode($response,true);
		return $json['results'][$url]['shortUrl'];
	}
	else //xml
	{
		$xml = simplexml_load_string($response);
		return 'http://bit.ly/'.$xml->results->nodeKeyVal->hash;
	}
}


?>
