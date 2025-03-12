=== Chatwoot Identity Validation for WooCommerce ===
Contributors: mlisb0n
Tags: chatwoot, woocommerce, customer support, live chat, widget, hmac, identity validation
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate Chatwoot with WooCommerce to provide personalized customer support with secure identity validation.

== Description ==

Chatwoot Identity Validation for WooCommerce connects your WordPress site with Chatwoot, a powerful open-source customer support platform. This integration enhances the customer support experience by providing your support agents with valuable customer information directly in the chat interface.

### Key Features

* **Secure Identity Validation**: Uses HMAC token verification to securely identify users
* **WooCommerce Integration**: Automatically shares relevant customer data from WooCommerce
* **Session Management**: Properly resets Chatwoot sessions on user logout
* **Customizable Widget**: Extensive options to customize the appearance and behavior of the chat widget
* **Debugging Tools**: Debug mode for easier troubleshooting

### Customer Data Shared (for logged-in users)

When a customer is logged in, the following data is securely shared with your support team:

* Customer name and email
* Customer ID
* Country
* Phone number (if available)
* Customer lifetime value statistics:
  * Registration date
  * Number of orders
  * Total amount spent

### Privacy and Security

* All data sharing requires proper configuration and is opt-in
* HMAC token verification ensures data integrity and user validation
* No data is shared for anonymous/guest users
* Complies with GDPR and privacy regulations when properly configured

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/chatwoot-identity-validation` directory, or install the plugin through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings → Chatwoot Identity Validation to configure the plugin

== Configuration ==

### Required Settings

* **Chatwoot Base URL**: The URL of your Chatwoot instance (e.g., https://app.chatwoot.com or your self-hosted instance)
* **Chatwoot Widget Token**: Found in your Chatwoot Inbox settings under "Widget Settings"

### Recommended Settings

* **HMAC Token**: For secure identity validation, enter the HMAC key from your Chatwoot settings
* **Widget Settings**: Customize the appearance and behavior of the chat widget according to your site's design

== Frequently Asked Questions ==

= Does this plugin require a Chatwoot account? =

Yes, you need a Chatwoot account. You can use the hosted version at [chatwoot.com](https://www.chatwoot.com) or self-host your own instance.

= Is this plugin compatible with the free version of Chatwoot? =

Yes, this plugin works with both the open-source/free version and paid plans of Chatwoot.

= Can I use this plugin without WooCommerce? =

Yes, the plugin will work without WooCommerce, but the customer data sharing features will be limited to basic WordPress user information.

= How do I get my HMAC token? =

1. In your Chatwoot admin panel, go to Settings → Inboxes
2. Select your inbox
3. Click on the "Widget Settings" tab
4. Scroll down to "Security Settings"
5. Enable "Identity Validation" and copy the displayed HMAC token

= Does this plugin slow down my website? =

No, the plugin loads Chatwoot asynchronously and does not block page loading. It has minimal impact on page performance.

== Screenshots ==

1. Admin settings page
2. Chatwoot widget on your WooCommerce store
3. Customer information displayed in the Chatwoot agent interface

== Changelog ==

= 1.0 =
* Initial release
* Support for HMAC identity validation
* WooCommerce customer data integration
* Customizable widget appearance
* Debug mode for easier troubleshooting

== Upgrade Notice ==

= 1.0 =
Initial release of the plugin.