=== LaunchKey ===
Contributors: launchkey 
Donate link: https://launchkey.com/
Tags: LaunchKey, launch key, launch, key, oauth, security, login, sign in, log in, authentication, key, SSO, ACL, connect, cyber security, cyber, identity, two-factor, multi-factor, two factor, multi factor, 2fa, mfa, tfa, biometry, biometric, face scan, facial scan, selfie, fingerprint, finger scan
Requires at least: 3.5
Tested up to: 4.2.3
Stable tag: 1.0.2
License: GPLv2 Copyright (c) 2015 LaunchKey, Inc.
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Upgrade Notice ==

This upgrade provides the native implementation of the LaunchKey service.  The native implementation is more secure regarding secret data.  After you upgrade, your OAuth based configuration will still work as before but you will get deprecation notices as the OAuth implementation will not be supported in a future release.

== Description ==

Stop using insecure passwords! With [LaunchKey](https://launchkey.com), you can securely log in to WordPress using your mobile phone or tablet.

= What is LaunchKey? =

[LaunchKey](https://launchkey.com) is the mobile authentication platform for the post-password era. With LaunchKey, an individual’s unique mobile phone or tablet is transformed in to a smart key capable of authenticating its owner to any online or offline application, including WordPress!

= How does LaunchKey work? =

Instead of logging in with passwords, LaunchKey forwards a login request to your paired mobile device where you can authorize the request through the free LaunchKey mobile app (available on iOS, Android, and Windows Phone). 

= How does LaunchKey provide more security? =

Not only does LaunchKey eliminate the liability of utilizing passwords in the first place, it provides true multi-factor authentication through a variety of auth factors including biometric face scan and fingerprint scan, geofencing, in-app combination and PIN locks, and more! Furthermore, by shifting the layer of authentication from your WordPress site to your mobile device, you eliminate many common attack vectors prevalent in password-based authentication.

= How do I know LaunchKey is secure? =

At LaunchKey, security and privacy are paramount. As such, we architected LaunchKey to be an anonymous service. We don’t collect personally identifying information on our users, and any personal information used to verify authentication factors (e.g. biometric data, geographic coordinates, etc.) are stored securely on your device, not on LaunchKey servers. As a company, we're not even capable of authenticating or logging in on behalf of our users. Additionally, the entire LaunchKey platform has been independently audited for security by Veracode, Praetorian Labs, and our active community of “white hat” security researchers.

= What if I lose my phone? = 

A paired device can be remotely unpaired through this online [form](https://launchkey.com/unpair). If you have another paired device with the same LaunchKey account, you may also unpair your lost device through the control panel of the device you still have possession of.

= Where can I find out more? =

End users may read our detailed mobile app guide while developers can view our extensive online documentation.


== Installation ==

Full documentation: https://docs.launchkey.com/developer/cms/word-press/

= Quick Start =

1. Install and activate the LaunchKey WordPress Plugin

2. Start the configuration wizard at one of these locations:

    * Click the "Wizard" link in the LaunchKey actions menu of the Plugins List
    * Click the "Configure LaunchKey" button at the top on any Admin page
    * Go to the "LaunchKey" settings page

3. Complete the steps in the wizard

Once all of et steps in the wizard are completed, you are ready to use the LaunchKey WordPress plugin.

== Frequently Asked Questions ==

= What does this cost? =

Nothing, it's free!

= What happens to my password? =

By default, your password will still remain after you pair your LaunchKey account, but you can remove your password by clicking the "Remove WP password" link under "LaunchKey Options" within your Profile Options page in WP Admin.

= What happens if I lose my device? =

Remotely unpair your device at anytime by visiting: https://launchkey.com/unpair

== Changelog ==
= 1.0.2 =
* Version release fix

= 1.0.1 =
* Cosmetic changes to configuration wizard

= 1.0.0 =
* Tested up to 4.2.2
* Split up plugin file and code
* Moved SSL Verify from constant to option
* Encrypt secret data in plug-in options
* Stopped displaying secret data in settings.  Now shows hash value.
* Add native (non-OAuth) authentication
* Add white label functionality
* Add reminders to configure plugin
* Add configuration wizard
* Update User Profile options section for better readability
* Add "Paired" column to users list

= 0.4.3 =
* Add icon to assets
* Tested up to 4.0 

= 0.4.2 =
* Update assets and readme
* Confirm support up to and including 3.9.1

= 0.4.1 =
* Our first user submitted language has been added: Chinese (WPLANG: zh_CN). Thanks @DeamworkTec! Please contact us if you would like to help translate a new language or update an existing one. 

= 0.4.0 =
* Internationalization and Localization support.
* Shortcode styling enhancements 

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

1. Passwordless WP-login screen
2. Session Expire - Full integration with WordPress heartbeat enables remote session expiration with your Mobile Authenticator
3. WP Admin > Settings > LaunchKey - Not configured shows setup wizard
4. WP Admin > Settings > LaunchKey - Configuration Wizard -  Takes you step by step through the entire process
5. WP Admin > Settings > LaunchKey - Configuration Wizard - Allows you to set up the LaunchKey Mobile Authenticator or use your own authenticator with a LaunchKey White Label implementation
6. WP Admin > Settings > LaunchKey - Configuration Wizard - Walks you through verifying your configuration settings once completed
7. WP Admin > Settings > LaunchKey - Configuration Wizard - Visual verification that everything is working
8. WP Admin > Settings > LaunchKey - Configured
9. WP Admin > Users - Users list shows LaunchKey paired status of user accounts
10. WP Admin > Profile > LaunchKey Options - Unpaired
11. WP Admin > Profile > LaunchKey Options - Paired with Password
12. WP Admin > Profile > LaunchKey Options - Paired without Password
