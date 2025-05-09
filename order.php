<?php
/*
Plugin Name: Order Management
Description: Hiển thị lịch sử đơn hàng cho người dùng tùy chỉnh.
Version: 1.0
Author: Bạn
*/

add_shortcode('order_history', 'render_order_history');

function render_order_history() {
    global $wpdb;
    ob_start();

    if (!isset($_COOKIE['custom_user_id'])) {
        echo '<p>Vui lòng <a href="' . esc_url(home_url('/dang-nhap')) . '">đăng nhập</a> để xem đơn hàng của bạn.</p>';
        return ob_get_clean();
    }

    $makh = intval($_COOKIE['custom_user_id']);

    // Tự động cập nhật trạng thái sau 3 ngày
    $wpdb->query("
        UPDATE {$wpdb->prefix}hoadon
        SET MaTrangThai = 3
        WHERE MaTrangThai IN (2, 3)
          AND DATEDIFF(CURDATE(), NgayDat) > 3
    ");

    // Nhận hàng
    if (isset($_GET['nhanhang']) && is_numeric($_GET['nhanhang'])) {
        $mahd = intval($_GET['nhanhang']);

        $wpdb->update("{$wpdb->prefix}hoadon", [
            'MaTrangThai' => 3,
            'NgayNhan' => current_time('mysql'),
        ], ['MaHD' => $mahd]);

        $tong_tien = $wpdb->get_var($wpdb->prepare("
            SELECT SUM((DonGia - GiamGia) * SoLuong)
            FROM {$wpdb->prefix}chitiethd
            WHERE MaHD = %d
        ", $mahd));

        $wpdb->update("{$wpdb->prefix}hoadon", [
            'TienDaNhan' => $tong_tien
        ], ['MaHD' => $mahd]);

        echo '<script>location.href="' . esc_url(remove_query_arg('nhanhang')) . '";</script>';
        exit;
    }

    // Hủy đơn
    if (isset($_GET['huydon']) && is_numeric($_GET['huydon'])) {
        $mahd = intval($_GET['huydon']);
        $currentStatus = $wpdb->get_var($wpdb->prepare("SELECT MaTrangThai FROM {$wpdb->prefix}hoadon WHERE MaHD = %d", $mahd));

        if ($currentStatus == 7) {
            $wpdb->update("{$wpdb->prefix}hoadon", ['MaTrangThai' => 3], ['MaHD' => $mahd]);
        } elseif ($currentStatus == 3) {
            $wpdb->update("{$wpdb->prefix}hoadon", ['MaTrangThai' => -1], ['MaHD' => $mahd]);
        }

        echo '<script>location.href="' . esc_url(remove_query_arg('huydon')) . '";</script>';
        exit;
    }

    // Hoàn hàng
    if (isset($_GET['hoanhang']) && is_numeric($_GET['hoanhang'])) {
        $mahd = intval($_GET['hoanhang']);
        $currentStatus = $wpdb->get_var($wpdb->prepare("SELECT MaTrangThai FROM {$wpdb->prefix}hoadon WHERE MaHD = %d", $mahd));

        if (in_array($currentStatus, [2, 3])) {
            $wpdb->update("{$wpdb->prefix}hoadon", ['MaTrangThai' => 7], ['MaHD' => $mahd]);
            $wpdb->update("{$wpdb->prefix}hoadon", ['TienDaNhan' => 0.00], ['MaHD' => $mahd]);

            $items = $wpdb->get_results($wpdb->prepare("
                SELECT MaHH, SoLuong
                FROM {$wpdb->prefix}chitiethd
                WHERE MaHD = %d
            ", $mahd));

            foreach ($items as $item) {
                $wpdb->query($wpdb->prepare("
                    UPDATE {$wpdb->prefix}hanghoa
                    SET SoLuongTonKho = SoLuongTonKho + %d
                    WHERE MaHH = %d
                ", $item->SoLuong, $item->MaHH));
            }
        }

        echo '<script>location.href="' . esc_url(remove_query_arg('hoanhang')) . '";</script>';
        exit;
    }

    // Lấy đơn hàng
    $orders = $wpdb->get_results(
        $wpdb->prepare("SELECT hd.*, tt.TenTrangThai
                        FROM {$wpdb->prefix}hoadon hd
                        LEFT JOIN TrangThai tt ON hd.MaTrangThai = tt.MaTrangThai
                        WHERE hd.MaKH = %d
                        ORDER BY hd.NgayDat DESC", $makh)
    );

    if ($orders) {
        echo '<table class="order-history-table table table-bordered">';
        echo '<thead><tr><th>Mã đơn</th><th>Ảnh</th><th>Ngày đặt</th><th>Ngày giao</th><th>Tổng tiền</th><th>Trạng thái</th><th>Hành động</th></tr></thead><tbody>';

        foreach ($orders as $order) {
            $ngay_giao = date('Y-m-d', strtotime($order->NgayDat . ' +7 days'));

            $first_item = $wpdb->get_row($wpdb->prepare("
                SELECT hh.Hinh, hh.TenHH
                FROM {$wpdb->prefix}chitiethd ct
                JOIN {$wpdb->prefix}hanghoa hh ON ct.MaHH = hh.MaHH
                WHERE ct.MaHD = %d
                LIMIT 1", $order->MaHD));

            $image_url = !empty($first_item->Hinh)
                ? esc_url(get_template_directory_uri() . '/img/' . $first_item->Hinh)
                : 'https://via.placeholder.com/80';

            $hide_return_button = false;
            if ($order->NgayNhan) {
                $date_diff = (strtotime(current_time('mysql')) - strtotime($order->NgayNhan)) / (60 * 60 * 24);
                if ($date_diff > 1) $hide_return_button = true;
            }

            echo '<tr>';
            echo '<td>' . esc_html($order->MaHD) . '</td>';
            echo '<td><img src="' . $image_url . '" width="80" alt="' . esc_attr($first_item->TenHH ?? '') . '"></td>';
            echo '<td>' . date('Y-m-d', strtotime($order->NgayDat)) . '</td>';
            echo '<td>' . esc_html($ngay_giao) . '</td>';
            echo '<td>' . number_format($order->TongTien, 0, ',', '.') . 'đ</td>';
            echo '<td>' . esc_html($order->TenTrangThai) . '</td>';

            echo '<td>';
            echo '<a href="' . esc_url(home_url('/chi-tiet-don-hang?mahd=' . $order->MaHD)) . '" class="btn btn-sm btn-primary">Xem</a> ';

            if ($order->MaTrangThai == 2) {
                echo '<a href="' . esc_url(add_query_arg(['nhanhang' => $order->MaHD])) . '" class="btn btn-sm btn-success" onclick="return confirm(\'Xác nhận bạn đã nhận hàng?\')">Đã nhận hàng</a> ';
            }

            if (!$hide_return_button && in_array($order->MaTrangThai, [2, 3]) && $order->MaTrangThai != 7) {
                echo '<a href="' . esc_url(add_query_arg(['hoanhang' => $order->MaHD])) . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Bạn chắc chắn muốn hoàn hàng?\')">Hoàn hàng</a> ';
            }

            if (!in_array($order->MaTrangThai, [2, 3, 7])) {
                echo '<a href="' . esc_url(add_query_arg(['huydon' => $order->MaHD])) . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Bạn chắc chắn muốn hủy đơn hàng này?\')">Hủy</a>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p class="no-orders">Bạn chưa có đơn hàng nào.</p>';
    }

    return ob_get_clean();
}


function add_order_management_styles() {
    ?>
    <style>
        .order-history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .order-history-table th, .order-history-table td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }

        .order-history-table th {
            background-color: #f4f4f4;
            font-weight: bold;
        }

        .order-history-table td img {
            max-width: 80px;
            height: auto;
        }

        .order-history-table .btn {
            margin: 5px;
            padding: 5px 10px;
            font-size: 14px;
        }

        .order-history-table .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .order-history-table .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .order-history-table .btn:hover {
            opacity: 0.8;
        }
        .order-history-table {
            width: 100%;
            table-layout: auto;
        }

        .order-history-table th, .order-history-table td {
            padding: 10px;
            vertical-align: middle;
            text-align: center;
            word-break: break-word;
        }

        .order-history-table img {
            max-width: 80px;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .no-orders {
            text-align: center;
            font-size: 16px;
            color: #777;
        }

        .alert {
            padding: 10px;
            background-color: #ffcc00;
            color: #000;
            font-weight: bold;
            text-align: center;
        }

        .common-banner {
            width: 100%;
            height: 200px;
        }
    </style>
    <?php
}
add_action('wp_head', 'add_order_management_styles');
