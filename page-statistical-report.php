<?php
/*
Template Name: Báo cáo Doanh thu
*/

// Kiểm tra quyền truy cập (chỉ admin)
if (!current_user_can('manage_options')) {
    wp_die('Bạn không có quyền truy cập trang này.');
}

// Kiểm tra hàm lấy dữ liệu từ plugin có tồn tại không
if (!function_exists('get_revenue_report_data')) {
    wp_die('Plugin "Revenue Report Plugin" không được kích hoạt hoặc không tồn tại.');
}

// Xử lý xuất file CSV
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $fromDate = isset($_GET['fromDate']) ? sanitize_text_field($_GET['fromDate']) : date('Y-m-01');
    $toDate = isset($_GET['toDate']) ? sanitize_text_field($_GET['toDate']) : date('Y-m-d');
    
    $data = get_revenue_report_data($fromDate, $toDate);
    
    // Khởi tạo dữ liệu mặc định nếu không hợp lệ
    if (!is_array($data)) {
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
    }

    // Thiết lập header cho file CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bao-cao-doanh-thu-' . $fromDate . '-den-' . $toDate . '.csv"');

    // Mở file output
    $output = fopen('php://output', 'w');

    // Thêm BOM để hỗ trợ UTF-8 trên Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Tiêu đề cột
    fputcsv($output, [
        'Loại sản phẩm',
        'Số lượng bán',
        'Doanh thu',
        'Chi phí',
        'Lợi nhuận',
        'Tỷ trọng',
        'TB/SP'
    ], ',', '"');

    // Dữ liệu
    foreach ($data['doanhThuLoai'] as $item) {
        // Chuẩn hóa dữ liệu trước khi xuất
        $row = [
            isset($item['LoaiSP']) ? str_replace(';', ',', (string)$item['LoaiSP']) : '', // Thay thế dấu ; để tránh lỗi cột
            isset($item['SoLuongBan']) ? (int)$item['SoLuongBan'] : 0,
            isset($item['DoanhThu']) ? (float)$item['DoanhThu'] : 0,
            isset($item['TongChiPhi']) ? (float)$item['TongChiPhi'] : 0,
            isset($item['LoiNhuan']) ? (float)$item['LoiNhuan'] : 0,
            isset($item['TyTrong']) ? (float)$item['TyTrong'] : 0,
            isset($item['TrungBinhSP']) ? (float)$item['TrungBinhSP'] : 0
        ];

        // Ghi dữ liệu vào file CSV
        fputcsv($output, $row, ',', '"');
    }

    fclose($output);
    exit;
}

// Lấy dữ liệu báo cáo từ plugin, có thể truyền tham số từ ngày đến ngày qua GET
$fromDate = isset($_GET['fromDate']) ? sanitize_text_field($_GET['fromDate']) : date('Y-m-01');
$toDate = isset($_GET['toDate']) ? sanitize_text_field($_GET['toDate']) : date('Y-m-d');

$data = get_revenue_report_data($fromDate, $toDate);

// Nếu dữ liệu không đúng định dạng, khởi tạo mặc định
if (!is_array($data)) {
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
}

// Debug dữ liệu (nên comment hoặc bỏ khi live)
error_log('Data: ' . print_r($data, true));

// Enqueue scripts & styles
wp_enqueue_script('jquery-datatables', 'https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js', ['jquery'], '1.13.1', true);
wp_enqueue_script('datatables-bootstrap', 'https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js', ['jquery-datatables'], '1.13.1', true);
wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
wp_enqueue_style('datatables-bootstrap-css', 'https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css');
wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');

