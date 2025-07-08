<?php
/**
 * Plugin Name: MailWish SMTP
 * Plugin URI: https://mailwish.com/smtp-security-delivery/
 * Description: Configure WordPress to send emails via MailWish SMTP service. Just $0.10 per 1,000 emails - blazing-fast delivery with inbox-focused performance.
 * Version: 1.0.0
 * Author: MailWish
 * Author URI: https://mailwish.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mailwish-smtp
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MAILWISH_SMTP_VERSION', '1.0.0');
define('MAILWISH_SMTP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAILWISH_SMTP_PLUGIN_PATH', plugin_dir_path(__FILE__));

class MailWishSMTP {
    
    private $options;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('phpmailer_init', array($this, 'configure_smtp'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Add settings link on plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Override WordPress default mail function
        add_action('phpmailer_init', array($this, 'configure_smtp'), 999);
        
        // Load options
        $this->options = get_option('mailwish_smtp_options');
    }
    
    public function init() {
        load_plugin_textdomain('mailwish-smtp', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_admin_scripts($hook) {
        // Only load on our settings page
        if ($hook !== 'toplevel_page_mailwish-smtp') {
            return;
        }
        
        wp_enqueue_style(
            'mailwish-smtp-admin',
            MAILWISH_SMTP_PLUGIN_URL . 'assets/admin-style.css',
            array(),
            MAILWISH_SMTP_VERSION
        );
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('MailWish SMTP Settings', 'mailwish-smtp'),
            __('MailWish SMTP', 'mailwish-smtp'),
            'manage_options',
            'mailwish-smtp',
            array($this, 'options_page'),
            'dashicons-email-alt',
            30
        );
    }
    
    public function settings_init() {
        register_setting('mailwish_smtp', 'mailwish_smtp_options', array($this, 'sanitize_options'));
        
        add_settings_section(
            'mailwish_smtp_section',
            __('SMTP Configuration', 'mailwish-smtp'),
            array($this, 'settings_section_callback'),
            'mailwish_smtp'
        );
        
        add_settings_field(
            'email_provider',
            __('Email Provider', 'mailwish-smtp'),
            array($this, 'email_provider_render'),
            'mailwish_smtp',
            'mailwish_smtp_section'
        );
        
        add_settings_field(
            'smtp_host',
            __('SMTP Host', 'mailwish-smtp'),
            array($this, 'smtp_host_render'),
            'mailwish_smtp',
            'mailwish_smtp_section'
        );
        
        add_settings_field(
            'smtp_port',
            __('SMTP Port', 'mailwish-smtp'),
            array($this, 'smtp_port_render'),
            'mailwish_smtp',
            'mailwish_smtp_section'
        );
        
        add_settings_field(
            'smtp_security',
            __('Security', 'mailwish-smtp'),
            array($this, 'smtp_security_render'),
            'mailwish_smtp',
            'mailwish_smtp_section'
        );
        
        add_settings_field(
            'smtp_username',
            __('Username', 'mailwish-smtp'),
            array($this, 'smtp_username_render'),
            'mailwish_smtp',
            'mailwish_smtp_section'
        );
        
        add_settings_field(
            'smtp_password',
            __('Password', 'mailwish-smtp'),
            array($this, 'smtp_password_render'),
            'mailwish_smtp',
            'mailwish_smtp_section'
        );
        
        add_settings_field(
            'from_email',
            __('From Email', 'mailwish-smtp'),
            array($this, 'from_email_render'),
            'mailwish_smtp',
            'mailwish_smtp_section'
        );
        
        add_settings_field(
            'from_name',
            __('From Name', 'mailwish-smtp'),
            array($this, 'from_name_render'),
            'mailwish_smtp',
            'mailwish_smtp_section'
        );
    }
    
    public function get_email_providers() {
        return array(
            'mailwish' => array(
                'name' => 'MailWish',
                'icon' => '🚀',
                'badge' => 'Recommended - Cheapest $0.10/1000 emails',
                'badge_class' => 'recommended',
                'host' => 'smtp.mailwish.com',
                'port' => 587,
                'security' => 'tls',
                'username_label' => 'Username',
                'password_label' => 'Password',
                'description' => 'Blazing-fast SMTP with inbox-focused delivery',
                'signup_url' => 'https://mailwish.com'
            ),
            'gmail' => array(
                'name' => 'Gmail',
                'icon' => '📧',
                'badge' => 'Popular',
                'badge_class' => 'popular',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'security' => 'tls',
                'username_label' => 'Email Address',
                'password_label' => 'App Password',
                'description' => 'Use Gmail SMTP with App Password (2FA required)',
                'signup_url' => 'https://accounts.google.com'
            ),
            'sendinblue' => array(
                'name' => 'Brevo (SendinBlue)',
                'icon' => '💙',
                'badge' => 'Free Tier',
                'badge_class' => 'free',
                'host' => 'smtp-relay.brevo.com',
                'port' => 587,
                'security' => 'tls',
                'username_label' => 'Email Address',
                'password_label' => 'SMTP Key',
                'description' => '300 emails/day free, then paid plans',
                'signup_url' => 'https://www.brevo.com'
            ),
            'sendgrid' => array(
                'name' => 'SendGrid',
                'icon' => '📬',
                'badge' => 'Reliable',
                'badge_class' => 'reliable',
                'host' => 'smtp.sendgrid.net',
                'port' => 587,
                'security' => 'tls',
                'username_label' => 'Username',
                'password_label' => 'API Key',
                'description' => 'Trusted by developers worldwide',
                'signup_url' => 'https://sendgrid.com'
            ),
            'amazonses' => array(
                'name' => 'Amazon SES',
                'icon' => '📦',
                'badge' => 'AWS',
                'badge_class' => 'aws',
                'host' => 'email-smtp.us-east-1.amazonaws.com',
                'port' => 587,
                'security' => 'tls',
                'username_label' => 'SMTP Username',
                'password_label' => 'SMTP Password',
                'description' => 'Scalable email service from Amazon Web Services',
                'signup_url' => 'https://aws.amazon.com/ses/'
            ),
            'custom' => array(
                'name' => 'Custom SMTP',
                'icon' => '⚙️',
                'badge' => 'Custom',
                'badge_class' => 'custom',
                'host' => '',
                'port' => 587,
                'security' => 'tls',
                'username_label' => 'Username',
                'password_label' => 'Password',
                'description' => 'Configure your own SMTP server',
                'signup_url' => ''
            )
        );
    }
    
    public function email_provider_render() {
        $current_provider = isset($this->options['email_provider']) ? $this->options['email_provider'] : 'mailwish';
        $providers = $this->get_email_providers();
        
        echo '<div class="provider-selector-container">';
        echo '<input type="hidden" name="mailwish_smtp_options[email_provider]" id="selected_provider" value="' . esc_attr($current_provider) . '" />';
        echo '<button type="button" class="provider-selector-btn" id="provider-selector-btn">';
        echo '<span class="provider-icon">' . $providers[$current_provider]['icon'] . '</span>';
        echo '<span class="provider-name">' . esc_html($providers[$current_provider]['name']) . '</span>';
        echo '<span class="provider-arrow">▼</span>';
        echo '</button>';
        echo '<span style="margin-left: 10px; font-size: 12px; color: #667eea; font-weight: 500;">Click to switch provider</span>';
        echo '</div>';
        echo '<p class="description">' . __('Choose your email service provider for quick configuration', 'mailwish-smtp') . '</p>';
    }
    
    public function smtp_host_render() {
        $value = isset($this->options['smtp_host']) ? $this->options['smtp_host'] : 'smtp.mailwish.com';
        echo '<input type="text" name="mailwish_smtp_options[smtp_host]" id="smtp_host" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('SMTP server hostname', 'mailwish-smtp') . '</p>';
    }
    
    public function smtp_port_render() {
        $value = isset($this->options['smtp_port']) ? $this->options['smtp_port'] : '587';
        echo '<input type="number" name="mailwish_smtp_options[smtp_port]" value="' . esc_attr($value) . '" class="small-text" />';
        echo '<p class="description">' . __('Default: 587 (recommended), Alternative: 25, 465, 2525', 'mailwish-smtp') . '</p>';
    }
    
    public function smtp_security_render() {
        $value = isset($this->options['smtp_security']) ? $this->options['smtp_security'] : 'tls';
        echo '<select name="mailwish_smtp_options[smtp_security]">';
        echo '<option value="tls"' . selected($value, 'tls', false) . '>TLS</option>';
        echo '<option value="ssl"' . selected($value, 'ssl', false) . '>SSL</option>';
        echo '<option value="none"' . selected($value, 'none', false) . '>' . __('None', 'mailwish-smtp') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('TLS is recommended for port 587, SSL for port 465', 'mailwish-smtp') . '</p>';
    }
    
    public function smtp_username_render() {
        $value = isset($this->options['smtp_username']) ? $this->options['smtp_username'] : '';
        echo '<input type="text" name="mailwish_smtp_options[smtp_username]" value="' . esc_attr($value) . '" class="regular-text" placeholder="user1" />';
        echo '<p class="description">' . __('Your MailWish SMTP username', 'mailwish-smtp') . '</p>';
    }
    
    public function smtp_password_render() {
        $has_password = !empty($this->options['smtp_password']);
        echo '<input type="password" name="mailwish_smtp_options[smtp_password]" value="" class="regular-text" placeholder="' . ($has_password ? __('Password is set - enter new password to change', 'mailwish-smtp') : __('Enter your password', 'mailwish-smtp')) . '" />';
        if ($has_password) {
            echo '<p class="description" style="color: #28a745; font-weight: 600;">✅ ' . __('Password is securely stored and encrypted', 'mailwish-smtp') . '</p>';
        } else {
            echo '<p class="description">' . __('Your MailWish SMTP password', 'mailwish-smtp') . '</p>';
        }
    }
    
    public function from_email_render() {
        $value = isset($this->options['from_email']) ? $this->options['from_email'] : get_option('admin_email');
        echo '<input type="email" name="mailwish_smtp_options[from_email]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('The email address to send from', 'mailwish-smtp') . '</p>';
    }
    
    public function from_name_render() {
        $value = isset($this->options['from_name']) ? $this->options['from_name'] : get_bloginfo('name');
        echo '<input type="text" name="mailwish_smtp_options[from_name]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('The name to send from', 'mailwish-smtp') . '</p>';
    }
    
    public function settings_section_callback() {
        echo '<p>' . __('Configure your MailWish SMTP settings below. Need an account?', 'mailwish-smtp') . ' <a href="https://mailwish.com" target="_blank">' . __('Get MailWish SMTP for just $0.10 per 1,000 emails!', 'mailwish-smtp') . '</a></p>';
    }
    
    public function options_page() {
        // Handle settings save and test connection
        $connection_tested = false;
        $smtp_status = null;
        $test_email_result = null;
        
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'mailwish_smtp-options')) {
            // Settings were just saved, test the connection
            $this->options = get_option('mailwish_smtp_options'); // Reload options
            $connection_tested = true;
            $smtp_status = $this->get_smtp_status_after_save();
        }
        
        // Handle test email
        if (isset($_POST['send_test_email']) && wp_verify_nonce($_POST['mailwish_test_nonce'], 'mailwish_test_email')) {
            $test_email_result = $this->send_test_email();
        }
        
        // Get current SMTP status for display
        $current_status = $this->get_current_smtp_status();
        ?>
        <div class="wrap mailwish-smtp-admin">
            <!-- Hide other plugin notices on our page -->
            <style>
                .mailwish-smtp-admin .notice:not(.mailwish-notice):not(.mailwish-conflict-notice):not(.test-email-result) {
                    display: none !important;
                }
            </style>
            
            <div class="mailwish-smtp-header">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <p><?php _e('Professional SMTP Email Delivery for WordPress', 'mailwish-smtp'); ?></p>
            </div>
            
            <!-- Important Notice -->
            <div class="alert alert-info">
                <div class="alert-content">
                    <strong>⚠️ Important:</strong> Do not use any other SMTP plugins while MailWish SMTP is active. This plugin replaces WordPress default mail function.
                </div>
            </div>
            
            <!-- MailWish Promotion -->
            <div class="mailwish-promo-banner">
                <div class="promo-content">
                    <h3>🚀 MailWish SMTP - Game Changer!</h3>
                    <p>💸 Just $0.10 per 1,000 Emails with blazing-fast SMTP performance and inbox-focused delivery!</p>
                    <a href="https://mailwish.com" target="_blank" class="btn btn-primary promo-button">Get MailWish SMTP Now →</a>
                </div>
            </div>
            
            <!-- SMTP Configuration Card -->
            <div class="card smtp-config-card">
                <div class="card-header">
                    <h2>SMTP Configuration</h2>
                    <!-- SMTP Status Indicator -->
                    <div class="smtp-status-badge <?php echo esc_attr($current_status['class']); ?>">
                        <span class="status-icon"><?php echo $current_status['icon']; ?></span>
                        <span class="status-text"><?php echo esc_html($current_status['message']); ?></span>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Show connection result after save -->
                    <?php if ($connection_tested && $smtp_status): ?>
                        <div class="alert <?php echo esc_attr($smtp_status['alert_class']); ?>">
                            <div class="alert-content">
                                <span class="alert-icon"><?php echo $smtp_status['icon']; ?></span>
                                <strong><?php echo esc_html($smtp_status['message']); ?></strong>
                                <?php if (!empty($smtp_status['description'])): ?>
                                    <p><?php echo esc_html($smtp_status['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form action="options.php" method="post">
                        <?php
                        settings_fields('mailwish_smtp');
                        do_settings_sections('mailwish_smtp');
                        submit_button(__('Save SMTP Settings', 'mailwish-smtp'), 'primary', 'submit', true, array('class' => 'btn btn-primary btn-lg'));
                        ?>
                    </form>
                </div>
            </div>
            
            <!-- Test Email Card -->
            <div class="card">
                <div class="card-header">
                    <h2><?php _e('Test Email', 'mailwish-smtp'); ?></h2>
                </div>
                <div class="card-body">
                    <!-- Test Email Result -->
                    <?php if ($test_email_result): ?>
                        <div class="alert <?php echo $test_email_result['success'] ? 'alert-success' : 'alert-danger'; ?>">
                            <div class="alert-content">
                                <span class="alert-icon"><?php echo $test_email_result['success'] ? '✅' : '❌'; ?></span>
                                <strong><?php echo esc_html($test_email_result['message']); ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <p><?php _e('Send a test email to verify your SMTP configuration:', 'mailwish-smtp'); ?></p>
                    <form method="post" action="">
                        <?php wp_nonce_field('mailwish_test_email', 'mailwish_test_nonce'); ?>
                        <div class="form-group">
                            <label for="test_email"><?php _e('To Email', 'mailwish-smtp'); ?></label>
                            <input type="email" name="test_email" id="test_email" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label for="test_subject"><?php _e('Subject', 'mailwish-smtp'); ?></label>
                            <input type="text" name="test_subject" id="test_subject" value="MailWish SMTP Test Email" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label for="test_message"><?php _e('Message', 'mailwish-smtp'); ?></label>
                            <textarea name="test_message" id="test_message" rows="4" class="form-control">This is a test email sent via MailWish SMTP plugin for WordPress.</textarea>
                        </div>
                        <button type="submit" name="send_test_email" class="btn btn-secondary btn-lg">
                            <?php _e('Send Test Email', 'mailwish-smtp'); ?>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <h2><?php _e('About MailWish SMTP', 'mailwish-smtp'); ?></h2>
                <p><?php _e('MailWish SMTP offers:', 'mailwish-smtp'); ?></p>
                <ul>
                    <li>✅ <?php _e('Blazing-fast SMTP performance', 'mailwish-smtp'); ?></li>
                    <li>✅ <?php _e('Inbox-focused delivery', 'mailwish-smtp'); ?></li>
                    <li>✅ <?php _e('No monthly minimums – pay as you go', 'mailwish-smtp'); ?></li>
                    <li>✅ <?php _e('Perfect for transactional & bulk emails', 'mailwish-smtp'); ?></li>
                    <li>✅ <?php _e('Set up in minutes, scale in seconds', 'mailwish-smtp'); ?></li>
                </ul>
                <p><a href="https://mailwish.com" target="_blank" class="button button-primary"><?php _e('Learn More & Sign Up', 'mailwish-smtp'); ?></a></p>
            </div>
            
            <!-- Provider Selector Popup -->
            <div id="provider-popup" class="provider-popup" style="display: none;">
                <div class="provider-popup-content">
                    <div class="provider-popup-header">
                        <h3><?php _e('Choose Email Provider', 'mailwish-smtp'); ?></h3>
                        <button type="button" class="provider-popup-close" id="provider-popup-close">×</button>
                    </div>
                    <div class="provider-popup-body">
                        <?php
                        $providers = $this->get_email_providers();
                        foreach ($providers as $key => $provider) {
                            echo '<div class="provider-option" data-provider="' . esc_attr($key) . '">';
                            echo '<div class="provider-icon">' . $provider['icon'] . '</div>';
                            echo '<div class="provider-info">';
                            echo '<div class="provider-name">' . esc_html($provider['name']) . '</div>';
                            echo '<div class="provider-badge provider-badge-' . esc_attr($provider['badge_class']) . '">' . esc_html($provider['badge']) . '</div>';
                            echo '<div class="provider-description">' . esc_html($provider['description']) . '</div>';
                            if (!empty($provider['signup_url'])) {
                                echo '<a href="' . esc_url($provider['signup_url']) . '" target="_blank" class="provider-signup">Sign Up →</a>';
                            }
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var providers = <?php echo json_encode($this->get_email_providers()); ?>;
                
                // Provider selector button click
                $('#provider-selector-btn').on('click', function(e) {
                    e.preventDefault();
                    $('#provider-popup').show();
                });
                
                // Close popup
                $('#provider-popup-close, #provider-popup').on('click', function(e) {
                    if (e.target === this) {
                        $('#provider-popup').hide();
                    }
                });
                
                // Provider selection
                $('.provider-option').on('click', function() {
                    var providerId = $(this).data('provider');
                    var provider = providers[providerId];
                    
                    // Update hidden field
                    $('#selected_provider').val(providerId);
                    
                    // Update button display
                    $('#provider-selector-btn .provider-icon').text(provider.icon);
                    $('#provider-selector-btn .provider-name').text(provider.name);
                    
                    // Update form fields
                    $('#smtp_host').val(provider.host);
                    $('input[name="mailwish_smtp_options[smtp_port]"]').val(provider.port);
                    $('select[name="mailwish_smtp_options[smtp_security]"]').val(provider.security);
                    
                    // Close popup
                    $('#provider-popup').hide();
                    
                    // Show success message
                    var message = '<div class="alert alert-info" style="margin-top: 15px;"><div class="alert-content"><span class="alert-icon">ℹ️</span><strong>Provider Updated:</strong> Form fields configured for ' + provider.name + '. Please enter your credentials and save.</div></div>';
                    $('.smtp-config-card .card-body form').before(message);
                    
                    // Remove message after 5 seconds
                    setTimeout(function() {
                        $('.alert-info').fadeOut();
                    }, 5000);
                });
            });
            </script>
        </div>
        <?php
    }
    
    public function send_test_email() {
        $to = sanitize_email($_POST['test_email']);
        $subject = sanitize_text_field($_POST['test_subject']);
        $message = sanitize_textarea_field($_POST['test_message']);
        
        $result = wp_mail($to, $subject, $message);
        
        if ($result) {
            return array(
                'success' => true,
                'message' => __('Test email sent successfully!', 'mailwish-smtp')
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Failed to send test email. Please check your SMTP settings.', 'mailwish-smtp')
            );
        }
    }
    
    public function configure_smtp($phpmailer) {
        // Only configure if we have the necessary settings
        if (empty($this->options['smtp_username']) || empty($this->options['smtp_password'])) {
            return;
        }
        
        $phpmailer->isSMTP();
        $phpmailer->Host = isset($this->options['smtp_host']) ? $this->options['smtp_host'] : 'smtp.mailwish.com';
        $phpmailer->Port = isset($this->options['smtp_port']) ? intval($this->options['smtp_port']) : 587;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $this->options['smtp_username'];
        $phpmailer->Password = $this->get_decrypted_password();
        
        // Set security
        $security = isset($this->options['smtp_security']) ? $this->options['smtp_security'] : 'tls';
        if ($security === 'tls') {
            $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($security === 'ssl') {
            $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }
        
        // Auto-detect port and security if using defaults
        if ($phpmailer->Port == 465 && $security === 'tls') {
            $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($phpmailer->Port == 587 && $security === 'ssl') {
            $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        // Set From email and name
        if (!empty($this->options['from_email'])) {
            $phpmailer->setFrom($this->options['from_email'], 
                               isset($this->options['from_name']) ? $this->options['from_name'] : '');
        }
        
        // Enable SMTP debugging for admins
        if (current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) {
            $phpmailer->SMTPDebug = 2;
        }
    }
    
    public function admin_notices() {
        // Only show on our settings page
        $screen = get_current_screen();
        if ($screen->id !== 'toplevel_page_mailwish-smtp') {
            return;
        }
        
        // Check for conflicting SMTP plugins
        $conflicting_plugins = array();
        if (is_plugin_active('wp-mail-smtp/wp_mail_smtp.php')) {
            $conflicting_plugins[] = 'WP Mail SMTP';
        }
        if (is_plugin_active('easy-wp-smtp/easy-wp-smtp.php')) {
            $conflicting_plugins[] = 'Easy WP SMTP';
        }
        if (is_plugin_active('wp-smtp/wp-smtp.php')) {
            $conflicting_plugins[] = 'WP SMTP';
        }
        if (is_plugin_active('smtp-mailer/smtp-mailer.php')) {
            $conflicting_plugins[] = 'SMTP Mailer';
        }
        
        if (!empty($conflicting_plugins)) {
            echo '<div class="notice notice-warning is-dismissible mailwish-conflict-notice">';
            echo '<p><strong>' . __('⚠️ SMTP Plugin Conflict Detected!', 'mailwish-smtp') . '</strong></p>';
            echo '<p>' . sprintf(__('The following SMTP plugins are active and may conflict with MailWish SMTP: %s', 'mailwish-smtp'), implode(', ', $conflicting_plugins)) . '</p>';
            echo '<p>' . __('For best results, please deactivate other SMTP plugins and use only MailWish SMTP.', 'mailwish-smtp') . '</p>';
            echo '</div>';
        }
    }
    
    public function get_smtp_status() {
        if (empty($this->options['smtp_username']) || empty($this->options['smtp_password'])) {
            return array(
                'status' => 'not_configured',
                'message' => __('SMTP not configured', 'mailwish-smtp'),
                'class' => 'notice-warning'
            );
        }
        
        // Check if we can create a test connection
        try {
            $test_result = $this->test_smtp_connection();
            if ($test_result) {
                return array(
                    'status' => 'connected',
                    'message' => __('SMTP Connected & Active', 'mailwish-smtp'),
                    'class' => 'notice-success'
                );
            } else {
                return array(
                    'status' => 'error',
                    'message' => __('SMTP Configuration Error', 'mailwish-smtp'),
                    'class' => 'notice-error'
                );
            }
        } catch (Exception $e) {
            return array(
                'status' => 'configured',
                'message' => __('SMTP Configured (Connection not tested)', 'mailwish-smtp'),
                'class' => 'notice-info'
            );
        }
    }
    
    public function get_current_smtp_status() {
        if (empty($this->options['smtp_username']) || empty($this->options['smtp_password'])) {
            return array(
                'status' => 'not_configured',
                'message' => __('Not Configured', 'mailwish-smtp'),
                'icon' => '❌',
                'class' => 'status-error'
            );
        }
        
        // Test the actual connection
        $test_result = $this->test_smtp_connection();
        if ($test_result) {
            return array(
                'status' => 'connected',
                'message' => __('Connected', 'mailwish-smtp'),
                'icon' => '✅',
                'class' => 'status-success'
            );
        } else {
            return array(
                'status' => 'error',
                'message' => __('Connection Failed', 'mailwish-smtp'),
                'icon' => '❌',
                'class' => 'status-error'
            );
        }
    }
    
    public function get_smtp_status_after_save() {
        if (empty($this->options['smtp_username']) || empty($this->options['smtp_password'])) {
            return array(
                'status' => 'not_configured',
                'message' => __('SMTP Not Configured', 'mailwish-smtp'),
                'icon' => '❌',
                'alert_class' => 'alert-danger',
                'description' => __('Please enter your MailWish SMTP username and password.', 'mailwish-smtp')
            );
        }
        
        // Test the actual connection
        $test_result = $this->test_smtp_connection();
        if ($test_result) {
            return array(
                'status' => 'connected',
                'message' => __('SMTP Connected & Active', 'mailwish-smtp'),
                'icon' => '✅',
                'alert_class' => 'alert-success',
                'description' => __('MailWish SMTP is now handling all WordPress emails.', 'mailwish-smtp')
            );
        } else {
            return array(
                'status' => 'error',
                'message' => __('SMTP Connection Failed', 'mailwish-smtp'),
                'icon' => '❌',
                'alert_class' => 'alert-danger',
                'description' => __('Unable to connect to MailWish SMTP server. Please check your credentials.', 'mailwish-smtp')
            );
        }
    }
    
    public function test_smtp_connection() {
        if (empty($this->options['smtp_username']) || empty($this->options['smtp_password'])) {
            return false;
        }
        
        // Use WordPress PHPMailer to test actual SMTP authentication
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = isset($this->options['smtp_host']) ? $this->options['smtp_host'] : 'smtp.mailwish.com';
            $mail->Port = isset($this->options['smtp_port']) ? intval($this->options['smtp_port']) : 587;
            $mail->SMTPAuth = true;
            $mail->Username = $this->options['smtp_username'];
            $mail->Password = $this->get_decrypted_password();
            
            // Set security
            $security = isset($this->options['smtp_security']) ? $this->options['smtp_security'] : 'tls';
            if ($security === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($security === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
            
            // Auto-detect port and security if using defaults
            if ($mail->Port == 465 && $security === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($mail->Port == 587 && $security === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Set timeout
            $mail->Timeout = 10;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Test the connection by connecting and authenticating
            $mail->smtpConnect();
            $mail->smtpClose();
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function sanitize_options($input) {
        $sanitized = array();
        
        // Sanitize all fields
        $sanitized['email_provider'] = sanitize_text_field($input['email_provider']);
        $sanitized['smtp_host'] = sanitize_text_field($input['smtp_host']);
        $sanitized['smtp_port'] = intval($input['smtp_port']);
        $sanitized['smtp_security'] = sanitize_text_field($input['smtp_security']);
        $sanitized['smtp_username'] = sanitize_text_field($input['smtp_username']);
        $sanitized['from_email'] = sanitize_email($input['from_email']);
        $sanitized['from_name'] = sanitize_text_field($input['from_name']);
        
        // Handle password encryption
        if (!empty($input['smtp_password'])) {
            // New password provided, encrypt it
            $sanitized['smtp_password'] = $this->encrypt_password($input['smtp_password']);
        } else {
            // No new password, keep existing encrypted password
            $existing_options = get_option('mailwish_smtp_options', array());
            $sanitized['smtp_password'] = isset($existing_options['smtp_password']) ? $existing_options['smtp_password'] : '';
        }
        
        return $sanitized;
    }
    
    private function encrypt_password($password) {
        if (empty($password)) {
            return '';
        }
        
        // Use WordPress salts for encryption key
        $key = wp_salt('auth') . wp_salt('secure_auth');
        $key = hash('sha256', $key);
        
        // Generate a random IV
        $iv = openssl_random_pseudo_bytes(16);
        
        // Encrypt the password
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
        
        // Combine IV and encrypted data
        return base64_encode($iv . $encrypted);
    }
    
    private function decrypt_password($encrypted_password) {
        if (empty($encrypted_password)) {
            return '';
        }
        
        try {
            // Use WordPress salts for encryption key
            $key = wp_salt('auth') . wp_salt('secure_auth');
            $key = hash('sha256', $key);
            
            // Decode the encrypted data
            $data = base64_decode($encrypted_password);
            
            // Extract IV and encrypted password
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            // Decrypt the password
            return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        } catch (Exception $e) {
            return '';
        }
    }
    
    private function get_decrypted_password() {
        if (empty($this->options['smtp_password'])) {
            return '';
        }
        
        return $this->decrypt_password($this->options['smtp_password']);
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=mailwish-smtp') . '">' . __('Settings', 'mailwish-smtp') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
new MailWishSMTP();

// Activation hook
register_activation_hook(__FILE__, 'mailwish_smtp_activate');
function mailwish_smtp_activate() {
    // Set default options
    $default_options = array(
        'smtp_host' => 'smtp.mailwish.com',
        'smtp_port' => '587',
        'smtp_security' => 'tls',
        'from_email' => get_option('admin_email'),
        'from_name' => get_bloginfo('name')
    );
    
    add_option('mailwish_smtp_options', $default_options);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'mailwish_smtp_deactivate');
function mailwish_smtp_deactivate() {
    // Clean up if needed
}
?>
