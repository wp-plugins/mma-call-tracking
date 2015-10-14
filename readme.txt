=== Plugin Name ===
Contributors: MessageMetric
Donate link: http://www.messagemetric.com/
Tags: call tracking, conversion tracking, optimization, ppc, seo, adwords, analytics
Requires at least: 4.0.0
Tested up to: 4.3
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Call Tracking by Message Metric.

== Description ==

[Message Metric](http://www.messagemetric.com) call tracking empowers businesses to find and identify the
marketing campaigns that create phone calls. Our WordPress plugin is very
easy-to-install and allows you to dynamically swap one or more phone numbers
displayed on any page of your website based on referral sources. We can track
how visitors find your website and call your business.

For example, you can have one or more tracking numbers to do things like:

* Track all calls on 1 number.
* Track visitors from PPC ads.  (Direct integration w/ Google Adwords conversions)
* Track visitors from Organic Search.
* Track direct referral visitors from sites like Yelp, Foursquare, Directories, etc.

To get started you simply install the wordpress plugin, connect to your
Message Metric Call Tracking Account, choose the JavaScript option and the
number or numbers you want replaced with any rules and we’ll automatically
replace the phone number on your website with the appropriate tracking phone
number based on your rules.

* Learn more about Message Metric at: [http://www.messagemetric.com](http://www.messagemetric.com)

== Installation ==

1.	Install MMA [Call Tracking](http://wordpress.org/plugins/mma-call-tracking/) either via the
	WordPress.org plugin directory, or by uploading the files to your server.
2.	Activate MMA Call Tracking from the 'Plugins' menu in WordPress.
3.	Configure the plugin from the 'MMA Call Tracking > Settings' menu in WordPress:
	1.	Enter your Message Metric username and authentication key.
	2.	If you are using Google AdWords, enter your AdWords Customer ID and
		Conversion Type (e.g. "Phone Calls").
	3.	Enter any additional, non-Message Metric phone number you would like to
		 have appear on your web site.
	4.	Select either "Shortcode" or "JavaScript" for the Assistant Mode.
		Shortcode mode replaces phone numbers before the page is load but will not
		work with caching plugins.
	5.	Click the Save Settings button to save your settings.
4.	Configure your phone numbers from the 'MMA Call Tracking > Phone Numbers'
	page. Click the Save Changes button to save your configuration.

== Frequently Asked Questions ==

= Do I need to have a Message Metric account to use MMA Call Tracking? =

Yes, you must have a Message Metric account to use the MMA [Call Tracking Plugin](https://wordpress.org/plugins/mma-call-tracking/).

== Screenshots ==

1. Plugin Settings
2. Phone Number Configuration

== Changelog ==

= 2.2.1 =
* Add logging of query arguments, referrer, and user agent.

= 2.2.0 =
* Implement new, simplified call tracking javascript.

= 2.1.1 =
* Improve error handling.

= 2.1.0 =
* Add support for mma-noreplace class.

== Upgrade Notice ==

= 2.2.0 =
* Implement new, more simplified, more robust call tracking javascript code.

= 2.1.1 =
* Improve error handling.

= 2.1.0 =
* Add support for a mma-noreplace class that can be added to tags to prevent a phone number inside that tag
  from getting replaced.
