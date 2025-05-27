<?php

/**
 * Clone un article WordPress d’un site à un autre dans un environnement multisite.
 *
 * @param int $post_id L’ID du post à cloner.
 * @param int $source_blog_id L’ID du site source.
 * @param int $target_blog_id L’ID du site cible.
 * @return int|WP_Error L’ID du nouveau post ou une erreur WP_Error.
 */
function gf_clone_post($post_id, $source_blog_id, $target_blog_id)
{
    if (!is_multisite()) return new WP_Error('not_multisite', 'CLoning can only be done on a multisite installation.');

    switch_to_blog($source_blog_id);
    $post = get_post($post_id, ARRAY_A);
    if (!$post) {
        restore_current_blog();
        return new WP_Error('post_not_found', 'Not found.');
    }

    $metas = get_post_meta($post_id);
    $is_attachment = ($post['post_type'] === 'attachment');

    restore_current_blog();

    unset($post['ID'], $post['guid']);
    $post['post_status'] = 'draft';
    $date_clonage = date_i18n('d/m/Y H:i');
    $post['post_title'] .= ' [cloned on ' . $date_clonage . ']';

    switch_to_blog($target_blog_id);
    $upload_dir = wp_upload_dir();
    $new_post_id = wp_insert_post($post);

    foreach ($metas as $key => $values) {
        if ($key === '_cloned_from') continue;
        foreach ($values as $value) {
            add_post_meta($new_post_id, $key, maybe_unserialize($value));
        }
    }
    add_post_meta($new_post_id, '_cloned_from', $source_blog_id . ':' . $post_id);
    restore_current_blog();

    if ($is_attachment) {
        $src_file = get_attached_file($post_id);
        if ($src_file && file_exists($src_file)) {
            switch_to_blog($target_blog_id);
            $filename = basename($src_file);
            $dest = $upload_dir['path'] . '/' . $filename;
            copy($src_file, $dest);
            update_attached_file($new_post_id, $dest);

            require_once ABSPATH . 'wp-admin/includes/image.php';
            $meta = wp_generate_attachment_metadata($new_post_id, $dest);
            wp_update_attachment_metadata($new_post_id, $meta);
            restore_current_blog();
        }
    } else {
        switch_to_blog($source_blog_id);
        $attachments = get_attached_media('', $post_id);
        $attachments = [];
        restore_current_blog();

        $attachements_ids = [];
        foreach ($attachments as $attachment) {
            $result = gf_clone_post($attachment->ID, $source_blog_id, $target_blog_id);
            if (!is_wp_error($result)) {
                $attachements_ids[$attachment->ID] = $result;
                switch_to_blog($target_blog_id);
                wp_update_post([
                    'ID' => $result,
                    'post_parent' => $new_post_id
                ]);
                restore_current_blog();
            }
        }
        if ($metas['_thumbnail_id'] ?? false) {
            foreach ($metas['_thumbnail_id'] as $value) {
                if (!isset($attachements_ids[$value])) {
                    $result = gf_clone_post($value, $source_blog_id, $target_blog_id);
                    if (!is_wp_error($result)) {
                        $attachements_ids[$value] = $result;
                    }
                }

                $value = $attachements_ids[$value]??false;
                switch_to_blog($target_blog_id);
                update_post_meta($new_post_id, '_thumbnail_id', maybe_unserialize($value));
                restore_current_blog();
            }
        }
        $acf_fields = gf_get_flat_acf_fields($post_id, $source_blog_id, ['image', 'file']);
        foreach ($metas as $key => $values) {
            $field_key = $metas['_' . $key][0] ?? false;
            foreach ($values as $value) {
                if (!$value) continue;
                if (!$field_key) continue;
                if (!isset($acf_fields[$field_key])) continue;
                if (!isset($attachements_ids[$value])) {
                    $result = gf_clone_post($value, $source_blog_id, $target_blog_id);
                    if (!is_wp_error($result)) {
                        $attachements_ids[$value] = $result;
                    }
                }
                $value = $attachements_ids[$value] ?? false;


                switch_to_blog($target_blog_id);
                update_post_meta($new_post_id, $key, maybe_unserialize($value));
                restore_current_blog();
            }
        }
    }


    return $new_post_id;
}


function gf_get_flat_acf_fields($post_id, $blog_id, $types = [])
{
    if (!function_exists('get_field_objects') || !function_exists('acf_get_field')) return [];

    switch_to_blog($blog_id);
    $field_objects = get_field_objects($post_id);
    restore_current_blog();

    $flat = [];

    $flatten = function ($fields) use (&$flatten, &$flat) {
        foreach ($fields as $field) {
            if (!isset($field['key'])) continue;

            $key = $field['key'];
            $definition = acf_get_field($field['key']);
            if (!$definition) continue;

            $flat[$key] = $definition;

            // Gestion des sous-champs (group, repeater)
            if (!empty($definition['sub_fields']) && is_array($definition['sub_fields'])) {
                $flatten($definition['sub_fields']);
            }

            // Gestion des layouts (flexible content)
            if ($definition['type'] === 'flexible_content' && !empty($definition['layouts'])) {
                foreach ($definition['layouts'] as $layout) {
                    if (!empty($layout['sub_fields'])) {
                        $flatten($layout['sub_fields']);
                    }
                }
            }
        }
    };

    if (is_array($field_objects)) {
        $flatten($field_objects);
    }
    foreach ($flat as &$field) {
        if ($types && !in_array($field['type'], $types)) {
            $field = false;
            continue;
        }
        if (isset($field['sub_fields']))
            unset($field['sub_fields']);
        if (isset($field['layouts']))
            unset($field['layouts']);
    }
    return array_filter($flat);
}


function me()
{
    $args = func_get_args();
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $filtered_trace = array_filter($backtrace, function ($trace) {
        return isset($trace['file']) && $trace['file'] !== __FILE__;
    });
    foreach ($args as $arg) {
        echo '<pre>';
        print_r($arg);
        echo '</pre>';
    }
    echo '<pre>';
    echo "\n--- Trace ---\n";
    foreach ($filtered_trace as $trace) {
        echo (isset($trace['file']) ? basename($trace['file']) : '') . ':' . ($trace['line'] ?? '') . ' ';
        echo (isset($trace['function']) ? $trace['function'] : '') . "()\n";
    }
    echo '</pre>';
    exit;
}

function m()
{
    $args = func_get_args();
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $filtered_trace = array_filter($backtrace, function ($trace) {
        return isset($trace['file']) && $trace['file'] !== __FILE__;
    });
    foreach ($args as $arg) {
        echo '<pre>';
        print_r($arg);
        echo '</pre>';
    }
    echo '<pre>';
    echo "\n--- Trace ---\n";
    foreach ($filtered_trace as $trace) {
        echo (isset($trace['file']) ? basename($trace['file']) : '') . ':' . ($trace['line'] ?? '') . ' ';
        echo (isset($trace['function']) ? $trace['function'] : '') . "()\n";
    }
    echo '</pre>';
}
