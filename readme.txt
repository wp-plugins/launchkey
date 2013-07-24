=== LaunchKey ===
Contributors: launchkey 
Donate link: https://launchkey.com/
Tags: LaunchKey, launch key, launch, key, oauth, security, login, sign in, log in, authentication, key, SSO, ACL, connect, cyber security, cyber, identity
Requires at least: 3.5
Tested up to: 3.5.2
Stable tag: 0.1.1
License: GPLv2 Copyright (c) 2013 LaunchKey, Inc.
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

[LaunchKey](https://launchkey.com) is evolving user authentication by eliminating passwords with multi-factor authentication on smartphones and tablets. LaunchKey's free App enables users on websites, applications and other systems to securely and privately authenticate without passwords. For implementers, LaunchKey provides a trustworthy alternative to password-based user authentication while reducing the liability passwords create.

This plugin enables a LaunchKey user to pair with a WordPress user for authentication. 

== Installation ==

= Plugin =

1. Upload or install the plugin from the repository.

2. Click the "Activate" link to the left of the LaunchKey description.


= LaunchKey Setup =

https://launchkey.com/docs/plugins/wordpress

1. Install LaunchKey on your personal device and create a user/pair and existing account.

2. Log into https://dashboard.launchkey.com and "Create new app" (https://dashboard.launchkey.com/my/newapp).

3. After you have created your new App in the dashboard, obtain your "App Key" and generate your "Secret Key".
*Keep these for future steps.

4. Check the OAuth section and enter the referring domain that will be asking for access.

5. Verify that the LaunchKey plugin is installed and enabled on your WordPress system with the instructions above.

6. Locate the "LaunchKey" settings section and enter your "App Key" and "Secret Key" from step 3.

7. Now logout of the admin section. "Login with LaunchKey" should be visible on the bottom of the login form.
Click through and authenticate with LaunchKey.

8. On the first success you will be asked to login with your username/password to create the initial user pairing.

9. After logging in with your username/password for the last time you are done.

Simply login with LaunchKey from this point forward!

== Frequently Asked Questions ==

= What does this cost? =

LaunchKey for your device and creating an App for your WordPress installation are both FREE.

= What Happens if I lose my Device? =

You can always unpair any device at any time at: https://launchkey.com/unpair

== Changelog ==

= 0.1.1 =
* Minor updates to readme.txt  

= 0.1.0 =
* Initial Release 
