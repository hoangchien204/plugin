<?php
/*
Plugin Name: Ecommerce Admin Plugin
Description: Provides admin dashboard functionality with custom user table authentication
Version: 2.0
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Custom database handler
class Ecommerce_Admin_DB {
    private $wpdb;
    private $orders_table;
    private $products_table;
    private $custom_users_table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->orders_table = $wpdb->prefix . 'orders';
        $this->products_table = $wpdb->prefix . 'products';
        $this->custom_users_table = $wpdb->prefix . 'custom_users';
    }

    // Lấy thông tin user theo ID
    public function get_user_by_id($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->custom_users_table} WHERE id = %d", $id),
            ARRAY_A
        );
    }

    // Các hàm thống kê
    public function get_today_revenue() {
        $revenue = $this->wpdb->get_var(
            "SELECT SUM(total_amount) FROM {$this->orders_table} WHERE DATE(order_date) = CURDATE()"
        );
        return $revenue ? $revenue : 0;
    }

    public function get_new_orders() {
        $order_count = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->orders_table} WHERE DATE(order_date) = CURDATE()"
        );
        return $order_count ? $order_count : 0;
    }

    public function get_recent_orders() {
        $results = $this->wpdb->get_results(
            "SELECT o.order_id, o.total_amount, o.status 
             FROM {$this->orders_table} o 
             ORDER BY o.order_date DESC 
             LIMIT 10",
            ARRAY_A
        );
        return $results;
    }
}

// Hàm kiểm tra đăng nhập custom
function ecommerce_check_custom_user_role() {
    if (is_page('admin-dashboard')) { // page slug của trang admin
        if (!isset($_SESSION)) {
            session_start();
        }

        if (!isset($_SESSION['custom_user_id'])) {
            // Chưa đăng nhập => Redirect
            wp_redirect(home_url());
            exit;
        }

        $db = new Ecommerce_Admin_DB();
        $user = $db->get_user_by_id($_SESSION['custom_user_id']);

        if (!$user || $user['role'] != '1') {
            // Không có user hoặc role khác 1 => Redirect
            wp_redirect(home_url());
            exit;
        }
    }
}
add_action('template_redirect', 'ecommerce_check_custom_user_role');

// Shortcode để render dashboard cards
function ecommerce_admin_cards_shortcode() {
    $db = new Ecommerce_Admin_DB();
    $today_revenue = $db->get_today_revenue();
    $new_orders = $db->get_new_orders();

    ob_start();
    ?>
    <section class="admin-cards">
        <div class="card">
            <h3>Doanh thu hôm nay</h3>
            <p><?php echo number_format($today_revenue, 0, ',', '.') . '₫'; ?></p>
        </div>
        <div class="card">
            <h3>Đơn hàng mới</h3>
            <p><?php echo esc_html($new_orders); ?></p>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('ecommerce_admin_cards', 'ecommerce_admin_cards_shortcode');

// Shortcode để render danh sách đơn hàng
function ecommerce_admin_orders_shortcode() {
    $db = new Ecommerce_Admin_DB();
    $recent_orders = $db->get_recent_orders();

    ob_start();
    ?>
    <section class="admin-table">
        <h2>Đơn hàng gần đây</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent_orders)) : ?>
                    <?php foreach ($recent_orders as $order) : ?>
                        <tr>
                            <td>#<?php echo esc_html($order['order_id']); ?></td>
                            <td><?php echo number_format($order['total_amount'], 0, ',', '.') . '₫'; ?></td>
                            <td><?php echo esc_html($order['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="3">Không có đơn hàng nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('ecommerce_admin_orders', 'ecommerce_admin_orders_shortcode');

// Hàm login riêng (nếu bạn có form login riêng, thì xử lý set session ở đây)
function ecommerce_custom_login($username, $password) {
    global $wpdb;
    $custom_users_table = $wpdb->prefix . 'custom_users';

    $user = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$custom_users_table} WHERE username = %s",
            $username
        ),
        ARRAY_A
    );

    if ($user && password_verify($password, $user['password'])) {
        if (!isset($_SESSION)) {
            session_start();
        }
        $_SESSION['custom_user_id'] = $user['id'];

        return true;
    }
    return false;
}
?>