wp_add_inline_script('chartjs', "
jQuery(document).ready(function($) {
    // Debug dữ liệu
    const labels = " . json_encode($data['labels'] ?? []) . ";
    const pieData = " . json_encode($data['pieData'] ?? []) . ";
    const barData = " . json_encode($data['barData'] ?? []) . ";
    console.log('Chart Data:', { labels, pieData, barData });

    // Khởi tạo DataTable
    $('#datatablesSimple').DataTable({
        pageLength: 10,
        language: {
            lengthMenu: 'Hiển thị _MENU_ mục mỗi trang',
            zeroRecords: 'Không tìm thấy dữ liệu',
            info: 'Hiển thị trang _PAGE_ của _PAGES_',
            infoEmpty: 'Không có dữ liệu',
            infoFiltered: '(lọc từ _MAX_ mục)',
            search: 'Tìm kiếm:',
            paginate: { first: 'Đầu', last: 'Cuối', next: 'Sau', previous: 'Trước' }
        }
    });

    // Kiểm tra dữ liệu cho biểu đồ
    if (!labels.length || !pieData.length || !barData.length) {
        console.warn('Không có dữ liệu để vẽ biểu đồ:', { labels, pieData, barData });
        $('#pieChartError').html('Không có dữ liệu để hiển thị biểu đồ tròn. Kiểm tra console để xem chi tiết.').show();
        $('#barChartError').html('Không có dữ liệu để hiển thị biểu đồ cột. Kiểm tra console để xem chi tiết.').show();
        return;
    }

    // Kiểm tra canvas
    const pieCanvas = document.getElementById('pieChart');
    const barCanvas = document.getElementById('barChart');
    if (!pieCanvas || !barCanvas) {
        console.error('Không tìm thấy canvas:', { pieCanvas: !!pieCanvas, barCanvas: !!barCanvas });
        if (!pieCanvas) $('#pieChartError').html('Không tìm thấy canvas biểu đồ tròn.').show();
        if (!barCanvas) $('#barChartError').html('Không tìm thấy canvas biểu đồ cột.').show();
        return;
    }

    // Vẽ biểu đồ tròn
    try {
        const pieCtx = pieCanvas.getContext('2d');
        new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Doanh thu (đ)',
                    data: pieData.map(val => parseFloat(val) || 0),
                    backgroundColor: [
                        '#ff6384', '#36a2eb', '#ffce56', '#4bc0c0', '#9966ff', '#ff9f40'
                    ],
                    borderColor: [
                        '#ff6384', '#36a2eb', '#ffce56', '#4bc0c0', '#9966ff', '#ff9f40'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Doanh thu theo loại sản phẩm' }
                }
            }
        });
        console.log('Biểu đồ tròn đã được vẽ.');
    } catch (error) {
        console.error('Lỗi khi vẽ biểu đồ tròn:', error);
        $('#pieChartError').html('Lỗi vẽ biểu đồ tròn: ' + error.message).show();
    }

    // Vẽ biểu đồ cột
    try {
        const barCtx = barCanvas.getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Số lượng bán',
                    data: barData.map(val => parseInt(val) || 0),
                    backgroundColor: '#36a2eb',
                    borderColor: '#36a2eb',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Số lượng bán theo loại sản phẩm' }
                },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Số lượng' } },
                    x: { title: { display: true, text: 'Loại sản phẩm' } }
                }
            }
        });
        console.log('Biểu đồ cột đã được vẽ.');
    } catch (error) {
        console.error('Lỗi khi vẽ biểu đồ cột:', error);
        $('#barChartError').html('Lỗi vẽ biểu đồ cột: ' + error.message).show();
    }
});
");
?>

