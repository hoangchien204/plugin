<?php
/*
Plugin Name: Admin Product Manager
Description: Plugin quản lý sản phẩm cho admin với chức năng thêm, sửa, xóa.
Version: 1.4
Author: Bạn
*/

// Hiển thị danh sách sản phẩm
function apm_display_admin_products() {
    if (!current_user_can('manage_options')) {
        return 'Bạn không có quyền truy cập trang này.';
    }

    ob_start();

    global $wpdb;
    $products = $wpdb->get_results("SELECT * FROM wp_hanghoa");

    if ($products) {
        foreach ($products as $product) {
            $image_path = !empty($product->Hinh) ? esc_url(get_stylesheet_directory_uri() . '/img/' . $product->Hinh) : esc_url(get_stylesheet_directory_uri() . '/img/placeholder.jpg');
            $is_deleted = ((int)$product->TrangThai === 0);
            ?>
            <div class="product-item <?php echo $is_deleted ? 'product-deleted' : ''; ?>">
                <img src="<?= $image_path ?>" alt="Ảnh sản phẩm" />
                <h3><?= esc_html($product->TenHH) ?></h3>
                <p>Giá: <?= number_format($product->DonGia, 0, ',', '.') ?>đ</p>
                <?php if (!$is_deleted) { ?>
                    <button class="btn-edit" data-id="<?= esc_attr($product->MaHH) ?>" onclick="openEditForm(<?= esc_attr($product->MaHH) ?>)">Sửa</button>
                    <button class="btn-delete" data-id="<?= esc_attr($product->MaHH) ?>" onclick="deleteProduct(<?= esc_attr($product->MaHH) ?>)">Xóa</button>
                <?php } else { ?>
                    <button class="btn-restore" data-id="<?= esc_attr($product->MaHH) ?>" onclick="restoreProduct(<?= esc_attr($product->MaHH) ?>)">Khôi phục</button>
                <?php } ?>
            </div>
            <?php
        }
    } else {
        echo "Không có sản phẩm nào.";
    }

    return ob_get_clean();
}

// Lọc sản phẩm (AJAX)
function filter_products() {
    global $wpdb;

    $category = isset($_POST['category']) ? $_POST['category'] : '';
    $sort_name = isset($_POST['sort_name']) ? $_POST['sort_name'] : '';

    $query = "SELECT * FROM wp_hanghoa WHERE 1=1";

    if ($category) {
        $query .= " AND MaLoai = " . esc_sql($category);
    }

    if ($sort_name) {
        $order = ($sort_name == 'asc') ? 'ASC' : 'DESC';
        $query .= " ORDER BY TenHH " . $order;
    }

    $products = $wpdb->get_results($query);

    if ($products) {
        foreach ($products as $product) {
            $image_path = !empty($product->Hinh) ? esc_url(get_stylesheet_directory_uri() . '/img/' . $product->Hinh) : esc_url(get_stylesheet_directory_uri() . '/img/placeholder.jpg');
            $is_deleted = ((int)$product->TrangThai === 0);
            echo '<div class="product-item ' . ($is_deleted ? 'product-deleted' : '') . '">';
            echo '<img src="' . $image_path . '" alt="Ảnh sản phẩm" />';
            echo '<h3>' . esc_html($product->TenHH) . '</h3>';
            echo '<p>Giá: ' . number_format($product->DonGia, 0, ',', '.') . 'đ</p>';
            if (!$is_deleted) {
                echo '<button class="btn-edit" data-id="' . esc_attr($product->MaHH) . '" onclick="openEditForm(' . esc_attr($product->MaHH) . ')">Sửa</button>';
                echo '<button class="btn-delete" data-id="' . esc_attr($product->MaHH) . '" onclick="deleteProduct(' . esc_attr($product->MaHH) . ')">Xóa</button>';
            } else {
                echo '<button class="btn-restore" data-id="' . esc_attr($product->MaHH) . '" onclick="restoreProduct(' . esc_attr($product->MaHH) . ')">Khôi phục</button>';
            }
            echo '</div>';
        }
    } else {
        echo "Không có sản phẩm nào.";
    }

    die();
}

