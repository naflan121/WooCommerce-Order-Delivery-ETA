<?php

/**
 * Plugin Name: WooCommerce Order Delivery ETA
 * Description: Display order tracking timeline with customizable steps and ETA
 * Version: 1.0.2
 * Author: Mohamed Naflan
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

class WC_Order_Delivery_ETA
{
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        // Add menu
        add_action('admin_menu', array($this, 'add_plugin_page'));
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        // Add shortcode
        add_shortcode('wc_order_eta', array($this, 'render_tracking'));
        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        // Display ETA on product page
        add_action('woocommerce_after_add_to_cart_button', array($this, 'display_eta_on_product_page'));
    }

    public function add_plugin_page()
    {
        add_menu_page(
            'WC Order ETA',
            'Order ETA',
            'manage_options',
            'wc-order-eta',
            array($this, 'create_admin_page'),
            'dashicons-calendar-alt',
            56
        );
    }

    public function register_settings()
    {
        register_setting('wc_order_eta_group', 'wc_order_eta_settings');

        // Default settings if not set
        if (!get_option('wc_order_eta_settings')) {
            $default_settings = array(
                'step1_label' => 'Order Confirmed',
                'step1_icon' => '✓',
                'step2_label' => 'Design and Print',
                'step2_icon' => '🎨',
                'step2_days_min' => 3,
                'step2_days_max' => 7,
                'step3_label' => 'Order Dispatch',
                'step3_icon' => '📦',
                'step3_days_min' => 4,
                'step3_days_max' => 8,
                'show_eta_product_page' => 0, // New setting for product page
            );
            update_option('wc_order_eta_settings', $default_settings);
        }
    }

    public function create_admin_page()
    {
        $settings = get_option('wc_order_eta_settings');

        // Ensure the setting exists, and set a default value if not
        if (!isset($settings['show_eta_product_page'])) {
            $settings['show_eta_product_page'] = 0; // Default to unchecked
        }
?>
        <div class="wrap">
            <h1>WooCommerce Order ETA Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_order_eta_group');
                ?>
                <table class="form-table">
                    <?php for ($i = 1; $i <= 3; $i++) : ?>
                        <tr>
                            <th scope="row">Step <?php echo $i; ?> Settings</th>
                            <td>
                                <input type="text"
                                    name="wc_order_eta_settings[step<?php echo $i; ?>_label]"
                                    value="<?php echo esc_attr($settings["step{$i}_label"]); ?>"
                                    placeholder="Step <?php echo $i; ?> Label" />
                                <input type="text"
                                    name="wc_order_eta_settings[step<?php echo $i; ?>_icon]"
                                    value="<?php echo esc_attr($settings["step{$i}_icon"]); ?>"
                                    placeholder="Icon"
                                    style="width: 50px;" />
                                <?php if ($i > 1) : ?>
                                    <br><br>
                                    Days Range:
                                    <input type="number"
                                        name="wc_order_eta_settings[step<?php echo $i; ?>_days_min]"
                                        value="<?php echo esc_attr($settings["step{$i}_days_min"]); ?>"
                                        style="width: 60px;" /> -
                                    <input type="number"
                                        name="wc_order_eta_settings[step<?php echo $i; ?>_days_max]"
                                        value="<?php echo esc_attr($settings["step{$i}_days_max"]); ?>"
                                        style="width: 60px;" />
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endfor; ?>

                    <tr>
                        <th scope="row">Display ETA on Product Page</th>
                        <td>
                            <input type="checkbox"
                                name="wc_order_eta_settings[show_eta_product_page]"
                                value="1"
                                <?php checked(1, $settings['show_eta_product_page']); ?> />
                            <label for="show_eta_product_page">Check to display ETA on product page</label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
    <?php
    }


    public function enqueue_styles()
    {
        wp_enqueue_style(
            'wc-order-eta-style',
            plugins_url('css/style.css', __FILE__),
            array(),
            '1.0.0'
        );
    }

    public function render_tracking($atts)
    {
        $settings = get_option('wc_order_eta_settings');

        // Calculate dates
        $order_date = current_time('timestamp');
        $step2_start = date('jS', strtotime("+{$settings['step2_days_min']} days", $order_date));
        $step2_end = date('jS M', strtotime("+{$settings['step2_days_max']} days", $order_date));
        $step3_start = date('jS', strtotime("+{$settings['step3_days_min']} days", $order_date));
        $step3_end = date('jS M', strtotime("+{$settings['step3_days_max']} days", $order_date));

        ob_start();
    ?>
        <div class="ost-container">
            <div class="ost-tracking-card">
                <div class="ost-tracking-timeline">
                    <div class="ost-timeline-line"></div>
                    <div class="ost-timeline-progress"></div>

                    <div class="ost-step active">
                        <div class="ost-step-icon"><?php echo esc_html($settings['step1_icon']); ?></div>
                        <div class="ost-step-label"><?php echo esc_html($settings['step1_label']); ?></div>
                        <div class="ost-step-date"><?php echo date('jS M', $order_date); ?></div>
                    </div>

                    <div class="ost-step active">
                        <div class="ost-step-icon"><?php echo esc_html($settings['step2_icon']); ?></div>
                        <div class="ost-step-label"><?php echo esc_html($settings['step2_label']); ?></div>
                        <div class="ost-step-date"><?php echo $step2_start . ' - ' . $step2_end; ?></div>
                    </div>

                    <div class="ost-step">
                        <div class="ost-step-icon"><?php echo esc_html($settings['step3_icon']); ?></div>
                        <div class="ost-step-label"><?php echo esc_html($settings['step3_label']); ?></div>
                        <div class="ost-step-date"><?php echo $step3_start . ' - ' . $step3_end; ?></div>
                    </div>
                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    public function display_eta_on_product_page()
    {
        $settings = get_option('wc_order_eta_settings');

        // Show ETA only if enabled in settings
        if (isset($settings['show_eta_product_page']) && $settings['show_eta_product_page'] == 1) {
            echo $this->render_tracking(null);
        }
    }
}

// Initialize plugin
WC_Order_Delivery_ETA::get_instance();