<div class="admin-wrapper">
    <?php get_template_part('admin-sidebar'); ?>

    <div class="admin-content">
        <main class="admin-main">
            <div class="container-fluid px-4">
                <!-- Filter Section -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row align-items-center">
                            <input type="hidden" name="page_id" value="<?php echo get_the_ID(); ?>">
                            <div class="col-md-3">
                                <label class="form-label" for="fromDate">Từ ngày</label>
                                <input id="fromDate" type="date" name="fromDate" class="form-control"
                                       value="<?php echo esc_attr($data['fromDate']); ?>" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="toDate">Đến ngày</label>
                                <input id="toDate" type="date" name="toDate" class="form-control"
                                       value="<?php echo esc_attr($data['toDate']); ?>" />
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary mt-4">
                                    <i class="fas fa-filter me-1"></i> Lọc
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="<?php echo esc_url(add_query_arg(['action' => 'export_csv', 'fromDate' => $fromDate, 'toDate' => $toDate])); ?>" class="btn btn-success mt-4">
                                    <i class="fas fa-download me-1"></i> Xuất file
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Overview Cards -->
                <div class="row">
                    <div class="col-xl-4">
                        <div class="card bg-primary text-white mb-4">
                            <div class="card-body">
                                <h4>Tổng doanh thu</h4>
                                <h2 class="mb-0"><?php echo number_format($data['tongDoanhThu'], 0, ',', '.'); ?> đ</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="card bg-danger text-white mb-4">
                            <div class="card-body">
                                <h4>Tổng chi phí</h4>
                                <h2 class="mb-0"><?php echo number_format($data['tongChiPhi'], 0, ',', '.'); ?> đ</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="card bg-success text-white mb-4">
                            <div class="card-body">
                                <h4>Lợi nhuận</h4>
                                <h2 class="mb-0"><?php echo number_format($data['loiNhuan'], 0, ',', '.'); ?> đ</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Chi tiết doanh thu theo loại
                    </div>
                    <div class="card-body">
                        <table id="datatablesSimple" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Loại sản phẩm</th>
                                    <th>Số lượng bán</th>
                                    <th>Doanh thu</th>
                                    <th>Chi phí</th>
                                    <th>Lợi nhuận</th>
                                    <th>Tỷ trọng</th>
                                    <th>TB/SP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['doanhThuLoai'] as $item) : ?>
                                    <tr>
                                        <td><?php echo esc_html($item['LoaiSP'] ?? ''); ?></td>
                                        <td><?php echo number_format($item['SoLuongBan'] ?? 0, 0, ',', '.'); ?></td>
                                        <td><?php echo number_format($item['DoanhThu'] ?? 0, 0, ',', '.'); ?> đ</td>
                                        <td><?php echo number_format($item['TongChiPhi'] ?? 0, 0, ',', '.'); ?> đ</td>
                                        <td><?php echo number_format($item['LoiNhuan'] ?? 0, 0, ',', '.'); ?> đ</td>
                                        <td><?php echo number_format($item['TyTrong'] ?? 0, 2, ',', '.'); ?>%</td>
                                        <td><?php echo number_format($item['TrungBinhSP'] ?? 0, 0, ',', '.'); ?> đ</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
/* Reset và thiết lập cơ bản */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    width: 100%;
    height: 100vh;
    overflow-x: hidden;
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f1f4f8;
    color: #333;
}

/* Canvas và container biểu đồ */
canvas#pieChart, canvas#barChart {
    display: block !important;
    width: 100% !important;
    height: 300px !important;
    min-height: 300px;
    visibility: visible !important;
    opacity: 1 !important;
}
.chart-container {
    width: 100%;
    height: 300px;
    position: relative;
}
.card-body {
    min-height: 110px !important;
    padding: 20px;
    position: relative;
}

/* Layout chính */
.admin-wrapper {
    display: flex;
    min-height: 100vh;
}

.admin-content {
    flex-grow: 1;
    padding: 30px;
    background: #f1f4f8;
    transition: margin-left 0.3s ease;
}

/* Hệ thống lưới (Grid System) */
.row {
    display: flex;
    flex-wrap: wrap;
    margin-left: -15px;
    margin-right: -15px;
}

[class*="col-"] {
    position: relative;
    width: 100%;
    padding-left: 15px;
    padding-right: 15px;
}

