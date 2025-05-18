=== Tutor Registration for WooCommerce & LatePoint ===
Contributors: Your Name
Tags: woocommerce, latepoint, registration, tutor, student, user roles, custom fields
Requires at least: 5.0
Tested up to: 6.0
Requires PHP: 7.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrates user registration on WooCommerce with LatePoint plugin to create tutors and students with subject selection.

== Description ==

This plugin enhances the WooCommerce registration and account management process to seamlessly integrate with the LatePoint booking plugin. It allows users to register as either 'Students' or 'Tutors'. 

Tutor specific features:

*   Set an hourly rate.
*   Select multiple subjects (services from LatePoint) they can teach.
*   Optionally offer 'Urgent Help' (within the hour) at a specified urgent hourly rate.

Student specific features:

*   Standard WooCommerce registration.

Key functionalities:

*   Adds custom fields to the WooCommerce registration form and 'My Account' > 'Edit Account' page.
*   Validates these custom fields during registration and account updates.
*   Saves custom field data as user meta.
*   For users registering or updating their role to 'Tutor':
    *   Creates or updates a corresponding 'Agent' in LatePoint.
    *   Syncs their selected subjects as services for the LatePoint agent.
    *   Syncs their hourly rate and urgent hourly rate (if applicable) to LatePoint agent meta.
*   Handles cleanup by removing the LatePoint agent and associated data when a WordPress user is deleted.
*   Modifies the WooCommerce login form to use 'Email address' as the placeholder for the username field.
*   Disables the WooCommerce password strength meter on account pages for a smoother user experience.
*   Adds 'Dashboard' and 'Logout' links to the primary navigation menu (specifically styled for Divi theme compatibility, but adaptable).
    *   The 'Dashboard' link directs tutors to the LatePoint admin page and students to a '/student-dashboard/' page (you'll need to create this page).

This plugin requires both WooCommerce and LatePoint to be active.

== Installation ==

1.  Upload the `darsna-tutor-registration` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Ensure WooCommerce and LatePoint plugins are installed and activated.
4.  (Optional) Create a page with the slug `student-dashboard` for students to be redirected to from the menu link.

== Frequently Asked Questions ==

= What happens if WooCommerce or LatePoint is not active? =

The plugin will display an admin notice and deactivate itself to prevent errors.

= How are subjects (services) managed? =

Subjects are pulled directly from the services you have created in the LatePoint plugin. Tutors can select which of these services they offer.

= What is 'Urgent Help'? =

This is an optional service a tutor can offer. If selected, they can set a different hourly rate for urgent bookings. The plugin assumes a service with ID `1` in LatePoint is designated for 'Urgent Help'. You may need to adjust the `DARSNA_URGENT_HELP_SERVICE_ID` constant in the main plugin file if your 'Urgent Help' service ID in LatePoint is different.

== Screenshots ==

1. Registration form with custom fields.
2. Edit account page with custom fields.

== Changelog ==

= 1.0.1 =
* Refactored plugin into a class-based structure.
* Improved code organization and maintainability.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.1 =
This version introduces a new file structure. Please ensure all files are uploaded correctly.