// Thêm sản phẩm
function handle_add_product() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Bạn không có quyền thêm sản phẩm.']);
        return;
    }

    if (isset($_POST['tenhh'], $_POST['maloai'], $_POST['dongia'])) {
        global $wpdb;

        $tenhh = sanitize_text_field($_POST['tenhh']);
        $maloai = intval($_POST['maloai']);
        $motadonvi = sanitize_text_field($_POST['motadonvi'] ?? '');
        $dongia = floatval($_POST['dongia']);
        $mota = sanitize_textarea_field($_POST['mota'] ?? '');
        $soluongtonkho = intval($_POST['soluongtonkho'] ?? 0);

        // Kiểm tra dữ liệu bắt buộc
        if (empty($tenhh)) {
            wp_send_json_error(['message' => 'Tên sản phẩm không được để trống.']);
            return;
        }
        if ($maloai <= 0) {
            wp_send_json_error(['message' => 'Vui lòng chọn loại sản phẩm hợp lệ.']);
            return;
        }
        if ($dongia < 0) {
            wp_send_json_error(['message' => 'Đơn giá không được âm.']);
            return;
        }
        if ($soluongtonkho < 0) {
            wp_send_json_error(['message' => 'Số lượng tồn kho không được âm.']);
            return;
        }

        // Xử lý ảnh tải lên (không bắt buộc)
        $hinh_path = '';
        if (isset($_FILES['hinh']) && $_FILES['hinh']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['hinh']['type'], $allowed_types)) {
                $errors[] = 'Chỉ cho phép tải lên các định dạng ảnh JPEG, PNG hoặc GIF.';
            } else {
                $upload_dir = wp_upload_dir();
                // Tạo thư mục 'fruits' nếu chưa tồn tại
                $fruits_dir = $upload_dir['path'] . '/fruits';
                if (!file_exists($fruits_dir)) {
                    wp_mkdir_p($fruits_dir);
                }
                // Tạo tên tệp duy nhất
                $file_name = wp_unique_filename($fruits_dir, $_FILES['hinh']['name']);
                $target_file = $fruits_dir . '/' . $file_name;
                if (move_uploaded_file($_FILES['hinh']['tmp_name'], $target_file)) {
                    $hinh_path = 'fruits/' . $file_name; // Lưu dạng fruits/guava.png
                } else {
                    $errors[] = 'Có lỗi khi tải ảnh lên.';
                }
            }
        } else {
            $errors[] = 'Vui lòng chọn một ảnh cho sản phẩm.';
        }

        // Thêm sản phẩm vào bảng wp_hanghoa
        $result = $wpdb->insert(
            'wp_hanghoa',
            array(
                'TenHH' => $tenhh,
                'MaLoai' => $maloai,
                'MoTaDonVi' => $motadonvi ?: null,
                'DonGia' => $dongia,
                'Hinh' => $hinh_path ?: null,
                'NgaySX' => current_time('mysql'),
                'GiamGia' => 0.00,
                'SoLanXem' => 0,
                'MoTa' => $mota ?: null,
                'SoLanMua' => 0,
                'SoLuongTonKho' => $soluongtonkho,
                'TrangThai' => 1
            ),
            array(
                '%s', '%d', '%s', '%f', '%s', '%s', '%f', '%d', '%s', '%d', '%d', '%d'
            )
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Sản phẩm đã được thêm thành công!']);
        } else {
            wp_send_json_error(['message' => 'Thêm sản phẩm không thành công. Lỗi: ' . $wpdb->last_error]);
        }
    } else {
        wp_send_json_error(['message' => 'Vui lòng điền đầy đủ thông tin.']);
    }

    die();
}

