<?php
/**
 * Plugin Name: Chatwoot Identity Validation for WooCommerce
 * Description: Integrates Chatwoot with WooCommerce, sending additional user details and enabling identity validation using HMAC.
 * Version: 1.0
 * Author: Marcos Lisboa (Pixel Infinito)
 * Author URI: https://pixel.ao
 * Text Domain: chatwoot-identity-validation
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add cookie-based logout reset functionality
function chatwoot_reset_on_logout() {
    // Only proceed if Chatwoot is configured
    $chatwoot_base_url = get_option('chatwoot_base_url', '');
    $chatwoot_widget_token = get_option('chatwoot_widget_token', '');
    
    if (empty($chatwoot_base_url) || empty($chatwoot_widget_token)) {
        return;
    }
    
    // Set a cookie to indicate a logout occurred
    setcookie('chatwoot_reset_needed', '1', time() + 300, COOKIEPATH, COOKIE_DOMAIN);
}
add_action('wp_logout', 'chatwoot_reset_on_logout');

// Check for the reset cookie and handle it
function chatwoot_check_reset_needed() {
    $debug_mode = get_option('chatwoot_debug_mode', 'false') === 'true';
    
    if (isset($_COOKIE['chatwoot_reset_needed']) && $_COOKIE['chatwoot_reset_needed'] === '1') {
        // Clear the cookie
        setcookie('chatwoot_reset_needed', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        
        // Add the reset script
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Wait for Chatwoot to initialize
                var checkChatwootInterval = setInterval(function() {
                    if (window.$chatwoot && typeof window.$chatwoot.reset === 'function') {
                        clearInterval(checkChatwootInterval);
                        
                        <?php if ($debug_mode) : ?>
                        console.log('Chatwoot detected after logout - resetting session');
                        <?php endif; ?>
                        
                        window.$chatwoot.reset();
                        
                        <?php if ($debug_mode) : ?>
                        console.log('Chatwoot session reset complete');
                        <?php endif; ?>
                    }
                }, 1000);
                
                // Set a timeout to avoid infinite checking
                setTimeout(function() {
                    clearInterval(checkChatwootInterval);
                    <?php if ($debug_mode) : ?>
                    console.log('Timed out waiting for Chatwoot to initialize after logout');
                    <?php endif; ?>
                }, 10000);
            });
        </script>
        <?php
    }
}
add_action('wp_head', 'chatwoot_check_reset_needed', 999);

function chatwoot_identity_validation() {
    $chatwoot_base_url     = get_option('chatwoot_base_url', '');
    $chatwoot_widget_token = get_option('chatwoot_widget_token', '');
    $chatwoot_hmac_token   = get_option('chatwoot_hmac_token', '');
    $debug_mode            = get_option('chatwoot_debug_mode', 'false') === 'true';
    
    // Widget settings
    $hide_message_bubble   = get_option('chatwoot_hide_message_bubble', 'false') === 'true';
    $show_unread_dialog    = get_option('chatwoot_show_unread_dialog', 'false') === 'true';
    $widget_position       = get_option('chatwoot_widget_position', 'right');
    $widget_locale         = get_option('chatwoot_widget_locale', 'en');
    $use_browser_language  = get_option('chatwoot_use_browser_language', 'true') === 'true';
    $widget_type           = get_option('chatwoot_widget_type', 'standard');
    $dark_mode             = get_option('chatwoot_dark_mode', 'auto');
    
    if (empty($chatwoot_base_url) || empty($chatwoot_widget_token)) {
        return; // Do not load if required settings are missing
    }
    
    // Prepare user data if the user is logged in and HMAC token is available
    $user_data_json = 'null';
    $identifier = '';
    $user_phone = '';
    $user_country = '';
    $user_description = '';
    $auth_token = '';
    
    if (is_user_logged_in() && !empty($chatwoot_hmac_token)) {
        $user = wp_get_current_user();
        $identifier = $user->user_email;
        // Generate HMAC using the email as identifier
        $auth_token = hash_hmac('sha256', $identifier, $chatwoot_hmac_token);
        
        // Get WooCommerce customer data if available
        if (class_exists('WooCommerce')) {
            $customer = new WC_Customer($user->ID);
            
            // Get phone number
            $user_phone = $customer->get_billing_phone();
            
            // Get country
            $country_code = $customer->get_billing_country();
            if (!empty($country_code)) {
                // Convert country code to country name if WC is available
                $countries = WC()->countries->get_countries();
                $user_country = isset($countries[$country_code]) ? $countries[$country_code] : $country_code;
            }
            
            // Create a description with useful customer info
            $order_count = wc_get_customer_order_count($user->ID);
            $total_spent = wc_format_decimal(wc_get_customer_total_spent($user->ID), 2);
            
            $user_description = sprintf(
                'Customer since: %s | Orders: %d | Total spent: %s | Country: %s',
                date_i18n(get_option('date_format'), $customer->get_date_created()->getTimestamp()),
                $order_count,
                $total_spent,
                $user_country
            );
        }
        
        // Build user data to be passed to Chatwoot via setUser()
        $user_data = array(
            'email'            => $user->user_email,
            'name'             => $user->display_name,
            'identifier_hash'  => $auth_token,
            'phone_number'     => $user_phone,
            'description'      => $user_description,
            'custom_attributes'=> array(
                'customer_id'  => $user->ID,
                'country'      => $user_country
            )
        );
        
        $user_data_json = json_encode($user_data);
    }
    
    ?>
    <script>
        // Define Chatwoot settings first, before loading the SDK
        window.chatwootSettings = {
            hideMessageBubble: <?php echo $hide_message_bubble ? 'true' : 'false'; ?>,
            showUnreadMessagesDialog: <?php echo $show_unread_dialog ? 'true' : 'false'; ?>,
            position: "<?php echo esc_js($widget_position); ?>",
            locale: "<?php echo esc_js($widget_locale); ?>",
            useBrowserLanguage: <?php echo $use_browser_language ? 'true' : 'false'; ?>,
            type: "<?php echo esc_js($widget_type); ?>",
            darkMode: "<?php echo esc_js($dark_mode); ?>"
        };
        
        // Now load the SDK
        (function(d, t) {
            var BASE_URL = "<?php echo esc_js($chatwoot_base_url); ?>";
            var g = d.createElement(t), s = d.getElementsByTagName(t)[0];
            g.src = BASE_URL + '/packs/js/sdk.js';
            g.defer = true;
            g.async = true;
            g.id = 'chatwoot-sdk'; // Add ID for easier debugging
            s.parentNode.insertBefore(g, s);
            g.onload = function() {
                <?php if ($debug_mode) : ?>
                console.log('Chatwoot SDK script loaded');
                <?php endif; ?>
                
                if (window.chatwootSDK && typeof window.chatwootSDK.run === 'function') {
                    window.chatwootSDK.run({
                        websiteToken: '<?php echo esc_js($chatwoot_widget_token); ?>',
                        baseUrl: BASE_URL
                    });
                    
                    <?php if ($debug_mode) : ?>
                    console.log('Chatwoot SDK initialized with token and base URL');
                    <?php endif; ?>
                } else {
                    <?php if ($debug_mode) : ?>
                    console.error('Chatwoot SDK not available or missing run method');
                    <?php endif; ?>
                }
            }
        })(document, 'script');
    </script>
    
    <script>
        // Separate script for event handling to ensure clean initialization
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.$chatwoot === 'undefined') {
                window.addEventListener('chatwoot:ready', initializeChatwoot);
            } else {
                initializeChatwoot();
            }
            
            function initializeChatwoot() {
                // Add a small delay to ensure Chatwoot is fully initialized
                setTimeout(function() {
                    try {
                        <?php if ($debug_mode) : ?>
                        console.log('Chatwoot SDK loaded:', typeof window.chatwootSDK);
                        console.log('Chatwoot API available:', typeof window.$chatwoot);
                        <?php endif; ?>
                        
                        // First set user data if available
                        <?php if (is_user_logged_in() && !empty($chatwoot_hmac_token)) : ?>
                            if (window.$chatwoot && typeof window.$chatwoot.setUser === 'function') {
                                <?php if ($debug_mode) : ?>
                                // For debugging - show what we're sending
                                console.log('Setting user with identifier:', "<?php echo esc_js($identifier); ?>");
                                console.log('User data:', <?php echo $user_data_json; ?>);
                                <?php endif; ?>
                                
                                // Use the exact format from Chatwoot documentation
                                window.$chatwoot.setUser("<?php echo esc_js($identifier); ?>", {
                                    email: "<?php echo esc_js($user->user_email); ?>",
                                    name: "<?php echo esc_js($user->display_name); ?>", 
                                    identifier_hash: "<?php echo esc_js($auth_token); ?>",
                                    avatar_url: "",
                                    phone_number: "<?php echo esc_js($user_phone); ?>",
                                    description: "<?php echo esc_js($user_description); ?>",
                                    custom_attributes: {
                                        customer_id: "<?php echo esc_js($user->ID); ?>",
                                        country: "<?php echo esc_js($user_country); ?>"
                                    }
                                });
                                
                                <?php if ($debug_mode) : ?>
                                console.log('Chatwoot: User data set successfully');
                                <?php endif; ?>
                            } else {
                                <?php if ($debug_mode) : ?>
                                console.error('Chatwoot: setUser method not available');
                                <?php endif; ?>
                            }
                        <?php endif; ?>
                        
                        // Then set page attributes - with a slight delay to ensure they don't conflict
                        setTimeout(function() {
                            if (window.$chatwoot && typeof window.$chatwoot.setCustomAttributes === 'function') {
                                // Prepare page attributes
                                var pageAttributes = {
                                    page_url: window.location.href,
                                    page_title: document.title
                                };
                                
                                <?php if ($debug_mode) : ?>
                                console.log('Setting page attributes:', pageAttributes);
                                <?php endif; ?>
                                
                                window.$chatwoot.setCustomAttributes(pageAttributes);
                                
                                <?php if ($debug_mode) : ?>
                                console.log('Chatwoot: Page attributes set successfully');
                                <?php endif; ?>
                            } else {
                                <?php if ($debug_mode) : ?>
                                console.error('Chatwoot: setCustomAttributes method not available');
                                <?php endif; ?>
                            }
                        }, 500);
                    } catch(e) {
                        <?php if ($debug_mode) : ?>
                        console.error('Chatwoot initialization error:', e);
                        <?php endif; ?>
                    }
                }, 1500); // 1.5 second delay for initial setup
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'chatwoot_identity_validation');


// Create settings page for Chatwoot configuration
function chatwoot_identity_validation_settings() {
    add_options_page(
        'Chatwoot Identity Validation',
        'Chatwoot Identity Validation',
        'manage_options',
        'chatwoot-identity-validation',
        'chatwoot_identity_validation_settings_page'
    );
}
add_action('admin_menu', 'chatwoot_identity_validation_settings');

function chatwoot_identity_validation_settings_page() {
    ?>
    <div class="wrap">
        <h1>Chatwoot Identity Validation Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('chatwoot-settings-group');
                do_settings_sections('chatwoot-settings-group');
            ?>
            
            <h2 class="title">Connection Settings</h2>
            <table class="form-table">
                <tr>
                    <th><label for="chatwoot_base_url">Chatwoot Base URL</label></th>
                    <td>
                        <input type="text" name="chatwoot_base_url" id="chatwoot_base_url" value="<?php echo esc_attr(get_option('chatwoot_base_url')); ?>" class="regular-text">
                        <p class="description">Enter your Chatwoot instance URL (e.g., https://app.chatwoot.com)</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="chatwoot_widget_token">Chatwoot Widget Token</label></th>
                    <td>
                        <input type="text" name="chatwoot_widget_token" id="chatwoot_widget_token" value="<?php echo esc_attr(get_option('chatwoot_widget_token')); ?>" class="regular-text">
                        <p class="description">Enter the Website Token from your Chatwoot inbox settings</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="chatwoot_hmac_token">Chatwoot HMAC Token</label></th>
                    <td>
                        <input type="text" name="chatwoot_hmac_token" id="chatwoot_hmac_token" value="<?php echo esc_attr(get_option('chatwoot_hmac_token')); ?>" class="regular-text">
                        <p class="description">Enter your HMAC token for secure identity validation (optional)</p>
                    </td>
                </tr>
            </table>
            
            <h2 class="title">Widget Settings</h2>
            <table class="form-table">
                <tr>
                    <th><label for="chatwoot_widget_position">Widget Position</label></th>
                    <td>
                        <select name="chatwoot_widget_position" id="chatwoot_widget_position">
                            <option value="left" <?php selected(get_option('chatwoot_widget_position', 'right'), 'left'); ?>>Left</option>
                            <option value="right" <?php selected(get_option('chatwoot_widget_position', 'right'), 'right'); ?>>Right</option>
                        </select>
                        <p class="description">Position of the chat widget on the screen</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="chatwoot_widget_locale">Widget Language</label></th>
                    <td>
                        <input type="text" name="chatwoot_widget_locale" id="chatwoot_widget_locale" value="<?php echo esc_attr(get_option('chatwoot_widget_locale', 'en')); ?>" class="regular-text">
                        <p class="description">Language code for the widget (e.g., en, fr, es)</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="chatwoot_use_browser_language">Use Browser Language</label></th>
                    <td>
                        <select name="chatwoot_use_browser_language" id="chatwoot_use_browser_language">
                            <option value="true" <?php selected(get_option('chatwoot_use_browser_language', 'true'), 'true'); ?>>Yes</option>
                            <option value="false" <?php selected(get_option('chatwoot_use_browser_language', 'true'), 'false'); ?>>No</option>
                        </select>
                        <p class="description">Use the browser's language setting</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="chatwoot_widget_type">Widget Type</label></th>
                    <td>
                        <select name="chatwoot_widget_type" id="chatwoot_widget_type">
                            <option value="standard" <?php selected(get_option('chatwoot_widget_type', 'standard'), 'standard'); ?>>Standard</option>
                            <option value="expanded_bubble" <?php selected(get_option('chatwoot_widget_type', 'standard'), 'expanded_bubble'); ?>>Expanded Bubble</option>
                        </select>
                        <p class="description">Layout type of the widget</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="chatwoot_dark_mode">Dark Mode</label></th>
                    <td>
                        <select name="chatwoot_dark_mode" id="chatwoot_dark_mode">
                            <option value="auto" <?php selected(get_option('chatwoot_dark_mode', 'auto'), 'auto'); ?>>Auto (follow system)</option>
                            <option value="light" <?php selected(get_option('chatwoot_dark_mode', 'auto'), 'light'); ?>>Light Mode</option>
                            <option value="dark" <?php selected(get_option('chatwoot_dark_mode', 'auto'), 'dark'); ?>>Dark Mode</option>
                        </select>
                        <p class="description">Color theme for the widget</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="chatwoot_hide_message_bubble">Hide Message Bubble</label></th>
                    <td>
                        <select name="chatwoot_hide_message_bubble" id="chatwoot_hide_message_bubble">
                            <option value="false" <?php selected(get_option('chatwoot_hide_message_bubble', 'false'), 'false'); ?>>Show</option>
                            <option value="true" <?php selected(get_option('chatwoot_hide_message_bubble', 'false'), 'true'); ?>>Hide</option>
                        </select>
                        <p class="description">Show or hide the message bubble</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="chatwoot_show_unread_dialog">Show Unread Messages Dialog</label></th>
                    <td>
                        <select name="chatwoot_show_unread_dialog" id="chatwoot_show_unread_dialog">
                            <option value="false" <?php selected(get_option('chatwoot_show_unread_dialog', 'false'), 'false'); ?>>No</option>
                            <option value="true" <?php selected(get_option('chatwoot_show_unread_dialog', 'false'), 'true'); ?>>Yes</option>
                        </select>
                        <p class="description">Show a dialog for unread messages</p>
                    </td>
                </tr>
            </table>
            
            <h2 class="title">Advanced Settings</h2>
            <table class="form-table">
                <tr>
                    <th><label for="chatwoot_debug_mode">Debug Mode</label></th>
                    <td>
                        <select name="chatwoot_debug_mode" id="chatwoot_debug_mode">
                            <option value="false" <?php selected(get_option('chatwoot_debug_mode', 'false'), 'false'); ?>>Off</option>
                            <option value="true" <?php selected(get_option('chatwoot_debug_mode', 'false'), 'true'); ?>>On</option>
                        </select>
                        <p class="description">Enable console logging for debugging purposes. Only turn this on when troubleshooting issues.</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 4px;">
            <h2 style="margin-top: 0;">How to Use</h2>
            <ol>
                <li><strong>Connection Settings:</strong>
                    <ul>
                        <li>Enter your Chatwoot Base URL (your Chatwoot instance URL)</li>
                        <li>Enter the Website Token from your Chatwoot inbox settings</li>
                        <li>For secure identity validation, enter your HMAC token (optional but recommended)</li>
                    </ul>
                </li>
                <li><strong>Widget Settings:</strong>
                    <ul>
                        <li>Customize how the chat widget appears on your website</li>
                        <li>Adjust the position, language, color theme, and other visual elements</li>
                    </ul>
                </li>
                <li><strong>Advanced Settings:</strong>
                    <ul>
                        <li>Debug Mode: Enable console logs to troubleshoot issues</li>
                    </ul>
                </li>
            </ol>
        </div>
    </div>
    <?php
}

function chatwoot_identity_validation_register_settings() {
    // Connection settings
    register_setting('chatwoot-settings-group', 'chatwoot_base_url');
    register_setting('chatwoot-settings-group', 'chatwoot_widget_token');
    register_setting('chatwoot-settings-group', 'chatwoot_hmac_token');
    
    // Widget appearance settings
    register_setting('chatwoot-settings-group', 'chatwoot_hide_message_bubble');
    register_setting('chatwoot-settings-group', 'chatwoot_show_unread_dialog');
    register_setting('chatwoot-settings-group', 'chatwoot_widget_position');
    register_setting('chatwoot-settings-group', 'chatwoot_widget_locale');
    register_setting('chatwoot-settings-group', 'chatwoot_use_browser_language');
    register_setting('chatwoot-settings-group', 'chatwoot_widget_type');
    register_setting('chatwoot-settings-group', 'chatwoot_dark_mode');
    
    // Advanced settings
    register_setting('chatwoot-settings-group', 'chatwoot_debug_mode');
}
add_action('admin_init', 'chatwoot_identity_validation_register_settings');