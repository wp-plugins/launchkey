=== LaunchKey ===
Contributors: launchkey 
Donate link: https://launchkey.com/
Tags: LaunchKey, launch key, launch, key, oauth, security, login, sign in, log in, authentication, key, SSO, ACL, connect, cyber security, cyber, identity, two-factor, multi-factor, two factor, multi factor, 2fa, mfa, tfa
Requires at least: 3.5
Tested up to: 3.8
Stable tag: 0.3.2
License: GPLv2 Copyright (c) 2013 LaunchKey, Inc.
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

This plugin integrates [LaunchKey](https://launchkey.com) so WordPress users have the ability to log in without the need or liability of passwords using their paired smartphone or tablet.

= What is LaunchKey? =

[LaunchKey](https://launchkey.com) is anonymous multi-factor user authentication without passwords for web and native apps. Instead of traditional password-based authentication, LaunchKey pushes authentication requests to a user’s paired device whereby a user can block or authorize the request with a slide of their finger.

Optional authentication factors like an in-app combo or PIN lock in addition to geofencing (the ability to restrict authentication within a specified geographical boundary) give a user true out-of-band multi-factor authentication.

With a RESTful [API](https://launchkey.com/docs/), LaunchKey’s [authentication flow](https://launchkey.com/docs/api/authentication-flow/) can be integrated in almost any setting. LaunchKey also supports a variety of SDKs in languages like [Python](https://github.com/LaunchKey/launchkey-python), [Ruby](https://github.com/LaunchKey/launchkey-ruby) and [PHP](https://github.com/LaunchKey/launchkey-php) with Objective-C and Java SDKs for [iOS](https://launchkey.com/docs/ios-sdk) and [Android](https://launchkey.com/docs/android-sdk) native apps. For quicker and easier web integrations, LaunchKey offers a client-side [JavaScript](https://launchkey.com/docs/oauth/javascript) implementation for OAuth along with plugins for popular systems like WordPress and Drupal.

== Installation ==

Full documentation: https://launchkey.com/docs/plugins/wordpress

1. Download LaunchKey from the [Apple App Store](https://itunes.apple.com/us/app/id609372788?mt=8) or [Google Play](https://play.google.com/store/apps/details?id=com.launchkey) and create a new account or pair your existing LaunchKey account.

2. Log into your online [dashboard](https://dashboard.launchkey.com) and create a new developer app by clicking [New app](https://dashboard.launchkey.com/my/newapp) located in the left side navigation.

3. After you've created your new app, copy the App Key and Secret Key (click the "Generate new key" button) from the App Details page and *keep these* for future steps.

4. While still on the App Details page, check the checkbox next to "OAuth" and enter the domain you've installed this plugin on.

5. Download, install and activate the LaunchKey WordPress plugin from the WordPress plugin repository.

6. Navigate to the LaunchKey settings section (WP Admin > Settings > LaunchKey) and enter the App Key and Secret Key you saved from step 3.

7. Finally, navigate to your Profile Options page by clicking on your username in the top right corner of the WP admin page and click the "Pair" link under the "LaunchKey Options" section. Next, enter your LaunchKey username and authorize the request on your paired device. When you're redirected back to the WP Profile Options page, you're done!

You will now be able to log in and out of WordPress using LaunchKey.

== Frequently Asked Questions ==

= What does this cost? =

Nothing, it's free!

= What happens to my password? =

By default, your password will still remain after you pair your LaunchKey account, but you can remove your password by clicking the "Remove WP password" link under "LaunchKey Options" within your Profile Options page in WP Admin.

= What happens if I lose my device? =

Remotely unpair your device at anytime by visiting: https://launchkey.com/unpair

== Changelog ==

= 0.3.2 =
* Added shortcode (Thanks to user jaketblank!)
* Additional Output Sanitization

= 0.3.1 =
* Refresh Token support for 30 days instead of 7. Note: Default WordPress Sessions last 48 Hours.
* Updated FAQ
* WordPress 3.8 support tested and verified.

= 0.3.0 =
* 3.7 & 3.7.1 support tested and verified.
* Enhance OAuth Refresh Token support enabling longer sessions.

= 0.2.5 =
* Secure UNINSTALL added, Deactivation does not do a secure wipe and retains settings and user pairings.

= 0.2.4 =
* Added nonce to remove password and unpair links inside Profile.

= 0.2.3 =
* Verified 3.6 compatibility.

= 0.2.2 =
* Fix for issue 32bit servers had with large App Keys. 

= 0.2.1 =
* readme.txt updates. Added screenshots, FAQ and updated content.

= 0.2.0 =
* Pair/Unpair accounts within the User Profile. Allow a User to remove their password and enable LaunchKey only login.

= 0.1.3 =
* Fixed Header Issue some installations were reporting. No new features at this time.

= 0.1.2 =
* Updates based on initial user feedback.

= 0.1.1 =
* Minor updates to readme.txt  

= 0.1.0 =
* Initial Release

== Screenshots ==

1. 'Log in with LaunchKey' button added to WP-login
2. WP Admin > Settings > LaunchKey
3. WP Admin > Profile > LaunchKey Options
4. Showing successful pair with LaunchKey account