/* Định nghĩa các cột cho màn hình lớn (≥1200px) */
@media (min-width: 1200px) {
    .col-xl-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
    .col-xl-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

/* Định nghĩa các cột cho màn hình vừa (≥768px) */
@media (min-width: 768px) {
    .col-md-3 {
        flex: 0 0 25%;
        max-width: 25%;
    }
    .col-md-2 {
        flex: 0 0 16.666667%;
        max-width: 16.666667%;
    }
}

/* Responsive: Màn hình nhỏ hơn 1200px */
@media (max-width: 1199.98px) {
    .col-xl-4 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

/* Responsive: Màn hình nhỏ hơn 768px */
@media (max-width: 767.98px) {
    .col-xl-4, .col-xl-6, .col-md-3, .col-md-2 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

/* Tiêu đề và breadcrumb */
h1.mt-4 {
    font-size: 28px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 20px;
}

.breadcrumb {
    background: #fff;
    border-radius: 8px;
    padding: 10px 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    font-size: 14px;
}

.breadcrumb-item a {
    color: #007bff;
    text-decoration: none;
}

.breadcrumb-item.active {
    color: #6c757d;
}

/* Card (bao gồm bộ lọc và các thẻ tổng quan) */
.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
    background: #fff;
    transition: transform 0.2s ease;
}

.card:hover {
    transform: translateY(-5px);
}

.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 15px 20px;
    font-size: 16px;
    font-weight: 500;
    color: #333;
    display: flex;
    align-items: center;
}

.card-header i {
    margin-right: 8px;
    color: #007bff;
}

/* Bộ lọc ngày */
.form-label {
    font-size: 14px;
    font-weight: 500;
    color: #555;
    margin-bottom: 8px;
    display: block;
}

.form-control {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 10px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    width: 100%;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
    outline: none;
}

.btn-primary {
    background: #007bff;
    border: none;
    border-radius: 6px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.3s ease, transform 0.2s ease;
    width: 100%;
}

.btn-primary:hover {
    background: #0056b3;
    transform: translateY(-2px);
}

.btn-success {
    background: #28a745;
    border: none;
    border-radius: 6px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.3s ease, transform 0.2s ease;
    width: 100%;
    color: #fff;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-2px);
    color: #fff;
}

.btn-primary i, .btn-success i {
    margin-right: 5px;
}

.row.align-items-center {
    align-items: flex-end;
}

/* Thẻ tổng quan (Overview Cards) */
.bg-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
}

.bg-danger {
    background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%) !important;
}

.bg-success {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%) !important;
}

.text-white {
    color: #fff !important;
}

.card-body h4 {
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 10px;
    opacity: 0.9;
}

.card-body h2 {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
}

/* Bảng chi tiết */
.table {
    width: 100%;
    margin-bottom: 0;
    font-size: 14px;
    border-collapse: separate;
    border-spacing: 0;
}

.table-striped tbody tr:nth-of-type(odd) {
    background: #f8f9fa;
}

.table th, .table td {
    padding: 12px 15px;
    vertical-align: middle;
    border-top: 1px solid #e9ecef;
}

.table th {
    background: #f1f4f8;
    font-weight: 600;
    color: #333;
    text-transform: uppercase;
    font-size: 13px;
    letter-spacing: 0.5px;
}

.table td {
    color: #555;
}

.table tbody tr:hover {
    background: #e9ecef;
}

/* DataTables styling */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    margin-bottom: 15px;
}

.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 8px;
    font-size: 14px;
}

.dataTables_wrapper .dataTables_paginate {
    margin-top: 15px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 8px 12px;
    border-radius: 6px;
    margin: 0 2px;
    font-size: 14px;
    color: #007bff;
    border: 1px solid #d1d5db;
    background: #fff;
    transition: background 0.3s ease;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #007bff;
    color: #fff;
    border-color: #007bff;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #007bff;
    color: #fff;
    border-color: #007bff;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-content {
        padding: 15px;
    }
    h1.mt-4 {
        font-size: 24px;
    }
    .card-body h2 {
        font-size: 22px;
    }
    .table th, .table td {
        padding: 10px;
        font-size: 13px;
    }
    .form-control,
    .btn-primary,
    .btn-success {
        font-size: 13px;
        padding: 8px;
    }
}
</style>