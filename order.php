<?php
/*
Plugin Name: Order Management
Description: Hiển thị lịch sử đơn hàng cho người dùng tùy chỉnh.
Version: 1.7
Author: Bạn
*/

// Đảm bảo plugin chỉ chạy trong WordPress
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('order_history', 'render_order_history');

function render_order_history() {
    global $wpdb;
    ob_start();

    // Kiểm tra đăng nhập
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

    // Xử lý Nhận hàng
    if (isset($_GET['nhanhang']) && is_numeric($_GET['nhanhang'])) {
        $mahd = intval($_GET['nhanhang']);
        $wpdb->update("{$wpdb->prefix}hoadon", [
            'MaTrangThai' => 3,
            'NgayNhan' => current_time('mysql'),
        ], ['MaHD' => $mahd]);

        $tong_tien = $wpdb->get_var($wpdb->prepare("
            SELECT SUM((DonGia - COALESCE(GiamGia, 0)) * SoLuong)
            FROM {$wpdb->prefix}chitiethd
            WHERE MaHD = %d
        ", $mahd));

        $wpdb->update("{$wpdb->prefix}hoadon", [
            'TienDaNhan' => $tong_tien ?: 0
        ], ['MaHD' => $mahd]);

        wp_redirect(esc_url(remove_query_arg('nhanhang')));
        exit;
    }

    // Xử lý Hủy đơn
    if (isset($_GET['huydon']) && is_numeric($_GET['huydon'])) {
        $mahd = intval($_GET['huydon']);
        $currentStatus = $wpdb->get_var($wpdb->prepare("SELECT MaTrangThai FROM {$wpdb->prefix}hoadon WHERE MaHD = %d", $mahd));

        if ($currentStatus == 7) {
            $wpdb->update("{$wpdb->prefix}hoadon", ['MaTrangThai' => 3], ['MaHD' => $mahd]);
        } elseif ($currentStatus == 3) {
            $wpdb->update("{$wpdb->prefix}hoadon", ['MaTrangThai' => -1], ['MaHD' => $mahd]);
        }

        wp_redirect(esc_url(remove_query_arg('huydon')));
        exit;
    }

    // Xử lý Hoàn hàng
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

        wp_redirect(esc_url(remove_query_arg('hoanhang')));
        exit;
    }
// Lấy số trang hiện tại
    $paged = get_query_var('paged') ? get_query_var('paged') : 1;

    // Số lượng đơn hàng trên mỗi trang
    $orders_per_page = 1;

    // Lấy tổng số đơn hàng
    $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hoadon");

    // Tính số trang
    $total_pages = ceil($total_orders / $orders_per_page);

    // Lấy danh sách đơn hàng
    $orders = $wpdb->get_results(
        $wpdb->prepare("SELECT hd.*, tt.TenTrangThai
                        FROM {$wpdb->prefix}hoadon hd
                        LEFT JOIN trangthai tt ON hd.MaTrangThai = tt.MaTrangThai
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
        echo '<a href="' . esc_url(add_query_arg(['mahd' => $order->MaHD])) . '" class="btn btn-sm btn-primary">Xem</a> ';

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

    // Phân trang
    $pagination_args = [
        'base' => esc_url(add_query_arg('paged', '%#%')),
        'format' => '?paged=%#%',
        'current' => $paged,
        'total' => $total_pages,
        'prev_text' => '&laquo; Trước',
        'next_text' => 'Sau &raquo;',
    ];
    echo '<div class="pagination">' . paginate_links($pagination_args) . '</div>';
} else {
    echo '<p class="no-orders">Bạn chưa có đơn hàng nào.</p>';
}

    // Hiển thị lớp phủ chi tiết đơn hàng nếu có tham số mahd
    if (isset($_GET['mahd']) && is_numeric($_GET['mahd'])) {
        $mahd = intval($_GET['mahd']);
        $order_details = $wpdb->get_results($wpdb->prepare(
            "SELECT ct.MaHD, ct.MaHH, ct.SoLuong, ct.DonGia, COALESCE(ct.GiamGia, 0) as GiamGia, hh.TenHH, hh.Hinh
             FROM {$wpdb->prefix}chitiethd ct
             LEFT JOIN {$wpdb->prefix}hanghoa hh ON ct.MaHH = hh.MaHH
             WHERE ct.MaHD = %d",
            $mahd
        ));

        $order_info = $wpdb->get_row($wpdb->prepare(
            "SELECT SDT, DiaChi, NgayDat, TongTien
             FROM {$wpdb->prefix}hoadon
             WHERE MaHD = %d AND MaKH = %d",
            $mahd, $makh
        ));

        if ($order_details && $order_info) {
            echo '<div class="order-overlay" onclick="this.style.display=\'none\'">';
            echo '<div class="order-details-container" onclick="event.stopPropagation()">';
            echo '<h3>Chi Tiết Đơn Hàng #' . esc_html($mahd) . '</h3>';
            echo '<p><strong>SĐT:</strong> ' . esc_html($order_info->SDT ?: 'Chưa có thông tin') . ' | <strong>Địa Chỉ:</strong> ' . esc_html($order_info->DiaChi ?: 'Chưa có thông tin') . '</p>';
            echo '<table class="order-history-table table table-bordered">';
            echo '<thead><tr><th>Ảnh</th><th>Tên Sản Phẩm</th><th>Ngày</th><th>Số Lượng</th><th>Đơn Giá</th><th>Tổng Tiền</th></tr></thead><tbody>';

            $ngay_dat = $order_info->NgayDat;
            foreach ($order_details as $detail) {
                $image_url = !empty($detail->Hinh)
                    ? esc_url(get_template_directory_uri() . '/img/' . $detail->Hinh)
                    : 'https://via.placeholder.com/80';
                $don_gia_hien_tai = $detail->DonGia - $detail->GiamGia;
                $tong_tien_item = $don_gia_hien_tai * $detail->SoLuong;
                echo '<tr>';
                echo '<td><img src="' . $image_url . '" width="80" alt="' . esc_attr($detail->TenHH ?? '') . '"></td>';
                echo '<td>' . esc_html($detail->TenHH ?: 'Sản phẩm không tồn tại') . '</td>';
                echo '<td>' . date('Y-m-d', strtotime($ngay_dat)) . '</td>';
                echo '<td>' . esc_html($detail->SoLuong) . '</td>';
                echo '<td>' . number_format($don_gia_hien_tai, 0, ',', '.') . 'đ</td>';
                echo '<td>' . number_format($tong_tien_item, 0, ',', '.') . 'đ</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '<p style="text-align: right; font-weight: bold; margin-top: 10px;">Tổng tiền: ' . number_format($order_info->TongTien, 0, ',', '.') . 'đ</p>';
            echo '<a href="' . esc_url(remove_query_arg('mahd')) . '" class="btn btn-sm btn-secondary">Đóng</a>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<p style="margin-top: 20px;">Không tìm thấy chi tiết đơn hàng.</p>';
        }
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

        .order-history-table .btn-secondary {
            background-color: #6c757d;
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

        /* Đặt kiểu chung cho phân trang */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        /* Cải thiện kiểu các nút phân trang */
        .pagination .page-numbers {
            background-color: #fff;
            color: black;
            border: 1px solid #007bff;
            padding: 8px 12px;
            margin: 0 5px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            transition: background-color 0.3s, transform 0.3s;
        }

        /* Hiển thị màu nền khi hover vào nút phân trang */
        .pagination .page-numbers:hover {
            background-color: #0056b3;
            transform: scale(1.1);
        }

        /* Đặt màu nền cho trang hiện tại */
        .pagination .current {
            background-color: #007bff;
            border-color: #007bff;
            color: #fff;
            pointer-events: none;
        }

        /* Cải thiện kiểu cho các nút 'Trước' và 'Sau' */
        .pagination .prev, .pagination .next {
            background-color: #f8f9fa;
            color: #007bff;
            border: 1px solid #007bff;
            
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 5px;
            font-size: 14px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        /* Hiển thị màu nền khi hover vào nút 'Trước' và 'Sau' */
        .pagination .prev:hover, .pagination .next:hover {
            background-color: #e2e6ea;
        }

        /* Đặt kiểu cho các nút phân trang khi vô hiệu */
        .pagination .disabled {
            background-color: #f1f1f1;
            border-color: #ccc;
            color: #999;
            pointer-events: none;
        }
        /* Lớp phủ và container chi tiết */
        .order-overlay {
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .order-details-container {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            max-width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
    </style>
    <?php
}

add_action('wp_head', 'add_order_management_styles');
?>