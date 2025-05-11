<?php
/**
 * Plugin Name: Admin Order
 * Description: Shortcode hiển thị danh sách đơn hàng cho trang quản trị.
 * Version: 1.7
 * Author: Bạn
 */

if (!defined('ABSPATH')) {
    exit;
}

// SHORTCODE hiển thị danh sách đơn hàng
function admin_order_list_shortcode() {
    global $wpdb;

    // Kiểm tra nếu có yêu cầu xuất file Excel
    if (isset($_GET['action']) && $_GET['action'] === 'export_order_to_excel' && isset($_GET['mahd'])) {
        export_order_to_excel();
    }

    // Phân trang
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 6;
    $offset = ($paged - 1) * $per_page;

    // Tổng số đơn hàng
    $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hoadon");

    // Lọc theo thời gian nếu có
    $time_filter = '';
    if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $start_date = $_GET['start_date'];
        $end_date = $_GET['end_date'];
        $time_filter = " AND hd.NgayDat BETWEEN '$start_date' AND '$end_date'";
    }

    // Lấy danh sách đơn hàng cho trang hiện tại
    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT hd.*, tt.TenTrangThai, kh.username 
         FROM {$wpdb->prefix}hoadon hd
         LEFT JOIN TrangThai tt ON hd.MaTrangThai = tt.MaTrangThai
         LEFT JOIN {$wpdb->prefix}custom_users kh ON hd.MaKH = kh.id
         WHERE 1=1 $time_filter
         ORDER BY hd.NgayDat DESC
         LIMIT %d OFFSET %d",
         $per_page, $offset
    ));

    if ($orders) {
        ob_start();
        ?>
        <div class="order-management-container">
            <!-- Nút giao hàng hàng loạt -->
            
        <form method="post" action="">
            <div class="ngang">
        <button type="submit" name="action" value="bulk_mark_as_delivered" class="btn btn-bulk-deliver">Giao Hàng Hàng Loạt</button>
    </div>

            <table class="order-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
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
                            <td><input type="checkbox" name="selected_orders[]" value="<?php echo esc_attr($order->MaHD); ?>" <?php echo $order->MaTrangThai == 0 ? '' : 'disabled'; ?>></td>
                            <td><?php echo esc_html($order->MaHD); ?></td>
                            <td><?php echo esc_html($order->username); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($order->NgayDat)); ?></td>
                            <td><?php echo ($order->NgayGiao != '1900-01-01 00:00:00' ? date('Y-m-d', strtotime($order->NgayGiao)) : 'Chưa giao'); ?></td>
                            <td><?php echo esc_html($order->TenTrangThai); ?></td>
                            <td><?php echo number_format($order->TongTien, 2); ?> VNĐ</td>
                            <td>
                                <?php if ($order->MaTrangThai == 0) : ?>
                                    <button type="submit" name="action" value="mark_as_delivered_<?php echo esc_attr($order->MaHD); ?>" class="btn btn-ship">Giao Hàng</button>
                                <?php elseif ($order->MaTrangThai == 2) : ?>
                                    <button type="submit" name="action" value="cancel_order_<?php echo esc_attr($order->MaHD); ?>" class="btn btn-cancel">Hủy Đơn</button>
                                <?php endif; ?>
                                <button type="button" onclick="showOrderDetails(<?php echo esc_attr($order->MaHD); ?>)" class="btn btn-details">Chi Tiết</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>

            <!-- Phân trang -->
            <div class="pagination">
                <?php
                $total_pages = ceil($total_orders / $per_page);
                for ($i = 1; $i <= $total_pages; $i++) {
                    $active = ($i === $paged) ? 'style="font-weight:bold;"' : '';
                    echo '<a href="' . esc_url(add_query_arg('paged', $i)) . '" ' . $active . '>' . $i . '</a> ';
                }
                ?>
            </div>

            <!-- Popup hiển thị chi tiết đơn hàng -->
            <div id="order-details-popup" class="form-popup-overlay">
                <div class="form-popup">
                    <button onclick="document.getElementById('order-details-popup').style.display='none'" class="btn-close">✖</button>
                    <h2>Chi Tiết Đơn Hàng</h2>
                    <div id="order-details-content"></div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById("select-all").addEventListener("change", function() {
                    document.querySelectorAll('input[name="selected_orders[]"]').forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });

                window.showOrderDetails = function(mahd) {
                    var detailsContent = document.getElementById('order-details-' + mahd);
                    if (detailsContent) {
                        document.getElementById('order-details-content').innerHTML = detailsContent.innerHTML;
                        document.getElementById('order-details-popup').style.display = 'flex';
                    } else {
                        alert('Không tìm thấy chi tiết đơn hàng.');
                    }
                };
            });
        </script>

        <?php
        foreach ($orders as $order) {
            $order_details = $wpdb->get_results($wpdb->prepare(
                "SELECT cthd.MaHD, cthd.MaHH, cthd.SoLuong, cthd.DonGia, hh.TenHH
                 FROM {$wpdb->prefix}chitiethd cthd
                 LEFT JOIN {$wpdb->prefix}hanghoa hh ON cthd.MaHH = hh.MaHH
                 WHERE cthd.MaHD = %d",
                $order->MaHD
            ));

            $order_info = $wpdb->get_row($wpdb->prepare(
                "SELECT TongTien, SDT, DiaChi, NgayDat
                 FROM {$wpdb->prefix}hoadon
                 WHERE MaHD = %d",
                $order->MaHD
            ));

            if ($order_details && $order_info) {
                ?>
                <div id="order-details-<?php echo esc_attr($order->MaHD); ?>" style="display: none;">
                    <p><strong>SĐT:</strong> <?php echo esc_html($order_info->SDT ?: 'Chưa có thông tin'); ?> | 
                    <strong>Địa Chỉ:</strong> <?php echo esc_html($order_info->DiaChi ?: 'Chưa có thông tin'); ?></p>
                    <table class="order-details-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Tên Sản Phẩm</th>
                                <th>Số Lượng</th>
                                <th>Đơn Giá</th>
                                <th>Tổng Tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $stt = 1; foreach ($order_details as $detail) : ?>
                                <tr>
                                    <td><?php echo $stt++; ?></td>
                                    <td><?php echo esc_html($detail->TenHH ?: 'Sản phẩm không tồn tại'); ?></td>
                                    <td><?php echo esc_html($detail->SoLuong); ?></td>
                                    <td><?php echo number_format($detail->DonGia, 2, ',', '.'); ?> VNĐ</td>
                                    <td><?php echo number_format($detail->SoLuong * $detail->DonGia, 2, ',', '.'); ?> VNĐ</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="text-align:right;"><strong>Tổng tiền phải thanh toán:</strong></td>
                                <td><strong><?php echo number_format($order_info->TongTien, 2, ',', '.'); ?> VNĐ</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                    <a href="<?php echo esc_url(add_query_arg(['action' => 'export_order_to_excel', 'mahd' => $order->MaHD])); ?>" class="btn btn-details">Xuất File Excel</a>
                </div>
                <?php
            }
        }

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

        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT MaTrangThai FROM {$wpdb->prefix}hoadon WHERE MaHD = %d", 
            $order_id
        ));

        if (strpos($action, 'cancel_order_') === 0) {
        // Kiểm tra nếu đơn hàng đã hủy (trạng thái -1), không làm gì thêm
        if ($current_status == -1) {
            return;
        }

        // Cập nhật trạng thái đơn hàng thành "Đã hủy" (trạng thái 6)
        $wpdb->update("{$wpdb->prefix}hoadon", ['MaTrangThai' => 6], ['MaHD' => $order_id]);

        // Lấy thông tin các sản phẩm trong đơn hàng
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT MaHH, SoLuong
            FROM {$wpdb->prefix}chitiethd
            WHERE MaHD = %d
        ", $order_id));

        // Cập nhật số lượng tồn kho của các sản phẩm bị hủy trong đơn hàng
        foreach ($items as $item) {
            // Cập nhật lại số lượng tồn kho (tăng thêm số lượng sản phẩm)
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}hanghoa
                SET SoLuongTonKho = SoLuongTonKho + %d
                WHERE MaHH = %d
            ", $item->SoLuong, $item->MaHH));
        }
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
        if ($action === 'bulk_mark_as_delivered' && isset($_POST['selected_orders'])) {
    $selected_orders = array_map('intval', $_POST['selected_orders']);
    foreach ($selected_orders as $order_id) {
        $wpdb->update("{$wpdb->prefix}hoadon", [
            'MaTrangThai' => 2,
            'NgayGiao' => current_time('mysql')
        ], ['MaHD' => $order_id]);
    }
}

    }
}
add_action('init', 'handle_order_actions');