// Sửa sản phẩm (AJAX)
function handle_edit_product() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Bạn không có quyền sửa sản phẩm.']);
        return;
    }

    if (isset($_POST['mahh'], $_POST['tenhh'], $_POST['maloai'], $_POST['dongia'])) {
        global $wpdb;

        $mahh = intval($_POST['mahh']);
        $tenhh = sanitize_text_field($_POST['tenhh']);
        $maloai = intval($_POST['maloai']);
        $motadonvi = sanitize_text_field($_POST['motadonvi']);
        $dongia = floatval($_POST['dongia']);
        $mota = sanitize_textarea_field($_POST['mota']);
        $soluongtonkho = intval($_POST['soluongtonkho']);

        // Kiểm tra dữ liệu
        if (empty($tenhh)) {
            wp_send_json_error(['message' => 'Tên sản phẩm không được để trống.']);
            return;
        }
        if ($maloai <= 0) {
            wp_send_json_error(['message' => 'Vui lòng chọn loại sản phẩm hợp lệ.']);
            return;
        }

        // Xử lý ảnh tải lên (nếu có)
        $hinh_path = $_POST['current_hinh'];
        if (isset($_FILES['hinh']) && $_FILES['hinh']['error'] == 0) {
            $upload_dir = wp_upload_dir();
            $target_file = $upload_dir['path'] . '/' . basename($_FILES['hinh']['name']);
            if (move_uploaded_file($_FILES['hinh']['tmp_name'], $target_file)) {
                $hinh_path = basename($_FILES['hinh']['name']);
            } else {
                wp_send_json_error(['message' => 'Có lỗi khi tải ảnh lên.']);
                return;
            }
        }

        // Cập nhật sản phẩm
        $result = $wpdb->update(
            'wp_hanghoa',
            array(
                'TenHH' => $tenhh,
                'MaLoai' => $maloai,
                'MoTaDonVi' => $motadonvi ?: null,
                'DonGia' => $dongia,
                'Hinh' => $hinh_path ?: null,
                'MoTa' => $mota ?: null,
                'SoLuongTonKho' => $soluongtonkho
            ),
            array('MaHH' => $mahh),
            array('%s', '%d', '%s', '%f', '%s', '%s', '%d'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Sản phẩm đã được cập nhật thành công!']);
        } else {
            wp_send_json_error(['message' => 'Cập nhật sản phẩm không thành công. Lỗi: ' . $wpdb->last_error]);
        }
    } else {
        wp_send_json_error(['message' => 'Vui lòng điền đầy đủ thông tin.']);
    }

    die();
}

// Xóa sản phẩm 
function handle_delete_product() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Bạn không có quyền xóa sản phẩm.']);
        return;
    }

    if (isset($_POST['mahh'])) {
        global $wpdb;
        $mahh = intval($_POST['mahh']);

        // Kiểm tra sản phẩm có tồn tại trong bất kỳ đơn hàng nào chưa
        $order_count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM wp_chitiethd WHERE MaHH = %d", $mahh)
        );

        if ($order_count > 0) {
            // XÓA MỀM nếu sản phẩm đã từng xuất hiện trong đơn hàng
            $result = $wpdb->update(
                'wp_hanghoa',
                ['TrangThai' => 0],
                ['MaHH' => $mahh],
                ['%d'],
                ['%d']
            );

            if ($result !== false) {
                wp_send_json_success(['message' => 'Sản phẩm đã được ẩn thành công (do có trong đơn hàng).']);
            } else {
                wp_send_json_error(['message' => 'Ẩn sản phẩm không thành công. Lỗi: ' . $wpdb->last_error]);
            }
        } else {
            // XÓA CỨNG nếu sản phẩm chưa từng có trong đơn hàng nào
            $deleted = $wpdb->delete(
                'wp_hanghoa',
                ['MaHH' => $mahh],
                ['%d']
            );

            if ($deleted !== false) {
                wp_send_json_success(['message' => 'Sản phẩm đã được xóa vĩnh viễn vì chưa có trong đơn hàng.']);
            } else {
                wp_send_json_error(['message' => 'Xóa vĩnh viễn không thành công. Lỗi: ' . $wpdb->last_error]);
            }
        }
    } else {
        wp_send_json_error(['message' => 'Thiếu mã sản phẩm cần xóa.']);
    }

    die();
}

// Khôi phục sản phẩm (AJAX)
function handle_restore_product() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Bạn không có quyền khôi phục sản phẩm.']);
        return;
    }

    if (isset($_POST['mahh'])) {
        global $wpdb;
        $mahh = intval($_POST['mahh']);

        $result = $wpdb->update(
            'wp_hanghoa',
            ['TrangThai' => 1],
            ['MaHH' => $mahh],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Sản phẩm đã được khôi phục thành công.']);
        } else {
            wp_send_json_error(['message' => 'Khôi phục sản phẩm không thành công. Lỗi: ' . $wpdb->last_error]);
        }
    } else {
        wp_send_json_error(['message' => 'Thiếu mã sản phẩm cần khôi phục.']);
    }

    die();
}

