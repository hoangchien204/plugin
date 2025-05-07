<?php
/*
Plugin Name: Custom Login
Description: Tạo một trang đăng nhập sử dụng template của theme mà không ảnh hưởng đến theme.
Version: 1.0
Author: Bạn
*/

// Đảm bảo không truy cập trực tiếp
if (!defined('ABSPATH')) exit;

// Tạo shortcode cho form đăng nhập
function clp_login_form_shortcode() {
    ob_start();
    
    // Gọi template đăng nhập từ theme
    get_template_part('page-log-in');  // Đây là tên file của template đăng nhập
    
    return ob_get_clean();
}
add_shortcode('custom_login_form', 'clp_login_form_shortcode');

// Xử lý đăng nhập
add_action('init', 'clp_process_login_form');
function clp_process_login_form() {
    global $wpdb;

    // Kiểm tra nonce và dữ liệu gửi lên
    if (
        isset($_POST['clp_login_nonce']) &&
        wp_verify_nonce($_POST['clp_login_nonce'], 'clp_login_action')
    ) {
        // Lấy thông tin từ form
        $username = sanitize_user($_POST['username']);
        $password = $_POST['password'];

        // Kiểm tra dữ liệu người dùng trong bảng wp_custom_users
        $table_name = $wpdb->prefix . 'custom_users';
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE username = %s", $username));

        if ($user) {
            // Kiểm tra mật khẩu
            if (password_verify($password, $user->password)) {
                // Đăng nhập thành công, tạo cookie
                setcookie('custom_user_logged_in', $user->username, time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                setcookie('custom_user_id', $user->id, time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

                wp_redirect(home_url('/'));
                exit;
            } else {
                wp_redirect(add_query_arg('login', 'failed', wp_get_referer()));
                exit;
            }
        } else {
            wp_redirect(add_query_arg('login', 'failed', wp_get_referer()));
            exit;
        }
    }
}

// Kiểm tra đăng nhập khi tải trang
add_action('wp', 'clp_check_login');
function clp_check_login() {
    if (isset($_COOKIE['custom_user_logged_in']) && isset($_COOKIE['custom_user_id'])) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'custom_users';
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $_COOKIE['custom_user_id']));

        if ($user && $user->username === $_COOKIE['custom_user_logged_in']) {
            // Người dùng đã đăng nhập vào hệ thống tùy chỉnh
        } else {
            // Nếu cookie không hợp lệ, đăng xuất
            clp_logout();
        }
    } else {
        // Nếu không có cookie, yêu cầu đăng nhập lại, nhưng cho phép truy cập trang đăng ký
        if (!is_page('dang-nhap') && !is_page('dang-ky')) {
            wp_redirect(home_url('/dang-nhap'));
            exit;
        }
    }
}

// Đảm bảo đăng xuất khỏi hệ thống của bạn
add_action('init', 'clp_logout');
function clp_logout() {
    if (isset($_GET['logout'])) {
        // Xóa cookie đăng nhập khi người dùng đăng xuất
        setcookie('custom_user_logged_in', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN); // Xóa cookie đăng nhập
        setcookie('custom_user_id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN); // Xóa cookie ID người dùng
        wp_redirect(home_url()); // Điều hướng về trang chủ sau khi đăng xuất
        exit;
    }
}