// Xử lý xuất file CSV
require_once(ABSPATH . 'wp-content/plugins/PhpSpreadsheet-4.2.0/vendor/autoload.php');
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
function export_order_to_excel() {
    if (!isset($_GET['mahd']) || !is_numeric($_GET['mahd']) || $_GET['mahd'] <= 0) {
        wp_die('Mã đơn hàng không hợp lệ.');
    }

    $mahd = intval($_GET['mahd']);
    global $wpdb;

    // Truy vấn chi tiết đơn hàng
    $order_details = $wpdb->get_results($wpdb->prepare(
        "SELECT cthd.MaHH, cthd.SoLuong, cthd.DonGia, hh.TenHH
         FROM {$wpdb->prefix}chitiethd cthd
         LEFT JOIN {$wpdb->prefix}hanghoa hh ON cthd.MaHH = hh.MaHH
         WHERE cthd.MaHD = %d",
        $mahd
    ));

    $order_info = $wpdb->get_row($wpdb->prepare(
        "SELECT TongTien, SDT, DiaChi, NgayDat
         FROM {$wpdb->prefix}hoadon
         WHERE MaHD = %d",
        $mahd
    ));

    if (empty($order_details) || $order_info === null) {
        wp_die('Không tìm thấy dữ liệu để xuất Excel.');
    }

    // Tạo đối tượng Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Thêm thông tin đơn hàng
    $sheet->setCellValue('A1', 'Chi Tiết Đơn Hàng #' . $mahd);
    $sheet->setCellValue('A2', 'SĐT: ' . ($order_info->SDT ?: 'Chưa có thông tin'));
    $sheet->setCellValue('A3', 'Địa Chỉ: ' . ($order_info->DiaChi ?: 'Chưa có thông tin'));
    $sheet->setCellValue('A4', 'Ngày Đặt: ' . date('Y-m-d', strtotime($order_info->NgayDat)));

    // Dòng trống
    $sheet->setCellValue('A5', '');

    // Header bảng
    $sheet->setCellValue('A6', 'Tên Sản Phẩm');
    $sheet->setCellValue('B6', 'Số Lượng');
    $sheet->setCellValue('C6', 'Đơn Giá (VNĐ)');
    $sheet->setCellValue('D6', 'Tổng Tiền (VNĐ)');

    // Ghi dữ liệu chi tiết
    $row = 7;
    foreach ($order_details as $detail) {
        $sheet->setCellValue('A' . $row, $detail->TenHH ?: 'Sản phẩm không tồn tại');
        $sheet->setCellValue('B' . $row, (int)$detail->SoLuong);
        $sheet->setCellValue('C' . $row, (float)$detail->DonGia);
        $sheet->setCellValue('D' . $row, (float)$detail->SoLuong * $detail->DonGia);
        $row++;
    }

    // Ghi tổng tiền
    $sheet->setCellValue('C' . $row, 'Tổng tiền phải thanh toán:');
    $sheet->setCellValue('D' . $row, (float)$order_info->TongTien);

    // Định dạng tiền VNĐ cho các cột giá
    $currencyFormat = '#,##0" VNĐ"';
    $sheet->getStyle("C7:D{$row}")
          ->getNumberFormat()
          ->setFormatCode($currencyFormat);

    // Tự động điều chỉnh độ rộng cột
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Clear buffer tránh lỗi
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Xuất ra trình duyệt
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="order_' . $mahd . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}


// Thêm CSS cho bảng đơn hàng và popup
function admin_order_management_styles() {
    ?>
    <style>
      
        .order-management-container {
            max-width: 98%;
            /* margin: 2rem auto; */
            /* padding: 0 1rem; */
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f9fafb;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            padding-bottom: 2rem;
        }

        
        .bulk-delivery-form, .date-filter-form {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .date-filter-form .filter-date {
            display: flex;
            gap: 10px;
        }

        .date-filter-form .filter-date label {
            margin-right: 5px;
        }

        .date-filter-form .filter-date input {
            padding: 5px;
            margin-right: 10px;
        }

        .date-filter-form .filter-date button {
            padding: 5px 10px;
        }
        .ngang{
            display: flex;
            justify-content: flex-end;
        }
        .ngang button{
            background: #10B981
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

        /* Popup styles */
        .form-popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .form-popup {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
        }

        .btn-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 18px;
            width: 30px;
            height: 30px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn-close:hover {
            background: #c0392b;
        }

        .order-details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .order-details-table th, .order-details-table td {
            padding: 12px;
            border: 1px solid #E5E7EB;
            text-align: left;
        }

        .order-details-table th {
            background: #4F46E5;
            color: #ffffff;
            font-weight: 600;
        }

        .order-details-table td {
            background: #ffffff;
            color: #333;
        }

        .order-details-table tfoot td {
            background: #F3F4F6;
            font-weight: 600;
        }

            /* Phân trang */
            .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                margin-top: 20px;
                padding: 10px 0;
                background-color: #f9f9f9;
                border-radius: 5px;
                border: 1px solid #ddd;
            }

            /* Các nút phân trang */
            .pagination a {
                padding: 8px 16px;
                margin: 0 4px;
                border: 1px solid #ddd;
                background-color: #fff;
                color: #007bff;
                text-decoration: none;
                font-size: 14px;
                border-radius: 4px;
                transition: background-color 0.3s, color 0.3s;
            }

            /* Khi hover */
            .pagination a:hover {
                background-color: #007bff;
                color: #fff;
            }

            /* Nút phân trang hiện tại */
            .pagination .current {
                background-color: #007bff;
                color: #fff;
                font-weight: bold;
            }

            /* Các nút phân trang bị vô hiệu hóa */
            .pagination .disabled {
                background-color: #e9ecef;
                color: #6c757d;
                pointer-events: none;
            }

            /* Nút "Next" và "Previous" */
            .pagination .prev, .pagination .next {
                padding: 8px 16px;
                font-weight: bold;
                background-color: #f1f1f1;
                color: #333;
            }

            .pagination .prev:hover, .pagination .next:hover {
                background-color: #007bff;
                color: white;
            }

            /* Nút "First" và "Last" */
            .pagination .first, .pagination .last {
                padding: 8px 16px;
                background-color: #f1f1f1;
                color: #333;
            }

            .pagination .first:hover, .pagination .last:hover {
                background-color: #007bff;
                color: white;
            }
        /* Responsive */
        @media screen and (max-width: 768px) {
            .order-table, .order-details-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .order-table th, .order-table td,
            .order-details-table th, .order-details-table td {
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

            .form-popup {
                width: 95%;
                padding: 20px;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'admin_order_management_styles');

// Đăng ký jQuery và các script cần thiết
function admin_order_enqueue_scripts() {
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'admin_order_enqueue_scripts');
add_action('admin_enqueue_scripts', 'admin_order_enqueue_scripts');
?>