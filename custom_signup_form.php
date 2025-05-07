<?php
/*
Plugin Name: Custom Sign Up Plugin
Description: Tạo một trang đăng ký và lưu thông tin vào bảng wp_custom_users.
Version: 1.0
Author: Bạn
*/

// Đảm bảo không truy cập trực tiếp
if (!defined('ABSPATH')) exit;

// Xử lý form đăng ký
add_action('init', 'clp_process_signup_form');
function clp_process_signup_form() {
    global $wpdb;

    // Kiểm tra nonce và nút submit
    if (
        isset($_POST['clp_signup_nonce']) &&
        wp_verify_nonce($_POST['clp_signup_nonce'], 'clp_signup_action') &&
        isset($_POST['clp_signup_submit'])
    ) {
        // Lấy thông tin từ form đăng ký
        $username = sanitize_text_field($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];

        // Kiểm tra dữ liệu đầu vào
        if (empty($username) || empty($email) || empty($password)) {
            wp_redirect(add_query_arg('signup', 'empty_fields', wp_get_referer()));
            exit;
        }

        // Kiểm tra email hoặc username đã tồn tại chưa
        $table_name = $wpdb->prefix . 'custom_users';
        $user_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s OR username = %s", $email, $username));

        if ($user_exists > 0) {
            wp_redirect(add_query_arg('signup', 'user_exists', wp_get_referer()));
            exit;
        }

        // Mã hóa mật khẩu trước khi lưu
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);

        // Thêm người dùng vào bảng wp_custom_users
        $wpdb->insert(
            $table_name,
            array(
                'username' => $username,
                'email'    => $email,
                'password' => $password_hashed
            ),
            array('%s', '%s', '%s')
        );

        // Kiểm tra lỗi khi chèn dữ liệu
        if ($wpdb->last_error) {
            wp_redirect(add_query_arg('signup', 'failed', wp_get_referer()));
            exit;
        }

        // Chuyển hướng đến trang đăng nhập sau khi đăng ký thành công
        wp_redirect(add_query_arg('signup', 'success', home_url('/dang-nhap')));
        exit;
    }
}

// Tạo shortcode cho form đăng ký
function clp_signup_form_shortcode() {
    ob_start();
    
    // Gọi template đăng ký từ theme
    get_template_part('page-sign-up');
    
    return ob_get_clean();
}
add_shortcode('custom_signup_form', 'clp_signup_form_shortcode');