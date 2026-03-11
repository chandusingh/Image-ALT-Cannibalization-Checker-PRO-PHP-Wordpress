/*
Plugin Name: Image ALT Cannibalization PRO
Description: Grouped ALT checker with pagination and inline editing.
Version: 2.0
*/

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
});

/* ---------- SAVE ALT INLINE ---------- */
add_action('admin_post_update_image_alt', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $image_id = intval($_POST['image_id']);
    $new_alt  = sanitize_text_field($_POST['new_alt']);

    update_post_meta($image_id, '_wp_attachment_image_alt', $new_alt);

    wp_redirect(admin_url('admin.php?page=image-alt-pro&updated=1'));
    exit;
});

/* ---------- MAIN PAGE ---------- */
function render_image_alt_pro() {

    global $wpdb;

    echo '<div class="wrap">';
    echo '<h1>Image ALT Cannibalization PRO</h1>';

    if (isset($_GET['updated'])) {
        echo '<div class="updated notice"><p>ALT Updated Successfully.</p></div>';
    }

    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Get all images WITH ALT only
    $images = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID,
               p.guid,
               p.post_parent,
               pm.meta_value AS alt_text
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm
            ON p.ID = pm.post_id
        WHERE p.post_type = 'attachment'
        AND p.post_mime_type LIKE 'image/%'
        AND pm.meta_key = '_wp_attachment_image_alt'
        AND pm.meta_value != ''
        ORDER BY pm.meta_value ASC
    "));

    if (!$images) {
        echo '<p>No images with ALT found.</p></div>';
        return;
    }

    // Group by ALT
    $grouped = [];
    foreach ($images as $img) {
        $grouped[$img->alt_text][] = $img;
    }

    $total_groups = count($grouped);
    $total_pages  = ceil($total_groups / $per_page);

    $grouped = array_slice($grouped, $offset, $per_page, true);

    foreach ($grouped as $alt => $imgs) {

        echo '<div style="background:#fff;padding:15px;margin-bottom:20px;border:1px solid #ddd;">';
        echo '<h2 style="color:#0073aa;">ALT: ' . esc_html($alt) . ' (Used ' . count($imgs) . ' times)</h2>';

        foreach ($imgs as $img) {

            echo '<div style="display:flex;gap:15px;margin-bottom:15px;align-items:center;">';

            echo '<img src="' . esc_url($img->guid) . '" width="80" style="border:1px solid #ccc;">';

            echo '<div style="flex:1;">';
            echo '<strong>Image URL:</strong><br>';
            echo '<a href="' . esc_url($img->guid) . '" target="_blank">' . esc_url($img->guid) . '</a><br><br>';

            echo '<strong>Attachment Edit:</strong><br>';
            echo '<a href="' . admin_url('post.php?post=' . $img->ID . '&action=edit') . '" target="_blank">Edit Attachment</a><br>';

            if ($img->post_parent) {
                $parent = get_post($img->post_parent);
                if ($parent) {
                    echo '<strong>Used In:</strong><br>';
                    echo '<a href="' . get_permalink($parent->ID) . '" target="_blank">View ' . ucfirst($parent->post_type) . '</a><br>';
                    echo '<a href="' . admin_url('post.php?post=' . $parent->ID . '&action=edit') . '" target="_blank">Edit ' . ucfirst($parent->post_type) . '</a>';
                }
            }

            echo '</div>';

            // Inline ALT Edit
            echo '<form method="POST" action="' . admin_url('admin-post.php') . '">';
            echo '<input type="hidden" name="action" value="update_image_alt">';
            echo '<input type="hidden" name="image_id" value="' . $img->ID . '">';
            echo '<input type="text" name="new_alt" value="' . esc_attr($alt) . '" style="width:200px;">';
            echo '<br><br><input type="submit" class="button button-primary" value="Update ALT">';
            echo '</form>';

            echo '</div>';
        }

        echo '</div>';
    }

    /* ---------- PAGINATION ---------- */

    if ($total_pages > 1) {
        echo '<div style="margin-top:20px;">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $class = ($i == $current_page) ? 'button button-primary' : 'button';
            echo '<a class="' . $class . '" style="margin-right:5px;" href="' . admin_url('admin.php?page=image-alt-pro&paged=' . $i) . '">' . $i . '</a>';
        }
        echo '</div>';
    }

    echo '</div>';
}
