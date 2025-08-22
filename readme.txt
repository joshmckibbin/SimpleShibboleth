=== Simple Shibboleth ===
Contributors: srg-1, joshmckibbin
Tags: shibboleth, authentication, sso, login
Requires at least: 5.9
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.5.1
License: MIT

A modernized fork of SimpleShib for Shibboleth SSO authentication. Easy to install and configure, focusing solely on authentication.

== Description ==

**Simple Shibboleth** is a WordPress plugin to authenticate users with a Shibboleth Single Sign-On infrastructure. It is an updated fork of the [SimpleShib](https://wordpress.com/plugins/simpleshib) plugin. This plugin will not work if you do not have a Shibboleth IdP and SP already configured.

When a WordPress login request is received from a user, the Shibboleth session is validated. If the session does not exist, user is redirected to the IdP login page. Once authenticated at the IdP, the user is redirected back to WordPress and logged into their local WordPress account. If a local account does not exist, one can _optionally_ be created.

User data (login, name, and email) is updated in WordPress from the IdP data upon every login. Additionally, the user is restricted from manually changing those fields on their profile page.

On multisite instances of WordPress, **Simple Shibboleth** can only be network-activated.

The plugin settings include options for autoprovisioning, custom IdP attributes, password reset/change URLs, and session initiation/logout URLs.

**Simple Shibboleth** is developed on GitHub. Please submit bug reports and contributions on [the GitHub project page](https://github.com/joshmckibbin/SimpleShibboleth).

This plugin is not affiliated with the Shibboleth or Internet2 organizations.

== Installation ==

This plugin will not work if you do not have a Shibboleth IdP and SP already configured. The `shibd` daemon must be installed, configured, and running on the same server as Apache/WordPress. Additionally, Apache's `mod_shib` module must be installed and enabled. These steps vary based on your operating system and environment. Installation and configuration of the IdP and SP is beyond the scope of this plugin's documentation. Reference the [official Shibboleth documentation](https://wiki.shibboleth.net/confluence/display/SP3/Home).

1. Install the plugin to `wp-content/plugins/simple-shibboleth` via your normal plugin install method (download and extract ZIP, `wp plugin install`, etc).
2. Add the following to Apache's VirtualHost block and restart Apache. This will ensure the shibd daemon running on your server will handle `/Shibboleth.sso/` requests instead of WordPress.

	`<Location />
		AuthType shibboleth
		Require shibboleth
	</Location>
	RewriteEngine on
	RewriteCond %{REQUEST_URI} ^/Shibboleth.sso($|/)
	RewriteRule . - [END]`

3. Activate the **Simple Shibboleth** plugin in WordPress.
4. Browse to Settings > Simple Shibboleth and edit the configuration.

= WP-CLI can also be used to enable/disable the plugin's 'Enable SSO' configuration option =

If you have WP-CLI installed, you can enable Shibboleth SSO with the following command:
```bash
wp sshib enable
```
You can disable the plugin with:
```bash
wp sshib disable
```

== Frequently Asked Questions ==

= What is Shibboleth? =

From [Wikipedia](https://en.wikipedia.org/wiki/Shibboleth_(Internet2)):

> *"Shibboleth is a single sign-on (log-in) system for computer networks and the Internet. It allows people to sign in using just one identity to various systems run by federations of different organizations or institutions. The federations are often universities or public service organizations."*

= Can I test this without an IdP? =

Maybe. Check out [TestShib.org](https://www.testshib.org/). Note, you still need the SP/shibd configured on the server with Apache/WordPress.

= A shibboleth plugin already exists; why write another? =

"My attempts to use the other Shibboleth plugin failed for various technical reasons. It seemed to be unmaintained at the time. I ended up modifying the plugin heavily. I finally got to the point where I just wrote my own." - Steve Guglielmo (srg-1), original author of SimpleShib.

= The domain name is not correct after a redirect =

Add the following to Apache's config:

	`UseCanonicalName On`

= Can I automatically set user roles based on IdP data?  =

No. **Simple Shibboleth** handles authentication, not authorization. Authorization is managed within WordPress by network admins or site admins.

= What's this MIT license? =

**Simple Shibboleth** is released under the MIT license. The MIT license is short, simple, and very permissive. Basically, you can do whatever you want, provided the original copyright and license notice are included in any/all copies of the software. You may modify, distribute, sell, incorporate into proprietary software, use privately, and use commerically.

There is no warranty and the author or any contributors are not liable if something goes wrong.

See the `LICENSE` file for full details.

== Screenshots ==

1. The first half of the SimpleShib plugin settings within the WordPress admin menu.
2. The second half of the SimpleShib plugin settings within the WordPress admin menu.

== Changelog ==

= 1.5.1 =
* Bug fixes for user profile page.

= 1.5.0 =
* Require PHP 8.0.
* Require WordPress 5.9.
* Documentation updates.
* Switched from jQuery to vanilla JavaScript.
* Removed the `new user` button from the admin bar menu.
* Added plugin version constant.
* Added WP-CLI support for SSO enable option.
* Changed names of form fields on options page to single array.

= 1.4.0 =
* Moved javascript to a separate file loaded via `wp_enqueue_script()` instead of injecting it inline.
* Utilize the `admin_notices` action to display messages.
* Wrapped all html text output in translation escaping functions.
* Switched 'echo, then die' to wp_die() for better compatibility with WordPress.

= 1.3.0 =
* Forked and development resumed by Josh Mckibbin.
* Removed flags from the `filter_var()` function to support PHP 8.1.

= 1.2.2 =
* Compatibility with WordPress 5.4.
* Require PHP 7.2.
* Documentation updates.

= 1.2.1 =
* Add options for custom IdP attributes.
* Documentation updates.

= 1.2.0 =
* Move configuration into the database.
* Compatibility with WordPress 5.3.
* Fix a return_to URL bug that affected multisite.
* Documentation updates.

= 1.1.1 =
* Compatibility with WordPress 5.2.
* Improve compliance with WordPress coding standards.
* Minor documentation updates.

= 1.1.0 =
* Add a boolean setting for automatic account provisioning.
* Update example logout URL to return to the IdP's logout page.

= 1.0.3 =
* Compatibility with WordPress 5.1.
* Improve compliance with WordPress coding standards.
* Use wp_safe_redirect() when possible.
* Move PHP class into a separate file.
* Change install instructions from a must-use plugin to a network-activated plugin.

= 1.0.2 =
* Compatibility with WordPress 5.
* Improve compliance with WordPress coding standards.
* Minor documentation updates.

= 1.0.1 =
* Minor documentation and code changes.
* Add plugin banner to assets.

= 1.0.0 =
* Initial release.
