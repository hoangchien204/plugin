<?php
/**
 * Plugin Name: Shop
 * Description: Hiển thị danh sách sản phẩm với hình ảnh, giá và lượt bán từ bảng wp_hanghoa.
 * Version: 1.0
 * Author: Bạn
 */

if (!defined('ABSPATH')) exit;

// Hàm lấy danh sách danh mục
function myshop_get_categories() {
    global $wpdb;
    return $wpdb->get_results("SELECT MaLoai, TenLoai FROM {$wpdb->prefix}loai");
}
function myshop_get_category_name($maloai) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare("SELECT TenLoai FROM {$wpdb->prefix}loaihanghoa WHERE MaLoai = %d", $maloai));
}

// Hàm lấy sản phẩm (có thể lọc theo mã loại)
function myshop_get_products($maloai = 0, $limit = 0, $offset = 0) {
    global $wpdb;
    $where = "WHERE TrangThai = 1";
    if ($maloai > 0) {
        $where .= " AND MaLoai = " . intval($maloai);
    }

    $limit_offset = '';
    if ($limit > 0) {
        $limit_offset = "LIMIT " . intval($limit);
        if ($offset > 0) {
            $limit_offset .= " OFFSET " . intval($offset);
        }
    }

    return $wpdb->get_results("
        SELECT MaHH, TenHH, DonGia, SoLanMua, Hinh, SoLuongTonKho
        FROM {$wpdb->prefix}hanghoa
        $where
        ORDER BY MaHH DESC
        $limit_offset
    ");
}
