<?php
/**
 * Plugin Name: Revenue Report Plugin
 * Description: A plugin to generate revenue reports by product category in WordPress.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

// ===============================
// LẤY DỮ LIỆU BÁO CÁO
// ===============================
function get_revenue_report_data() {
    global $wpdb;

    try {
        $fromDate = isset($_GET['fromDate']) ? sanitize_text_field($_GET['fromDate']) : date('Y-m-01');
        $toDate = isset($_GET['toDate']) ? sanitize_text_field($_GET['toDate']) : date('Y-m-d');

        $fromDateTime = DateTime::createFromFormat('Y-m-d', $fromDate);
        $toDateTime = DateTime::createFromFormat('Y-m-d', $toDate);
        if (!$fromDateTime || !$toDateTime || $fromDateTime > $toDateTime) {
            $fromDate = date('Y-m-01');
            $toDate = date('Y-m-d');
        }

        $data = [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'tongDoanhThu' => 0,
            'tongChiPhi' => 0,
            'loiNhuan' => 0,
            'doanhThuLoai' => [],
            'labels' => [],
            'pieData' => [],
            'barData' => [],
        ];

        $query = "
            SELECT 
                l.MaLoai,
                l.TenLoai,
                SUM(cthd.DonGia * cthd.SoLuong) AS DoanhThu,
                SUM(cthd.DonGia * cthd.SoLuong) * 0.7 AS TongChiPhi,
                SUM(cthd.DonGia * cthd.SoLuong) * 0.3 AS LoiNhuan,
                SUM(cthd.SoLuong) AS SoLuongBan
            FROM {$wpdb->prefix}hoadon hd
            JOIN {$wpdb->prefix}chitiethd cthd ON hd.MaHD = cthd.MaHD
            JOIN {$wpdb->prefix}hanghoa hh ON cthd.MaHH = hh.MaHH
            JOIN {$wpdb->prefix}loai l ON hh.MaLoai = l.MaLoai
            WHERE hd.NgayDat BETWEEN %s AND %s
              AND hd.MaTrangThai = 3
            GROUP BY l.MaLoai, l.TenLoai
            ORDER BY DoanhThu DESC
        ";

        $results = $wpdb->get_results(
            $wpdb->prepare($query, $fromDate . ' 00:00:00', $toDate . ' 23:59:59')
        );

        if (empty($results)) {
            return $data;
        }

        $data['tongDoanhThu'] = array_sum(array_column($results, 'DoanhThu'));
        $data['tongChiPhi'] = array_sum(array_column($results, 'TongChiPhi'));
        $data['loiNhuan'] = $data['tongDoanhThu'] - $data['tongChiPhi'];

        $data['doanhThuLoai'] = array_map(function($item) use ($data) {
            $soLuongBan = intval($item->SoLuongBan);
            $doanhThu = floatval($item->DoanhThu);
            $tongChiPhi = floatval($item->TongChiPhi);
            $loiNhuan = floatval($item->LoiNhuan);
            return [
                'LoaiSP' => $item->TenLoai,
                'SoLuongBan' => $soLuongBan,
                'DoanhThu' => $doanhThu,
                'TongChiPhi' => $tongChiPhi,
                'LoiNhuan' => $loiNhuan,
                'TyTrong' => $data['tongDoanhThu'] > 0 ? ($doanhThu * 100 / $data['tongDoanhThu']) : 0,
                'TrungBinhSP' => $soLuongBan > 0 ? ($doanhThu / $soLuongBan) : 0,
            ];
        }, $results);

        $data['labels'] = array_column($data['doanhThuLoai'], 'LoaiSP');
        $data['pieData'] = array_column($data['doanhThuLoai'], 'DoanhThu');
        $data['barData'] = array_column($data['doanhThuLoai'], 'SoLuongBan');

        return $data;

    } catch (Exception $ex) {
        error_log('Revenue Report Error: ' . $ex->getMessage());
        return [
            'fromDate' => date('Y-m-01'),
            'toDate' => date('Y-m-d'),
            'tongDoanhThu' => 0,
            'tongChiPhi' => 0,
            'loiNhuan' => 0,
            'doanhThuLoai' => [],
            'labels' => [],
            'pieData' => [],
            'barData' => [],
        ];
    }
}

// ===============================
// TRANG ADMIN MENU
// ===============================
function rrp_add_admin_menu() {
    add_menu_page(
        'Revenue Report',
        'Revenue Report',
        'manage_options',
        'revenue-report',
        'rrp_render_admin_page',
        'dashicons-chart-pie',
        6
    );
}
add_action('admin_menu', 'rrp_add_admin_menu');

function rrp_render_admin_page() {
    echo do_shortcode('[revenue_report]');
}

// ===============================
// SHORTCODE FRONTEND & BACKEND
// ===============================

function revenue_report_shortcode() {
    $reportData = get_revenue_report_data();

    ob_start();
    ?>
    <div class="wrap">
        <h2>Báo cáo doanh thu theo loại sản phẩm</h2>
        <p>Từ ngày: <?php echo esc_html($reportData['fromDate']); ?> đến ngày: <?php echo esc_html($reportData['toDate']); ?></p>
        <h3>Tổng doanh thu: <?php echo number_format($reportData['tongDoanhThu'], 0, ',', '.'); ?> đ</h3>
        <h3>Lợi nhuận: <?php echo number_format($reportData['loiNhuan'], 0, ',', '.'); ?> đ</h3>

        <table style="width:100%; border-collapse:collapse;" border="1">
            <thead>
                <tr>
                    <th>Loại SP</th>
                    <th>Số lượng bán</th>
                    <th>Doanh thu</th>
                    <th>Chi phí</th>
                    <th>Lợi nhuận</th>
                    <th>Tỷ trọng</th>
                    <th>TB / SP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData['doanhThuLoai'] as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item['LoaiSP']); ?></td>
                        <td><?php echo intval($item['SoLuongBan']); ?></td>
                        <td><?php echo number_format($item['DoanhThu'], 0, ',', '.'); ?> đ</td>
                        <td><?php echo number_format($item['TongChiPhi'], 0, ',', '.'); ?> đ</td>
                        <td><?php echo number_format($item['LoiNhuan'], 0, ',', '.'); ?> đ</td>
                        <td><?php echo round($item['TyTrong'], 2); ?>%</td>
                        <td><?php echo number_format($item['TrungBinhSP'], 0, ',', '.'); ?> đ</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('revenue_report', 'revenue_report_shortcode');
