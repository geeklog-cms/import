## Import Plugin for glFusion

### Overview

This plugin allows you to import / migrate data from an old geeklog site into glFusion. Import will migrate the following items:

 * Users
 * Groups (Only core groups such as Root, Story Admin, etc.)
 * Topics
 * Stories
 * Comments
 * Blocks
 * Calendar Events (both master and personal)
 * Links
 * Polls
 * Static Pages
 * Forum
 * Media Gallery

This plugin is designed to be run on a fresh installation of glFusion. The importer will REMOVE ALL EXISTING CONTENT, USERS and GROUPS prior to importing the geeklog data.

Import will work with any version of geeklog and plugins.

### What Doesn't Migrate

Import does not pull 100% of everything from geeklog. The following items will not migrate:

 * Site Configuration
 * Media Gallery Configuration
 * User Passwords - users must use the Forgot Password feature to reset their password after migration.
 * Groups - Any user created group or groups for plugins other than those whose data is migrated.
 * Hierarchical topics are flattened during migration - no parent / child relationship is migrated.
 * Stories will only migrate the primary topic, any additional topic assignments from geeklog will not migrate.
 * Content Syndications
 * FileMgmt or Downloads Plugin data

### How to Migrate

 1. Install glFusion - ensure you use the same Character Set and Database Collation as the old geeklog site.
 2. Configuration glFusion appropriately
 3. Install the Import Plugin
 4. Run the import plugin
 5. Copy the following data directories and all their content from the old geeklog site:
    * public_html/images/
    * public_html/mediagallery/mediaobjects/orig/
    * public_html/mediagallery/mediaobjects/disp/
    * Do not copy the Media Gallery thumbnails (geeklog version of Media Gallery creates thumbnails that are not compatible with glFusion)
 6. Verify the new glFusion site is working correctly
 7. If you migrated Media Gallery - Navigate to the Media Gallery Administration page - Choose Rebuild All Thumbnails from the Batch Options menu
 8. If you migrated the Forum Plugin - Navigate to the Forum Administration Page and select ReSync Category Forums for all categories. geeklog's Forum plugin does not always correctly maintain the parent / child relationship of topic post.

#### Notes

1. It is best to install glFusion on a clean set of directories. Do not copy the glFusion files over your existing geeklog files. You might consider renaming the existing directories that hold the geeklog site and create new directories to hold the glFusion files. Copy over an existing geeklog site has the following risks:
    * You cannot easily go back to your old geeklog site if something goes wrong or your decide you prefer the geeklog environment over glFusion.
    * It will leave several orphaned files that could pose a potential security risk.
2. You must create a new database for your glFusion site. Do not try to use the old geeklog database.
3. Verify that your Character Set configuration on the new glFusion site matches what you had on the old geeklog site.  See **Character Sets** below for more information.
4. The only user that will remain on the new glFusion site after migration is the **Admin** user. Ensure you can login as the Admin user and that it has full Root access to your site. You should run the Import plugin while logged in with the Admin account

### Potential Problems to Lookout For...

#### Character Sets

You **must** use the same character set for glFusion that you used for geeklog. You must also use the same database character set and collation for your glFusion database as you did for geeklog.

geeklog does not validate that the character set selected for geeklog matches the character set used by the database. If you have a geeklog site that thinks it is using UTF-8 (See the siteconfig.php file from geeklog to see what it is configured to use) but the MySQL database is configured to use latin1 and latin1_swedish_ci collation, there is a potential that some ASCII characters will become 'munged'.

For example, a name such as Pøbel may display correctly on your geeklog site, but after conversion it may look like PÃ¸bel.

glFusion will not allow an installation with mis-matched character sets, so it may be impossible to setup a glFusion site that mirrors an incorrect geeklog setup.

The primary risk is with foreign language sites and any character in the ASCII character set above 128 - for example ñ, è, ö and other accent characters.

Possible solutions are to convert the old geeklog sites database tables to UTF-8 prior to migrating. There are some really old instructions on the geeklog support site. Your best bet is to Google how to convert latin1 to UTF-8 for MySQL.

### License

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version.
