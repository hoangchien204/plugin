<?php
/**
 * Plugin Name: Admin Order
 * Description: Shortcode hiển thị danh sách đơn hàng cho trang quản trị.
 * Version: 1.1
 * Author: Bạn
 */

if (!defined('ABSPATH')) {
    exit;
}

// SHORTCODE hiển thị danh sách đơn hàng
function admin_order_list_shortcode() {
    global $wpdb;

    $orders = $wpdb->get_results("SELECT hd.*, tt.TenTrangThai, kh.username 
                                  FROM {$wpdb->prefix}hoadon hd
                                  LEFT JOIN TrangThai tt ON hd.MaTrangThai = tt.MaTrangThai
                                  LEFT JOIN {$wpdb->prefix}custom_users kh ON hd.MaKH = kh.id
                                  ORDER BY hd.NgayDat DESC");

    if ($orders) {
        ob_start();
        ?>
        <div class="order-management-container">
            <form method="post" action="">
                <table class="order-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"> Chọn</th>
                            <th>Mã Đơn</th>
                            <th>Username</th>
                            <th>Ngày Đặt</th>
                            <th>Ngày Giao</th>
                            <th>Trạng Thái</th>
                            <th>Tổng Tiền</th>
                            <th>Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order) : ?>
                            <tr>
                                <td><input type="checkbox" name="selected_orders[]" value="<?php echo esc_attr($order->MaHD); ?>"></td>
                                <td><?php echo esc_html($order->MaHD); ?></td>
                                <td><?php echo esc_html($order->username); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($order->NgayDat)); ?></td>
                                <td><?php echo ($order->NgayGiao != '1900-01-01 00:00:00' ? date('Y-m-d', strtotime($order->NgayGiao)) : 'Chưa giao'); ?></td>
                                <td><?php echo esc_html($order->TenTrangThai); ?></td>
                                <td><?php echo number_format($order->TongTien, 2); ?> VNĐ</td>
                                <td>
                                <?php if ($order->MaTrangThai == 0) : ?>
                                        <!-- Chuyển trực tiếp sang trạng thái 'Đã giao' khi nhấn nút 'Giao Hàng' -->
                                        <button type="submit" name="action" value="mark_as_delivered_<?php echo esc_attr($order->MaHD); ?>" class="btn btn-ship">Giao Hàng</button>
                                    <?php elseif ($order->MaTrangThai == 2) : ?>
                                        <!-- Ẩn tất cả các nút khi trạng thái là 'Đã giao' -->
                                        <button type="submit" name="action" value="cancel_order_<?php echo esc_attr($order->MaHD); ?>" class="btn btn-cancel">Hủy Đơn</button>
                                    <?php elseif ($order->MaTrangThai == 3) : ?>
                                        <!-- Khi trạng thái là 'Hủy', chỉ hiển thị nút chi tiết -->
                                    <?php endif; ?>
                                    <!-- Nút chi tiết luôn hiển thị cho tất cả trạng thái -->
                                    <button type="submit" name="action" value="view_details_<?php echo esc_attr($order->MaHD); ?>" class="btn btn-details">Chi Tiết</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById("select-all").addEventListener("change", function() {
                    document.querySelectorAll('input[name="selected_orders[]"]').forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    } else {
        return '<p class="no-orders">Không có đơn hàng nào.</p>';
    }
}
add_shortcode('admin_order_list', 'admin_order_list_shortcode');

// Xử lý các hành động đơn hàng
function handle_order_actions() {
    if (isset($_POST['action'])) {
        global $wpdb;
        $action = sanitize_text_field($_POST['action']);
        $order_id = intval(substr($action, strrpos($action, '_') + 1));

        // Kiểm tra trạng thái của đơn hàng hiện tại
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT MaTrangThai FROM {$wpdb->prefix}hoadon WHERE MaHD = %d", 
            $order_id
        ));

        if (strpos($action, 'cancel_order_') === 0) {
            // Nếu trạng thái hiện tại là -1 (Khách hàng đã hủy đơn hàng), không cho hủy lại
            if ($current_status == -1) {
                return;
            }

            // Cập nhật trạng thái đơn hàng thành 6 (Đã hủy đơn) khi bấm nút hủy đơn
            $wpdb->update("{$wpdb->prefix}hoadon", ['MaTrangThai' => 6], ['MaHD' => $order_id]);
        }

        if (strpos($action, 'ship_order_') === 0) {
            $wpdb->update("{$wpdb->prefix}hoadon", ['MaTrangThai' => 1], ['MaHD' => $order_id]);
        }

        if (strpos($action, 'mark_as_delivered_') === 0) {
            $wpdb->update("{$wpdb->prefix}hoadon", [
                'MaTrangThai' => 2,
                'NgayGiao' => current_time('mysql')
            ], ['MaHD' => $order_id]);
        }

        if (strpos($action, 'view_details_') === 0) {
            wp_redirect(home_url('/admin-order-details?mahd=' . $order_id));
            exit;
        }
    }
}

add_action('init', 'handle_order_actions');


// Thêm CSS cho bảng đơn hàng
function admin_order_management_styles() {
    ?>
    <style>
        .order-management-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f9fafb;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            padding-bottom: 2rem;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-top: 1rem;
        }

        .order-table th {
            background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%);
            color: #ffffff;
            padding: 1.1rem;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            text-align: left;
            letter-spacing: 0.05em;
        }

        .order-table td {
            padding: 1.1rem;
            font-size: 0.95rem;
            color: #333;
            border-bottom: 1px solid #E5E7EB;
            vertical-align: middle;
            text-align: left;
        }

        .order-table tr {
            transition: background 0.2s ease;
        }

        .order-table tr:hover {
            background-color: #F3F4F6;
        }

        .order-table input[type="checkbox"] {
            width: 1.5rem;
            height: 1.5rem;
            accent-color: #6366F1;
            cursor: pointer;
        }

        .btn {
            padding: 0.6rem 1.4rem;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.6rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-ship {
            background: #10B981;
            color: #ffffff;
        }

        .btn-ship:hover {
            background: #059669;
        }

        .btn-delivered {
            background: #3B82F6;
            color: #ffffff;
        }

        .btn-delivered:hover {
            background: #2563EB;
        }

        .btn-cancel {
            background: #EF4444;
            color: #ffffff;
        }

        .btn-cancel:hover {
            background: #DC2626;
        }

        .btn-details {
            background: #6B7280;
            color: #ffffff;
        }

        .btn-details:hover {
            background: #4B5563;
        }

        .no-orders {
            text-align: center;
            font-size: 1.1rem;
            color: #6B7280;
            padding: 2rem;
            background: #F3F4F6;
            border-radius: 8px;
            margin: 2rem 0;
            font-weight: 500;
        }

        .order-table th, .order-table td {
            padding: 1rem;
            border: 1px solid #E5E7EB;
        }

        /* Responsive */
        @media screen and (max-width: 768px) {
            .order-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .order-table th, .order-table td {
                min-width: 120px;
                padding: 1rem;
            }

            .btn {
                padding: 0.5rem 1.2rem;
                font-size: 0.85rem;
            }

            .no-orders {
                padding: 1.5rem;
                font-size: 1rem;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'admin_order_management_styles');
?>