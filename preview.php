<?php

if (! defined('ABSPATH')) {
    require_once './../../../wp-load.php';
}

$post_id = isset($_GET['post']) ? $_GET['post'] : 0;

$message = get_post_meta($post_id, 'message', true);

echo $message;
