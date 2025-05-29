<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
// remove these to prevent deprecating warnings
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'wp_admin_bar_header');
remove_action('wp_admin_head', 'wp_admin_bar_header');
?>
<!DOCTYPE html>
<html lang="en" class="latepoint-clean">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>LatePoint</title>
	<?php wp_head(); ?>
</head>
<body class="latepoint-clean-body with-pattern latepoint">
<div class="latepoint-w">
	<?php include($view); ?>
</div>
</body>
</html>