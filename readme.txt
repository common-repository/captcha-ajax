=== Captcha Ajax===
Contributors: alessandro12
Donate link: https://www.paypal.me/a6419
Tags: captcha, ajax, security, login, post
Requires at least: 5.0 or higher
Tested up to: 6.6
Requires PHP: 7.2.24
Stable tag: 1.10.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Captcha, ajax and cache. For logins, for contact forms. Include Firewall and REST API.

== Description ==
Captcha works fine with website cache.
Adds Captcha Asynchronously anti-spam methods to WordPress. Include login form, registration form, lost passwordform and comments form. The asynchronously method allows captcha to work well when a page cache, server cache or plugin cache, is active.

== Demo ==
[View:]( https://captcha-ajax.eu )

== Features ==
- Captcha for login form
- Captcha for login form obtained with the function wp_login_form(). Login form embedded in a page.
- Captcha for registration form
- Captcha for lost password form.
- Captcha for comments form

- Captcha for Contact Form 7
- Captcha for WPForms
- Captcha for Forminators form.

- Select the letters type from the options - Capital letters, Small letters or Captial & Small letters.
- Select the captcha type from the options - Alphanumeric, Alphabets or numbers.
- Select the captcha image. Default image or Black and white or Multicolor or Icons ( 27 icons available from fontawesome.com ) or Arithmetics. See images in screenshots.

- Firewall. Limit rate of failed login attempts for each IP.

- REST API


Firewall details:
Limit failed login attempts. 
Temporary blocks an Internet address from making further attempts after a specified limit on failed retries is reached.
Option active for login form, login form embedded, registration form, lost password form. Select the feature in the dashboard.


REST API details:
The following address, reachable with a browser:
" https://your_site/wp-json/captcha-ajax/v1/transients_expired "
will cause the cleaning of expired transients. 
Performs this task no more than once every 2 hours, further requests will be ignored.

If your web site has a caching plugin installed or uses server-side caching, it is best to exclude the page from caching:
" https://your_site/wp-json/captcha-ajax/v1/transients_expired "


Captcha for Contact Form 7 details:
1. Install and activate CF7 and Captcha Ajax.
2. Go to Captcha Ajax settings. CF7 yes and save.
3. Create your contact form with CF7.
4. Edit your new CF7 contact form:
    Click on the line before [submit "Submit"]. This positions the cursor.
    Click on the Captcha Ajax Tag.
    Click on Insert Tag. This inserts the shortcode of Captcha Ajax.
    Click on Save.
5. Add the CF7 shortcode to the page, post or text widget and save.
6. Cache. Purge if it is active.
Done


Captcha for WPForms details:
1. Install and activate WPForms and Captcha Ajax.
2. Go to Captcha Ajax settings. WPF yes and save.
3. Create your contact form with WPForms.
4. Add the WPForms shortcode to the page, post or text widget and save.
5. Cache. Purge if it is active.
Done


Captcha for Forminator form details:
1. Install and activate Forminator form and Captcha Ajax.
2. Go to Captcha Ajax settings. Forminator yes and save.
3. Create your contact form with Forminator.
4. Add the Forminator shortcode to the page, post or text widget and save.
5. Cache. Purge if it is active.
Done


== Installation ==
- Upload the Captcha-Ajax plugin to your site and activate it.
- Admin page: Dashboard > Settings > Captcha-Ajax Settings.

== Support ==
Thanks for downloading and installing my plugin. You can show your appreciation and support future development by donating. https://www.paypal.me/a6419

== Screenshots ==
1. screenshot-1.png
2. screenshot-2.png
3. screenshot-3.png
4. screenshot-4.png
5. screenshot-5.png
6. screenshot-6.png
7. screenshot-7.png
8. screenshot-8.png
9. screenshot-9.png
10. screenshot-10.webp


== Changelog ==
= 1.10.0 =
Added Arithmetics Captcha Image

= 1.9.2 =
Small changes to plugin administration.

= 1.9.1 =
Fixes an issue that occurs if WordPress files are not placed in the root of the website.

= 1.9.0 =
Added support for Firewall

= 1.8.0 =
Added a REST API nemespace plus an endpoint that cleans up expired transients. 

= 1.7.7 = 
added delete Expired Transients in the admin page. 

= 1.7.5 =
Fix. Solves a css problem that occurs with some themes.

= 1.7.0 =
Added: Captcha Icons images. 
Added: new Captcha button. Regenerate Captcha. 
Emergency. Added: deactivation of this plugin via wp-config.php

= 1.6.0 =
Add captcha image multicolor

= 1.5.0 =
Add captcha image black and white.

= 1.4.0 = 
* Add captcha for Forminator form.

= 1.3.0 =
* Add captcha for WPForms

= 1.2.0 =
* Add captcha for Contact Form 7

= 1.1.0 =
* Add captcha for comments form

= 1.0.0 =
* Initial Release
