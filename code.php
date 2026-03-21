/*
Plugin Name: Image ALT Cannibalization PRO
Description: Grouped ALT checker with pagination, history, featured/gallery detection, RankMath SEO keyword.
Version: 5.1
*/

/* ---------- CREATE HISTORY TABLE AUTOMATICALLY ---------- */

add_action('init', function () {
    global $wpdb;
    $table = $wpdb->prefix . 'alt_history';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {

        $sql = "CREATE TABLE $table (
            id INT AUTO_INCREMENT,
            image_id INT,
            old_alt TEXT,
            new_alt TEXT,
            updated_by VARCHAR(100),
            updated_at DATETIME,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
});


/* ---------- ADMIN MENU ---------- */

add_action('admin_menu', function () {
    add_menu_page(
        'Image ALT PRO',
        'Image ALT PRO',
        'manage_options',
        'image-alt-pro',
        'render_image_alt_pro',
        'dashicons-format-image',
        25
    );

    add_submenu_page(
        'image-alt-pro',
        'ALT History',
        'ALT History',
        'manage_options',
        'image-alt-history',
        'render_alt_history_page'
    );
});


/* ---------- SAVE ALT + HISTORY ---------- */

add_action('admin_post_update_image_alt', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update_alt_nonce')) {
        wp_die('Security check failed');
    }

    global $wpdb;

    $image_id = intval($_POST['image_id']);
    $new_alt  = sanitize_text_field($_POST['new_alt']);
    $old_alt  = get_post_meta($image_id, '_wp_attachment_image_alt', true);

    update_post_meta($image_id, '_wp_attachment_image_alt', $new_alt);

    $user = wp_get_current_user();

    $wpdb->insert(
        $wpdb->prefix . 'alt_history',
        [
            'image_id'   => $image_id,
            'old_alt'    => $old_alt,
            'new_alt'    => $new_alt,
            'updated_by' => $user->display_name,
            'updated_at' => current_time('mysql')
        ]
    );

    wp_redirect(admin_url('admin.php?page=image-alt-pro&updated=1'));
    exit;
});


/* ---------- IMAGE TYPE ---------- */

function get_image_usage_type($image_id, $parent_id) {

    if (get_post_thumbnail_id($parent_id) == $image_id) {
        return '<span style="color:green;font-weight:bold;">Featured</span>';
    }

    $gallery = get_post_meta($parent_id, '_product_image_gallery', true);
    if ($gallery) {
        $gallery_ids = explode(',', $gallery);
        if (in_array($image_id, $gallery_ids)) {
            return '<span style="color:purple;font-weight:bold;">Gallery</span>';
        }
    }

    return 'Content';
}


/* ---------- RANKMATH PRIMARY KEYWORD ---------- */

function get_rankmath_focus_keyword($post_id) {
    $keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);

    if ($keyword) {
        $keywords = explode(',', $keyword);
        return trim($keywords[0]);
    }

    return '-';
}


/* ---------- MAIN PAGE ---------- */

function render_image_alt_pro() {

    global $wpdb;

    echo '<div class="wrap">';
    echo '<h1>Image ALT Cannibalization PRO</h1>';

    if (isset($_GET['updated'])) {
        echo '<div class="updated notice"><p>ALT Updated + History Saved.</p></div>';
    }

    $images = $wpdb->get_results("
        SELECT p.ID,
               p.post_parent,
               pm.meta_value AS alt_text
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm
            ON p.ID = pm.post_id
        WHERE p.post_type = 'attachment'
        AND p.post_mime_type LIKE 'image/%'
        AND p.post_parent != 0
        AND pm.meta_key = '_wp_attachment_image_alt'
        AND pm.meta_value != ''
        ORDER BY pm.meta_value ASC
    ");

    if (!$images) {
        echo '<p>No images found.</p></div>';
        return;
    }

    $grouped = [];

    foreach ($images as $img) {
        $grouped[$img->alt_text][] = $img;
    }

    foreach ($grouped as $alt => $imgs) {
        if (count($imgs) <= 1) {
            unset($grouped[$alt]);
        }
    }

    foreach ($grouped as $alt => $imgs) {

        echo '<div style="background:#fff;padding:15px;margin-bottom:20px;border:1px solid #ddd;">';
        echo '<h2 style="color:#0073aa;">ALT: ' . esc_html($alt) . ' (Used ' . count($imgs) . ' times)</h2>';

        foreach ($imgs as $img) {

            $image_url = wp_get_attachment_url($img->ID);
            $parent = get_post($img->post_parent);
            $focus_keyword = get_rankmath_focus_keyword($parent->ID);

            echo '<div style="display:flex;gap:15px;margin-bottom:15px;align-items:center;">';

            echo '<img src="' . esc_url($image_url) . '" width="80">';

            echo '<div style="flex:1;">';
            echo '<strong>Image Type:</strong> ' . get_image_usage_type($img->ID, $parent->ID) . '<br>';
            echo '<strong>Primary Focus Keyword:</strong> ' . esc_html($focus_keyword) . '<br>';

            echo '<strong>Used In:</strong><br>';

            $post_type = get_post_type($parent->ID);

            if ($post_type == 'product') {
                echo '<a href="' . get_permalink($parent->ID) . '" target="_blank">View Product</a> | ';
                echo '<a href="' . admin_url('post.php?post=' . $parent->ID . '&action=edit') . '" target="_blank">Edit Product</a><br><br>';
            } else {
                echo '<a href="' . get_permalink($parent->ID) . '" target="_blank">View Post</a> | ';
                echo '<a href="' . admin_url('post.php?post=' . $parent->ID . '&action=edit') . '" target="_blank">Edit Post</a><br><br>';
            }

            echo '</div>';

            echo '<form method="POST" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('update_alt_nonce');
            echo '<input type="hidden" name="action" value="update_image_alt">';
            echo '<input type="hidden" name="image_id" value="' . $img->ID . '">';
            echo '<input type="text" name="new_alt" value="' . esc_attr($alt) . '" style="width:220px;">';
            echo '<br><br>';
            echo '<input type="submit" class="button button-primary" value="Update ALT">';
            echo '</form>';

            echo '</div>';
        }

        echo '</div>';
    }

    echo '</div>';
}


/* ---------- HISTORY PAGE ---------- */

function render_alt_history_page() {

    global $wpdb;

    echo '<div class="wrap">';
    echo '<h1>ALT Update History</h1>';

    $history = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}alt_history
        ORDER BY updated_at DESC
        LIMIT 100
    ");

    echo '<table class="widefat">';
    echo '<tr><th>Image</th><th>Old ALT</th><th>New ALT</th><th>User</th><th>Date</th></tr>';

    foreach ($history as $h) {
        $img = wp_get_attachment_url($h->image_id);
        echo '<tr>';
        echo '<td><img src="' . esc_url($img) . '" width="50"></td>';
        echo '<td>' . esc_html($h->old_alt) . '</td>';
        echo '<td>' . esc_html($h->new_alt) . '</td>';
        echo '<td>' . esc_html($h->updated_by) . '</td>';
        echo '<td>' . esc_html($h->updated_at) . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</div>';
}
