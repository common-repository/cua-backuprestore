=== cua-backup-restore ===
Contributors: cayfer
Tags: multisite,backup,restore
Requires at least: 4.3
Tested up to: 4.4
License: GPL
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html

A Backup/Restore plugin for MultiSite sites and blogs. 

== Description ==

CUABR is a WordPress plugin developed for multi-site WordPress blog admins. 
With CUABR, individual blog admins of a multi-site network can backup and 
restore their own blogs; together with the db records and uploaded files;
and download the backup sets to their desktops. This plugin ONLY works on 
multi-site WordPress installations built on LAMP (Linux + Apache + MySQL + PHP) servers.

Network administrators can backup/restore any blog site within the network, i
as well as the whole network.

CUABR can also be used to clone blogs within a network.

<a href=http://cuabr.net target=_new>Plugin site</a>
== Installation ==
1. Logon to WordPress with network administrator credentials;
2. go to My Sites → Network Admin → Plugins and click "Add New",
3. Click "Upload Plugin" and upload the plugin's ZIP file,
4. "Network Activate" the CUABR plugin.
5. then go to the dashboard of any of the blogs ( My Sites → Site listed at the top → Dashboard),
6. "CUA Backup/Restore" should now be available in the dashboard menu,
7. Click "CUA Backup/Restore" to see the plugin's console,
8. Click "Show Settings Panel" (only the network administrator will see this link) and make 
   sure that "path to mysql command", "path to mysqldump command", "number of days to 
   keep backup sets" and "disk quota for backup files" are set properly. 
   The "Suggest" links will check the availability and determine the command paths for your server.
9. Click the Save button and hide the Settings Panel.
10. Installation is now complete.

== Frequently Asked Questions ==
How is CUABR different than WP Import/Export?
WordPress' Import tool requires a properly working site in order to import its media
files mentioned in the XML file (exported file). It is not always possible to restore 
a broken site using its exported XML file which was exported and saved when the site 
was in good shape.

Is this plugin a migration tool? Can I move blogs/sites across servers?
This plugin can migrate blogs from one multisite WP to another, provided that both 
WordPress are the same version and CUABR is installed on both of them. 
But; still; care must be taken while attemptying this.

Will this plugin work on a WordPress server installed on a MS-Windows host?
No! This plugin works only on Linux/UNIX servers. 

Can I use this plugin to backup/restore a single site WordPress blog/site?
No! The plugin works ONLY on multi-site WordPress installations.

What does it take to set up a multi-site WordPress server?
Actually not much! Why don't you have a look at http://codex.wordpress.org/Create_A_Network 
to see how easy it is to set up a multi-site WordPress Network. 
(You'll need root shell access to the server).

Will you develop a multi-lingual version?
No! 

== Screenshots ==

== Changelog ==
N/A

== Upgrade Notice ==
N/A
