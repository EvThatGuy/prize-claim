=== Prize Claim ===
Contributors: EvThatGuy
Tags: prize, claims, tournaments, payments
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Handle prize claim submissions and processing with direct deposit integration.

== Description ==
Prize Claim plugin manages the entire lifecycle of prize claims from submission to payment processing.

== Installation ==
1. Upload 'prize-claim' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure forms in Settings > Prize Claims

== Frequently Asked Questions ==
= What forms are required? =
The plugin uses two Gravity Forms: Form #51 for claims and Form #91 for direct deposit info.


== Changelog ==
= 1.0.0 =
* Initial release

## Available Shortcodes

The plugin provides two shortcodes for displaying prize claim functionality:
### Prize Claim Form
[prize_claim_form]
Displays the prize claim submission form. Users must be logged in and have verified W9 and direct deposit information to submit claims.

### Prize Claim Status
[prize_claim_status]
Shows a table of all prize claims for the current user, including claim numbers, tournament names, amounts, and current status.