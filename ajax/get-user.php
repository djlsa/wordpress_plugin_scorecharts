<?php
	include '../../../../wp-load.php';
	$user_id = wp_get_current_user()->ID;
	echo '{ "user_id": "' . $user_id . '" }';
?>