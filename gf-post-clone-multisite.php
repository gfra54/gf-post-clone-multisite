<?php

/**
 * Plugin Name: GF Post Clone Multisite
 * Description: Adds a "Clone to another site" action for posts, allowing administrators to duplicate a post (including metadata, ACF fields, and attachments) to another site within the same WordPress multisite network.
 * Author: GF
 * Version: 1.6
 */
include __DIR__ . '/functions.php';

add_action('init', function () {
    foreach (get_post_types(['show_ui' => true]) as $post_type) {
        add_filter("{$post_type}_row_actions", function ($actions, $post) {
            if (!is_multisite() || !current_user_can('edit_post', $post->ID)) return $actions;

            $url = add_query_arg([
                'page' => 'clone_post_to_site',
                'post_id' => $post->ID,
            ], admin_url('admin.php'));

            $actions['clone_to_site'] = '<a href="' . esc_url($url) . '">Clone to...</a>';
            return $actions;
        }, 10, 2);
    }
});

add_action('admin_menu', function () {
    add_submenu_page(null, 'Clone to site', 'Clone to site', 'edit_posts', 'clone_post_to_site', function () {
        $post_id = intval($_GET['post_id'] ?? 0);
        $current_blog_id = get_current_blog_id();
        $sites = get_sites(['number' => 0]);

        echo '<div class="wrap"><h1>Cloner to another site</h1>';
        echo '<form method="post" style="margin-top: 2em;">';
        echo '<input type="hidden" name="clone_post_id" value="' . esc_attr($post_id) . '">';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px;">';

        foreach ($sites as $site) {
            if ((int)$site->blog_id === $current_blog_id) continue;
            $details = get_blog_details($site->blog_id);
            switch_to_blog($site->blog_id);
            $favicon = get_site_icon_url(64);
            restore_current_blog();

            echo '<label style="border: 1px solid #ccc; padding: 15px; display: block; cursor: pointer; border-radius: 6px;">';
            echo '<input type="radio" name="target_blog_id" value="' . esc_attr($site->blog_id) . '" style="margin-right: 10px;">';
            echo $favicon
                ? '<img src="' . esc_url($favicon) . '" alt="icon" style="width:32px;height:32px;vertical-align:middle;margin-right:10px;">'
                : '<span class="dashicons dashicons-admin-home" style="font-size: 16px;vertical-align: middle; margin-right: 10px;"></span>';
            echo '<strong>' . esc_html($details->blogname) . '</strong><br>';
            echo '<small>' . esc_url($details->siteurl) . '</small>';
            echo '</label>';
        }

        echo '</div>';
        echo '<p>';
        submit_button('Clone');
        echo '</p>';
        echo '</form></div>';
    });
});

add_action('admin_init', function () {
    if (!isset($_POST['clone_post_id'], $_POST['target_blog_id'])) return;

    $source_blog_id = get_current_blog_id();
    $post_id = intval($_POST['clone_post_id']);
    $target_blog_id = intval($_POST['target_blog_id']);


    $new_post_id = gf_clone_post($post_id, $source_blog_id, $target_blog_id);

    $edit_url = get_admin_url($target_blog_id, 'post.php?post=' . $new_post_id . '&action=edit');

    // echo '<a href="' . $edit_url . '" target="post">' . $edit_url . '</a>';
    // me($edit_url);
    wp_redirect($edit_url);
    exit;
});

add_action('admin_bar_menu', function ($admin_bar) {
    if (!is_admin() || !is_multisite() || !current_user_can('edit_posts')) return;

    global $pagenow;

    if ($pagenow === 'post.php' && isset($_GET['post'])) {
        $post_id = intval($_GET['post']);
        $url = add_query_arg([
            'page' => 'clone_post_to_site',
            'post_id' => $post_id,
        ], admin_url('admin.php'));

        $admin_bar->add_node([
            'id' => 'gf-clone-post',
            'title' => '<span style="position: relative;display:inline-block">📄<span style="position: absolute; top: 0; left: 0;width: 100%;height: 100%;transform: translate(3px,3px);">📄</span></span> Clone to...',
            'href' => esc_url($url),
            'meta' => ['title' => 'Clone to another site']
        ]);

        $meta = get_post_meta($post_id, '_cloned_from', true);

        // Format attendu : "blog_id:post_id" ou juste post_id
        if ($meta) {
            $parts = explode(':', $meta);
            $source_blog_id = isset($parts[1]) ? intval($parts[0]) : get_current_blog_id();
            $source_post_id = isset($parts[1]) ? intval($parts[1]) : intval($parts[0]);

            switch_to_blog($source_blog_id);
            if (get_post_status($source_post_id)) {
                $source_url = get_edit_post_link($source_post_id);
                $admin_bar->add_node([
                    'id' => 'gf-clone-source',
                    'title' => '📜 See original post',
                    'href' => esc_url($source_url),
                    'meta' => ['title' => 'See the post that this one was cloned from']
                ]);
            }
            restore_current_blog();
        }
    }
}, 100);
