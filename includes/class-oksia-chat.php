<?php

if (!defined('ABSPATH')) {
    exit;
}

class OKSIA_Chat {
    const POST_TYPE = 'oksia_chat_message';
    const REST_NAMESPACE = 'oksia/v1';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate() {
        self::register_post_type_static();
    }

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_post_type() {
        self::register_post_type_static();
    }

    public static function register_post_type_static() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name' => __('Internal Chat Messages', 'oksia-smart-itinerary-agent'),
                    'singular_name' => __('Internal Chat Message', 'oksia-smart-itinerary-agent'),
                ),
                'public' => false,
                'show_ui' => false,
                'show_in_menu' => false,
                'show_in_rest' => false,
                'supports' => array('editor', 'author', 'title'),
                'capability_type' => array('oksia_chat_message', 'oksia_chat_messages'),
                'map_meta_cap' => true,
                'capabilities' => array(
                    'edit_post' => 'edit_oksia_chat_message',
                    'read_post' => 'read_oksia_chat_message',
                    'delete_post' => 'delete_oksia_chat_message',
                    'edit_posts' => 'edit_oksia_chat_messages',
                    'edit_others_posts' => 'edit_others_oksia_chat_messages',
                    'publish_posts' => 'publish_oksia_chat_messages',
                    'read_private_posts' => 'read_private_oksia_chat_messages',
                    'delete_posts' => 'delete_oksia_chat_messages',
                    'delete_private_posts' => 'delete_private_oksia_chat_messages',
                    'delete_published_posts' => 'delete_published_oksia_chat_messages',
                    'delete_others_posts' => 'delete_others_oksia_chat_messages',
                    'edit_private_posts' => 'edit_private_oksia_chat_messages',
                    'edit_published_posts' => 'edit_published_oksia_chat_messages',
                    'create_posts' => 'edit_oksia_chat_messages',
                ),
            )
        );
    }

    public function register_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/chat/users',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'rest_get_users'),
                'permission_callback' => array($this, 'can_access_chat'),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/chat/messages',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'rest_get_messages'),
                    'permission_callback' => array($this, 'can_access_chat'),
                    'args' => array(
                        'recipient_id' => array(
                            'sanitize_callback' => 'absint',
                        ),
                        'limit' => array(
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'rest_send_message'),
                    'permission_callback' => array($this, 'can_access_chat'),
                    'args' => array(
                        'recipient_id' => array(
                            'sanitize_callback' => 'absint',
                        ),
                        'message' => array(
                            'sanitize_callback' => 'sanitize_textarea_field',
                        ),
                    ),
                ),
            )
        );
    }

    public function can_access_chat() {
        if (!is_user_logged_in()) {
            return false;
        }

        return $this->is_internal_user(get_current_user_id());
    }

    public function is_internal_user($user_id = 0) {
        $user = $user_id ? get_user_by('id', absint($user_id)) : wp_get_current_user();
        if (!$user || empty($user->roles) || !is_array($user->roles)) {
            return false;
        }

        $internal_roles = array('administrator', 'oksia_manager', 'oksia_executive');
        foreach ($user->roles as $role) {
            if (in_array($role, $internal_roles, true)) {
                return true;
            }
        }

        return false;
    }

    public function get_internal_users($exclude_current = true) {
        $users = get_users(
            array(
                'role__in' => array('administrator', 'oksia_manager', 'oksia_executive'),
                'orderby' => 'display_name',
                'order' => 'ASC',
                'fields' => array('ID', 'display_name', 'user_login'),
            )
        );

        $current_user_id = get_current_user_id();
        $items = array();
        foreach ($users as $user) {
            if ($exclude_current && (int) $user->ID === (int) $current_user_id) {
                continue;
            }

            $items[] = array(
                'id' => (int) $user->ID,
                'label' => $this->format_user_label($user->ID, $user->display_name, $user->user_login),
                'display_name' => $user->display_name,
                'role' => $this->get_user_primary_role($user->ID),
            );
        }

        return $items;
    }

    public function render_chat_widget() {
        if (!$this->can_access_chat()) {
            return '';
        }

        $users = $this->get_internal_users(true);
        $default_recipient = !empty($users) ? (int) $users[0]['id'] : 0;
        $data = array(
            'restUrl' => esc_url_raw(rest_url(self::REST_NAMESPACE)),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUserId' => get_current_user_id(),
            'defaultRecipientId' => $default_recipient,
            'users' => $users,
            'strings' => array(
                'loading' => __('Loading conversation...', 'oksia-smart-itinerary-agent'),
                'empty' => __('No messages yet. Start the conversation below.', 'oksia-smart-itinerary-agent'),
                'send' => __('Send Message', 'oksia-smart-itinerary-agent'),
                'selectUser' => __('Select a colleague to start chatting.', 'oksia-smart-itinerary-agent'),
            ),
        );

        wp_enqueue_script('oksia-workspace-chat', OKSIA_URL . 'assets/js/workspace-chat.js', array(), OKSIA_VERSION, true);
        wp_localize_script('oksia-workspace-chat', 'okChatData', $data);

        ob_start();
        ?>
        <div class="oksia-chat-shell" id="oksia-chat">
            <div class="oksia-chat-head">
                <div>
                    <div class="oksia-chat-kicker"><?php esc_html_e('Team Members', 'oksia-smart-itinerary-agent'); ?></div>
                    <h3><?php esc_html_e('Open a colleague and chat', 'oksia-smart-itinerary-agent'); ?></h3>
                </div>
                <p><?php esc_html_e('Choose a team member from the list to open a private internal thread.', 'oksia-smart-itinerary-agent'); ?></p>
            </div>
            <div class="oksia-chat-grid">
                <aside class="oksia-chat-sidebar">
                    <h4><?php esc_html_e('Team Members', 'oksia-smart-itinerary-agent'); ?></h4>
                    <?php if (!empty($users)) : ?>
                        <div class="oksia-chat-users" data-chat-users>
                            <?php foreach ($users as $index => $user) : ?>
                                <button
                                    type="button"
                                    class="oksia-chat-user<?php echo 0 === $index ? ' is-active' : ''; ?>"
                                    data-recipient-id="<?php echo esc_attr((string) $user['id']); ?>"
                                    data-recipient-label="<?php echo esc_attr($user['label']); ?>"
                                >
                                    <strong><?php echo esc_html($user['display_name']); ?></strong>
                                    <span><?php echo esc_html($user['role']); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="oksia-chat-empty"><?php esc_html_e('No other internal users found.', 'oksia-smart-itinerary-agent'); ?></p>
                    <?php endif; ?>
                </aside>
                <section class="oksia-chat-pane">
                    <div class="oksia-chat-pane__head">
                        <div>
                            <div class="oksia-chat-pane__label"><?php esc_html_e('Conversation', 'oksia-smart-itinerary-agent'); ?></div>
                            <h4 data-chat-title><?php esc_html_e('Select a colleague', 'oksia-smart-itinerary-agent'); ?></h4>
                        </div>
                        <div class="oksia-chat-pane__status" data-chat-status><?php esc_html_e('Ready', 'oksia-smart-itinerary-agent'); ?></div>
                    </div>
                    <div class="oksia-chat-messages" data-chat-messages>
                        <div class="oksia-chat-empty"><?php esc_html_e('Select a colleague to start chatting.', 'oksia-smart-itinerary-agent'); ?></div>
                    </div>
                    <form class="oksia-chat-form" data-chat-form>
                        <textarea name="message" rows="3" placeholder="<?php esc_attr_e('Type your internal note or question here...', 'oksia-smart-itinerary-agent'); ?>"></textarea>
                        <div class="oksia-chat-form__row">
                            <span class="oksia-chat-form__hint"><?php esc_html_e('Only internal agency users can read these messages.', 'oksia-smart-itinerary-agent'); ?></span>
                            <button type="submit" class="button button-primary"><?php esc_html_e('Send Message', 'oksia-smart-itinerary-agent'); ?></button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function rest_get_users(WP_REST_Request $request) {
        return rest_ensure_response(
            array(
                'items' => $this->get_internal_users(true),
            )
        );
    }

    public function rest_get_messages(WP_REST_Request $request) {
        $recipient_id = absint($request->get_param('recipient_id'));
        if ($recipient_id <= 0 || !$this->is_internal_user($recipient_id)) {
            return new WP_Error('oksia_chat_invalid_recipient', __('Choose a valid internal user.', 'oksia-smart-itinerary-agent'), array('status' => 400));
        }

        $current_user_id = get_current_user_id();
        $limit = absint($request->get_param('limit'));
        if ($limit <= 0) {
            $limit = 50;
        }

        return rest_ensure_response(
            array(
                'items' => $this->get_thread_messages($current_user_id, $recipient_id, $limit),
            )
        );
    }

    public function rest_send_message(WP_REST_Request $request) {
        $recipient_id = absint($request->get_param('recipient_id'));
        $message = trim((string) $request->get_param('message'));

        if ($recipient_id <= 0 || !$this->is_internal_user($recipient_id)) {
            return new WP_Error('oksia_chat_invalid_recipient', __('Choose a valid internal user.', 'oksia-smart-itinerary-agent'), array('status' => 400));
        }

        if ('' === $message) {
            return new WP_Error('oksia_chat_empty_message', __('Write a message before sending.', 'oksia-smart-itinerary-agent'), array('status' => 400));
        }

        $post_id = $this->insert_message(get_current_user_id(), $recipient_id, $message);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        return rest_ensure_response(
            array(
                'ok' => true,
                'id' => (int) $post_id,
                'messages' => $this->get_thread_messages(get_current_user_id(), $recipient_id, 50),
            )
        );
    }

    private function thread_key($user_a, $user_b) {
        $pair = array(absint($user_a), absint($user_b));
        sort($pair, SORT_NUMERIC);
        return implode(':', $pair);
    }

    private function format_user_label($user_id, $display_name, $user_login) {
        $display_name = trim((string) $display_name);
        if ('' === $display_name) {
            $display_name = trim((string) $user_login);
        }

        $role = $this->get_user_primary_role($user_id);
        return sprintf('%s (%s)', $display_name, $role);
    }

    private function get_user_primary_role($user_id) {
        $user = get_user_by('id', absint($user_id));
        if (!$user || empty($user->roles) || !is_array($user->roles)) {
            return __('Staff', 'oksia-smart-itinerary-agent');
        }

        $role = (string) reset($user->roles);
        $labels = array(
            'administrator' => __('Admin', 'oksia-smart-itinerary-agent'),
            'oksia_manager' => __('Manager', 'oksia-smart-itinerary-agent'),
            'oksia_executive' => __('Executive', 'oksia-smart-itinerary-agent'),
        );

        return isset($labels[$role]) ? $labels[$role] : ucwords(trim(str_replace(array('-', '_'), ' ', $role)));
    }

    private function insert_message($sender_id, $recipient_id, $message) {
        $sender = get_user_by('id', absint($sender_id));
        $recipient = get_user_by('id', absint($recipient_id));
        if (!$sender || !$recipient) {
            return new WP_Error('oksia_chat_user_missing', __('Unable to load chat users.', 'oksia-smart-itinerary-agent'));
        }

        $thread_key = $this->thread_key($sender_id, $recipient_id);
        $post_id = wp_insert_post(
            array(
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => sprintf('Chat %s', $thread_key),
                'post_content' => wp_strip_all_tags($message),
                'post_author' => absint($sender_id),
            ),
            true
        );

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        update_post_meta($post_id, '_oksia_chat_thread_key', $thread_key);
        update_post_meta($post_id, '_oksia_chat_sender_id', absint($sender_id));
        update_post_meta($post_id, '_oksia_chat_recipient_id', absint($recipient_id));
        update_post_meta($post_id, '_oksia_chat_sender_name', $sender->display_name ?: $sender->user_login);
        update_post_meta($post_id, '_oksia_chat_recipient_name', $recipient->display_name ?: $recipient->user_login);
        update_post_meta($post_id, '_oksia_chat_read_at_' . absint($sender_id), current_time('mysql'));
        return $post_id;
    }

    private function get_thread_messages($user_a, $user_b, $limit = 50) {
        $thread_key = $this->thread_key($user_a, $user_b);
        $query = new WP_Query(
            array(
                'post_type' => self::POST_TYPE,
                'posts_per_page' => $limit,
                'post_status' => array('publish', 'private'),
                'orderby' => 'date',
                'order' => 'ASC',
                'meta_query' => array(
                    array(
                        'key' => '_oksia_chat_thread_key',
                        'value' => $thread_key,
                    ),
                ),
            )
        );

        if (!$query->have_posts()) {
            return array();
        }

        $items = array();
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $sender_id = absint(get_post_meta($post_id, '_oksia_chat_sender_id', true));
            $recipient_id = absint(get_post_meta($post_id, '_oksia_chat_recipient_id', true));
            $items[] = array(
                'id' => $post_id,
                'message' => get_the_content(null, false, $post_id),
                'sender_id' => $sender_id,
                'recipient_id' => $recipient_id,
                'sender_name' => trim((string) get_post_meta($post_id, '_oksia_chat_sender_name', true)),
                'recipient_name' => trim((string) get_post_meta($post_id, '_oksia_chat_recipient_name', true)),
                'time' => get_the_date('M j, g:i a', $post_id),
                'mine' => $sender_id === absint($user_a),
            );
        }

        wp_reset_postdata();
        return $items;
    }
}
