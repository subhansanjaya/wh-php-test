===WH PHP Test===
Contributors: subhansanjaya
Author: subhansanjaya
Requires at least: 3.0
Tested up to: 4.2
Stable tag: 0.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
	
A simple autocomplete plugin.

== Installation ==	

**Installation Instruction & Configuration**  	

1.You can use the built-in installer. OR
Download the zip file and extract the contents. Upload the ‘wh-php-test’ folder to your plugins directory (wp-content/plugins/).

2.Activate the plugin through the 'Plugins' menu in WordPress. 

3. Once the plugin activated on the plugins page you should see a notice asking to copy data from CSV file. You can click on the “Copy data from CSV” button copy data into the database.

3.Log into Admin panel and go to Settings > WH PHP Test to change values.

**Configuration**
To display autocomplete, you can use any of the following methods.

**In a post/page:**
Simply insert the short-code below into any post/page to display the autocomplete:

e.g. If you want to display the autocomplete in page call location-search, please, create a page and type [whpt] in content area.

`[whpt]`

**Function in template files (via php):**
To insert the slider into your theme, add the following code to the appropriate theme file:

`<?php echo do_shortcode(‘[whpt]’); ?>`

==changelog==

**Version 0.0.1 **
Initial release.