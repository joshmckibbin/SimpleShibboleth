# Simple Shibboleth

Simple Shibboleth is a WordPress plugin to authenticate users with a Shibboleth Single Sign-On infrastructure.

Simple Shibboleth began as a fork of [SimpleShib](https://github.com/srguglielmo/SimpleShib) by [srguglielmo](https://github.com/srguglielmo)

## Installation

This plugin will not work if you do not have a Shibboleth IdP and SP already configured. The `shibd` daemon must be installed, configured, and running on the same server as Apache/WordPress. Additionally, Apache's `mod_shib` module must be installed and enabled. These steps vary based on your operating system and environment. Installation and configuration of the IdP and SP is beyond the scope of this plugin's documentation. Reference the [official Shibboleth documentation](https://wiki.shibboleth.net/confluence/display/SP3/Home).

After installing the plugin, add the following to Apache's VirtualHost block and restart Apache. This will ensure the shibd daemon running on your server will handle `/Shibboleth.sso/` requests instead of WordPress.

```apache
<Location />
	AuthType shibboleth
	Require shibboleth
</Location>
RewriteEngine on
RewriteCond %{REQUEST_URI} ^/Shibboleth.sso($|/)
RewriteRule . - [END]
```

See `readme.txt` for more info.

## Usage

After installing and configuring the plugin, users can authenticate using their Shibboleth credentials. The plugin will handle the login process and if provisioning is enabled, create a local WordPress account if one does not exist.

The plugin can also be enabled and disabled using WP-CLI commands, which is useful for debugging:

```bash
wp sshib enable
wp sshib disable
```

## Contributing

Please use GitHub issues for any questions or contributions. Contributions will be added under the [MIT license](https://choosealicense.com/licenses/mit/). By submitting a pull request, you agree to this licensing.

1. Fork the repository.
2. Commit and push your changes to your fork.
3. Open a new pull request into our master branch.
