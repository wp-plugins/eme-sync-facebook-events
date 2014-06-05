=== EME Sync Facebook Events ===
Contributors: liedekef
Tags: facebook, events, synchronize, calendar
Requires at least: 3.5
Tested up to: 3.9.1
Stable tag: 1.0.3

A simple plugin to sync Facebook events to the Events Made Easy plugin.

== Description ==

A simple plugin to sync Facebook events to the Events Made Easy plugin, based on the old Sync Facebook Events plugin.
Uses the Facebook PHP API, which requires at least PHP 5.4 to work.

Get The Events Made Easy plugin:
http://wordpress.org/extend/plugins/events-made-easy/

== Installation ==

1. Download the plugin archive and expand it
2. Upload the eme-sync-facebook-events folder to your /wp-content/plugins/ directory
3. Go to the plugins page and click 'Activate' for EME Sync FB Events
4. Navigate to the Settings section within Wordpress and enter your Facebook App ID, App Secret & Page names or IDs you want to import.
5. Ensure the Events Made Easy plugin is installed and configured - http://wordpress.org/extend/plugins/events-made-easy/
5. Press 'Update' to synchronize your current Facebook events for display within Events Made Easy.
6. Synchronization will continue to occur on the schedule you set. You can always update manually if/when needed.

== Frequently Asked Questions ==

Q: What is the Facebook App ID and App Secret, and why are they required?

A: The Facebook App ID and App Secret are required by Facebook to access data via the Facebook graph API. 
To signup for a developer account or learn more see - http://developers.facebook.com/docs/guides/canvas/

Q: How do I find the Facebook ID of the page for which I wish to synchronize events?

A: Goto the page you're interested in - ex. https://www.facebook.com/webtrends  
Copy the URL and replace 'www' with 'graph' - ex. https://graph.facebook.com/webtrends 
The ID is the first item in the resulting text. In this example it is "54905721286".
Of course, 'webtrends' itself is accepted as a value too (it will just add an extra call to facebook to get the page ID).

Q: Do my Facebook events get updated on a schedule?

A: Yes, You can choose the update interval and also update immediately when you press the 'Update' button from the Sync FB Events section within settings.

Q: Why do I get a blank screen when running an update?

A: Check your Facebook App ID, Facebook App Secret and Facebook Page IDs. One of them is probably incorrect.

== Upgrade Notice ==

Upgrade Notice

== Screenshots ==

1. EME Sync Facebook Events Configuration

== Changelog ==

= 1.0.4 =
* Feature: you can now also use the facebook uid, next to the API uid itself. So e.g. 'webtrends' and '54905721286' will result in the same
* Improvement: facebook cover pictures are now downloaded and uploaded into wp, so the power of the gallery is at our disposal now, and EME can use it as any other picture

= 1.0.3 =
* Feature: if wanted, use latitude and longitude to check for matching (existing) locations (next to the facebook id) to check if a location exists already
* Work around a bug in the facebook api where the cover picture isn't returned
* Bugfix: start/end time of imported events were wrong

= 1.0.2 =
* Feature: allow to skip already synced events and locations, so you can edit these and keep the changes

= 1.0.1 =
* Improvement: do nothing if not all settings have been completed
* Improvement: all strings are translate-ready now, and added eme_sfe.pot and language subdir

= 1.0.0 =
* Initial release (based on the old Sync Facebook Events plugin)
