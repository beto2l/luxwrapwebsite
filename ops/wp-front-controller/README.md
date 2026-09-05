# WordPress front controller

These files route `luxwrapstudio.com` through the OPIN X WordPress Multisite while preserving the existing static assets, contact handler and legacy admin during the first LuxWrap Studio migration.

The hosted domain and WordPress installation must remain sibling directories under the hosting account. Install only after:

1. the Multisite contains a site whose domain is `luxwrapstudio.com`;
2. LuxWrap Studio is active and the LW Site is registered;
3. a published release exists below `wp-content/luxwrap-sites/{blog_id}/releases/{version}`;
4. the existing `.htaccess` and `index.html` have been backed up.