// Hiển thị danh sách sản phẩm trong bảng
function render_product_list() {
    global $wpdb;
    $products = $wpdb->get_results("SELECT * FROM wp_hanghoa");

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Mã HH</th><th>Tên HH</th><th>Giá</th><th>Trạng thái</th><th>Hành động</th></tr></thead>';
    echo '<tbody>';

    foreach ($products as $product) {
        $is_deleted = ((int)$product->TrangThai === 0);
        echo '<tr class="' . ($is_deleted ? 'product-deleted' : '') . '">';
        echo '<td>' . esc_html($product->MaHH) . '</td>';
        echo '<td>' . esc_html($product->TenHH) . '</td>';
        echo '<td>' . number_format($product->DonGia, 0, ',', '.') . 'đ</td>';
        echo '<td>' . ($is_deleted ? 'Đã xóa' : 'Hoạt động') . '</td>';
        echo '<td>';
        echo '<form method="post" style="display:inline;">';
        echo '<input type="hidden" name="mahh" value="' . esc_attr($product->MaHH) . '">';
        if ($is_deleted) {
            echo '<input type="submit" name="restore_product" value="Khôi phục" class="button button-secondary">';
        } else {
            echo '<input type="submit" name="delete_product" value="Xóa" class="button button-danger" onclick="return confirm(\'Bạn có chắc chắn muốn xóa?\')">';
        }
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

// Lấy thông tin sản phẩm để hiển thị trong form sửa (AJAX)
function get_product_data() {
    if (isset($_POST['mahh'])) {
        global $wpdb;
        $mahh = intval($_POST['mahh']);
        $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_hanghoa WHERE MaHH = %d AND TrangThai = 1", $mahh));

        if ($product) {
            wp_send_json_success([
                'TenHH' => $product->TenHH,
                'MaLoai' => $product->MaLoai,
                'MoTaDonVi' => $product->MoTaDonVi,
                'DonGia' => $product->DonGia,
                'Hinh' => $product->Hinh,
                'MoTa' => $product->MoTa,
                'SoLuongTonKho' => $product->SoLuongTonKho
            ]);
        } else {
            wp_send_json_error(['message' => 'Không tìm thấy sản phẩm.']);
        }
    }

    die();
}

// Đăng ký các hành động AJAX
add_action('wp_ajax_filter_products', 'filter_products');
add_action('wp_ajax_nopriv_filter_products', 'filter_products');
add_action('wp_ajax_add_product', 'handle_add_product');
add_action('wp_ajax_edit_product', 'handle_edit_product');
add_action('wp_ajax_delete_product', 'handle_delete_product');
add_action('wp_ajax_restore_product', 'handle_restore_product');
add_action('wp_ajax_get_product_data', 'get_product_data');

function enqueue_admin_product_assets() {
    // Enqueue jQuery (WordPress đã có sẵn)
    wp_enqueue_script('jquery');

    // Định nghĩa ajaxurl để sử dụng trong JavaScript
    wp_localize_script('jquery', 'ajax_object', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));

    // Thêm JavaScript cho xử lý xóa và khôi phục sản phẩm
    wp_add_inline_script('jquery', '
        function deleteProduct(mahh) {
            if (confirm("Bạn có chắc chắn muốn xóa sản phẩm này?")) {
                jQuery.ajax({
                    url: ajax_object.ajaxurl,
                    type: "POST",
                    data: {
                        action: "delete_product",
                        mahh: mahh
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        }

        function restoreProduct(mahh) {
            if (confirm("Bạn có chắc chắn muốn khôi phục sản phẩm này?")) {
                jQuery.ajax({
                    url: ajax_object.ajaxurl,
                    type: "POST",
                    data: {
                        action: "restore_product",
                        mahh: mahh
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        }
    ');
}

add_action('wp_enqueue_scripts', 'enqueue_admin_product_assets'); // Frontend
add_action('admin_enqueue_scripts', 'enqueue_admin_product_assets'); // Admin

add_shortcode('admin_products', 'apm_display_admin_products');
?>