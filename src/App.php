<?php

namespace My\EmailLog;

class App
{
    const POST_TYPE = 'email_log';

    public static function init()
    {
        add_action('init', [__CLASS__, 'loadTextdomain']);
        add_action('init', [__CLASS__, 'registerPostType']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'adminEnqueueScripts']);
        add_action('add_meta_boxes', [__CLASS__, 'addMetaBoxes']);
        add_filter('pre_wp_mail', [__CLASS__, 'preWPMail'], 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [__CLASS__, 'addLogColumns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [__CLASS__, 'renderLogColumns'], 10, 2);

    }

    public static function loadTextdomain()
    {
        load_plugin_textdomain('my-email-log', false, dirname(plugin_basename(MY_EMAIL_LOG_PLUGIN_FILE)) . '/languages');
    }

    public static function registerPostType()
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'                  => _x('Email Logs', 'Post type general name', 'my-email-log'),
                'singular_name'         => _x('Email Log', 'Post type singular name', 'my-email-log'),
                'menu_name'             => _x('Email Logs', 'Admin Menu text', 'my-email-log'),
                'name_admin_bar'        => _x('Email Log', 'Add New on Toolbar', 'my-email-log'),
                'add_new'               => __('Add New', 'my-email-log'),
                'add_new_item'          => __('Add New Log', 'my-email-log'),
                'new_item'              => __('New Log', 'my-email-log'),
                'edit_item'             => __('Edit Log', 'my-email-log'),
                'view_item'             => __('View Log', 'my-email-log'),
                'all_items'             => __('All Logs', 'my-email-log'),
                'search_items'          => __('Search Logs', 'my-email-log'),
                'parent_item_colon'     => __('Parent Logs:', 'my-email-log'),
                'not_found'             => __('No logs found.', 'my-email-log'),
                'not_found_in_trash'    => __('No logs found in Trash.', 'my-email-log'),
                'featured_image'        => _x('Log Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'my-email-log'),
                'set_featured_image'    => _x('Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'my-email-log'),
                'remove_featured_image' => _x('Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'my-email-log'),
                'use_featured_image'    => _x('Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'my-email-log'),
                'archives'              => _x('Log archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'my-email-log'),
                'insert_into_item'      => _x('Insert into log', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'my-email-log'),
                'uploaded_to_this_item' => _x('Uploaded to this log', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'my-email-log'),
                'filter_items_list'     => _x('Filter logs list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'my-email-log'),
                'items_list_navigation' => _x('Logs list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'my-email-log'),
                'items_list'            => _x('Logs list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'my-email-log'),
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => ['slug' => 'email-log'],
            'capabilities'       => [
                'edit_post'          => 'update_core',
                'read_post'          => 'update_core',
                'delete_post'        => 'update_core',
                'edit_posts'         => 'update_core',
                'edit_others_posts'  => 'update_core',
                'delete_posts'       => 'update_core',
                'publish_posts'      => 'update_core',
                'read_private_posts' => 'update_core',
            ],
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => ['title'],
        ]);
    }

    public static function adminEnqueueScripts()
    {
        $screen = get_current_screen();

        if ($screen->post_type !== self::POST_TYPE || $screen->id !== self::POST_TYPE) {
            return;
        }

        wp_enqueue_script('my-email-log', plugins_url('admin.js', MY_EMAIL_LOG_PLUGIN_FILE), ['jquery'], false, true);
    }

    public static function preWPMail($return, $args)
    {
        global $phpmailer;

        /**
         * Send email
         */

        remove_filter(current_filter(), [__CLASS__, __FUNCTION__], 10);

        $is_sent = wp_mail($args['to'], $args['subject'], $args['message'], $args['headers'], $args['attachments']);

        add_filter(current_filter(), [__CLASS__, __FUNCTION__], 10, 2);

        /**
         * Save post
         */

        $post_id = wp_insert_post([
            'post_title'   => $args['subject'],
            'post_content' => '',
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
        ]);

        $is_html = 'text/html' === apply_filters('wp_mail_content_type', '');

        update_post_meta($post_id, 'to', self::sanitizeTo($args['to']));
        update_post_meta($post_id, 'subject', $args['subject']);
        update_post_meta($post_id, 'message', $args['message']);
        update_post_meta($post_id, 'headers', self::sanitizeHeaders($args['headers']));
        update_post_meta($post_id, 'attachments', self::sanitizeAttachments($args['attachments']));
        update_post_meta($post_id, 'is_sent', $is_sent);
        update_post_meta($post_id, 'error', $phpmailer->ErrorInfo);
        update_post_meta($post_id, 'is_html', $is_html);

        /**
         * Return
         */

        return $is_sent;
    }

    public static function sanitizeTo($to)
    {
        if (! is_array($to)) {
            $to = explode(',', $to);
            $to = array_map('trim', $to);
            $to = array_filter($to);
        }

        return $to;
    }

    public static function sanitizeHeaders($headers)
    {
        if (! is_array($headers)) {
            $headers = explode("\n", $headers);
            $headers = array_map('trim', $headers);
            $headers = array_filter($headers);
        }

        return $headers;
    }

    public static function sanitizeAttachments($attachments)
    {
        return self::sanitizeHeaders($attachments);
    }

    public static function getToUsers($emails)
    {
        $return = [];

        foreach ($emails as $email) {
            $user = get_user_by('email', $email);
            if ($user) {
                $return[] = sprintf(
                    '<a href="%1$s">%2$s</a>',
                    esc_url(get_edit_user_link($user->ID)),
                    esc_html($user->display_name)
                );
            } else {
                $return[] = esc_html($email);
            }
        }

        return $return;
    }

    public static function addMetaBoxes($post_type)
    {
        if ($post_type === self::POST_TYPE) {
            add_meta_box('my-email-log', __('Data', 'my-email-log'), [__CLASS__, 'renderMetaBoxContent'], $post_type);
        }
    }

    public static function renderMetaBoxContent($post)
    {
        $to          = get_post_meta($post->ID, 'to', true);
        $subject     = get_post_meta($post->ID, 'subject', true);
        $message     = get_post_meta($post->ID, 'message', true);
        $headers     = get_post_meta($post->ID, 'headers', true);
        $attachments = get_post_meta($post->ID, 'attachments', true);
        $is_sent     = get_post_meta($post->ID, 'is_sent', true);
        $error       = get_post_meta($post->ID, 'error', true);
        $is_html     = get_post_meta($post->ID, 'is_html', true);

        $src = plugins_url('preview.php', MY_EMAIL_LOG_PLUGIN_FILE);
        $src = add_query_arg('post', $post->ID, $src);

        ?>

        <p>
            <strong><?php esc_html_e('Recipients', 'my-email-log'); ?></strong><br>
            <?php echo $to ? implode('<br>', self::getToUsers($to)) : '–'; ?>
        </p>

        <p>
            <strong><?php esc_html_e('Subject', 'my-email-log'); ?></strong><br>
            <?php echo trim($subject) ? esc_html($subject) : '–'; ?>
        </p>

        <p>
            <strong><?php esc_html_e('Message', 'my-email-log'); ?></strong><br>
            <?php if ($is_html) : ?>
            <iframe src="<?php echo esc_url($src); ?>" width="100%" height="100px" style="border:none;"></iframe>
            <?php else : ?>
            <?php echo trim($message) ? esc_html($message) : '–'; ?>
            <?php endif; ?>
        </p>

        <p>
            <strong><?php esc_html_e('Headers', 'my-email-log'); ?></strong><br>
            <?php echo $headers ? esc_html(implode('<br>', $headers)) : '–'; ?>
        </p>

        <p>
            <strong><?php esc_html_e('attachments', 'my-email-log'); ?></strong><br>
            <?php echo $attachments ? esc_html(implode('<br>', $attachments)) : '–'; ?>
        </p>

        <p>
            <strong><?php esc_html_e('Send', 'my-email-log'); ?></strong><br>
            <?php echo $is_sent ? esc_html__('yes', 'my-email-log') : esc_html__('no', 'my-email-log'); ?>
        </p>

        <p>
            <strong><?php esc_html_e('Error', 'my-email-log'); ?></strong><br>
            <?php echo trim($error) ? esc_html($error) : '–'; ?>
        </p>

        <?php
    }

    public static function addLogColumns($columns)
    {
        unset($columns['title']);

        return [
            'cb'           => $columns['cb'],
            'mail_to'      => __('Recipients', 'my-email-log'),
            'mail_subject' => __('Subject', 'my-email-log'),
            'mail_is_sent' => __('Sent', 'my-email-log'),
            'mail_error'   => __('Error', 'my-email-log'),
        ]+ $columns;
    }

    public static function renderLogColumns($column, $post_id)
    {
        $to      = get_post_meta($post_id, 'to', true);
        $subject = get_post_meta($post_id, 'subject', true);
        $is_sent = get_post_meta($post_id, 'is_sent', true);
        $error   = get_post_meta($post_id, 'error', true);

        switch ($column) {
            case 'mail_to':
                echo $to ? implode(', ', self::getToUsers($to)) : '–';
                break;
            case 'mail_subject':
                echo trim($subject) ? esc_html($subject) : '–';
                break;
            case 'mail_is_sent':
                echo $is_sent ? esc_html__('yes', 'my-email-log') : esc_html__('no', 'my-email-log');
                break;
            case 'mail_error':
                echo trim($error) ? esc_html($error) : '–';
                break;
        }
    }
}
