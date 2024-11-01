=== back data ass up ===
Contributors: postpostmodern
Donate link: http://www.heifer.org/
Tags: db, database, backup
Requires at least: 2.9.2
Tested up to: 3.2
Stable tag: trunk

Database backup.

== Description ==
Warning! In developent, not ready for production use.  Should be considered alpha at best. 
Tested with Wordpress MU 2.9.2 - Wordpress 3.1.3 More testing to come.
Requires PHP 5, as it should.

== Installation ==
1. Place entire /back-data-ass-up/ directory to the /wp-content/plugins/ directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==
= 0.6 =
* Moved default save location out of plugin directory - was getting overwritten on plugin upgrade * 
* Added bulk actions in backup table and bulk delete action *

= 0.57=
* Fixed deprecated user capabilities *

= 0.5 =
* Email file on complete is working - first pass, bug in gz compression fixed

= 0.05 =
* Minor bug in download fixed, delete database backup from interface

= 0.04 =
* First options for cron, using external service.

= 0.01 =
* Initial public release. No documentation, NOT recommended for production use.

== Screenshots ==
1. I like big databases