<?php
/**
 * Plugin Name: Fruity Theme Extension
 * Description: Plugin mở rộng thêm chức năng cho theme Fruity
 * Version: 1.0
 * Author: Bạn
 */

add_action('init', function () {
    register_nav_menus([
        'header-menu' => __('Header Menu'),
        'footer-menu' => __('Footer Menu'),
    ]);
});

add_action('wp_enqueue_scripts', function () {
    $theme_uri = get_template_directory_uri();
    
    wp_enqueue_style('fruity-root', $theme_uri . '/css/root.css');
    wp_enqueue_script('fruity-main', $theme_uri . '/js/root.js', ['jquery'], null, true);
});
