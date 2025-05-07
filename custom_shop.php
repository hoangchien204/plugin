<?php
/**
 * Plugin Name: Shop
 * Description: Hiển thị danh sách sản phẩm với hình ảnh, giá và lượt bán từ bảng wp_hanghoa.
 * Version: 1.0
 * Author: Bạn
 */

if (!defined('ABSPATH')) exit;

// Đăng ký shortcode [custom_shop]
function mpl_render_product_list($atts) {
    global $wpdb;  // Sử dụng $wpdb để truy vấn cơ sở dữ liệu

    ob_start();

    // Truy vấn dữ liệu từ bảng wp_hanghoa
    $results = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}hanghoa
        ORDER BY MaHH DESC
        LIMIT 12
    ");

    if ($results) :
        echo '<div class="mpl-product-grid" style="display:flex;flex-wrap:wrap;gap:20px;">';
        foreach ($results as $product) :
            // Lấy dữ liệu từ bảng wp_hanghoa
            $product_name = $product->TenHH;
            $price = $product->DonGia;
            $sales = $product->SoLanMua;
            $image_path = get_template_directory_uri() . '/img/' . $product->Hinh;  // Đảm bảo bạn lưu ảnh trong thư mục đúng

            ?>
            <div class="mpl-product-item" style="width:200px;border:1px solid #ddd;padding:10px;">
                <a href="#">
                    <!-- Hiển thị hình ảnh -->
                    <?php if ($product->Hinh) : ?>
                        <div class="mpl-thumbnail">
                            <img src="<?php echo $image_path; ?>" alt="<?php echo esc_attr($product_name); ?>" style="width:100%;">
                        </div>
                    <?php endif; ?>
                    <!-- Tên sản phẩm -->
                    <h3 class="mpl-title" style="font-size:16px;"><?php echo esc_html($product_name); ?></h3>
                    <!-- Giá sản phẩm -->
                    <p class="mpl-price"><?php echo number_format($price, 0, ',', '.'); ?> VND</p>
                    <!-- Lượt bán -->
                    <p class="mpl-sales">Đã bán: <?php echo $sales ? $sales : 0; ?></p>
                </a>
            </div>
            <?php
        endforeach;
        echo '</div>';
    else :
        echo '<p>Không có sản phẩm nào.</p>';
    endif;

    return ob_get_clean();
}

add_shortcode('custom_shop', 'mpl_render_product_list');
