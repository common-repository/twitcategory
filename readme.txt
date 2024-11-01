=== TwitCategory ===
Contributors: Gabriel Nagmay
Tags: tweet, twitter, category, integration
Requires at least: 2.0.2
Tested up to: 2.9.2
Stable tag: 0.1.9

== Description ==

Based on twitpress by Tom Purnell. Adds the ability to choose which category will result in updates to your twitter account.

TwitCategory sends a tweet notifying your followers that a new post has been published on your blog. It is very customizable, allowing you to format the tweets using static text and several variables: title, post, permalink, guid and category name.

New versions also allow for URL shortening via tinyurl, isgd and bitly!

== Installation ==

= From wordpress admin screen =

1. Choose 'Plugins > Add New'.
1. Search for 'twitcategory' and install! 

= From this page =

1. Click 'Download'
1. Upload twitcategory.php to your /wp-content/plugins directory.

= Remember to: =

1. Activate the plugin through the Plugins menu in Wordpress Admin (you may need to be using a wordpress administrator account for this action). Please note that TwitCategory will create a table in your wordpress database called 'twitcategory'. It will clobber any previous table with that name.
1. Fill in your twitter account information in the twitcategory configuration menu found in the Manage menu in Wordpress Admin.
1. Rate and give feedback on http://wordpress.org/extend/plugins/twitcategory/

== Upgrading ==

1. Click update from the WordPress plugin menu.

or

1. Click 'Download' on this page.
1. Deactivate the twitcategory plugin through the Plugins menu in Wordpress Admin.
1. Upload twitcategory.php to your /wp-content/plugins directory, overwriting the previous version.
1. Fill in your twitter account information (be sure to re-enter your password), using the twitcategory configuration menu found in Wordpress Admin.

== Frequently Asked Questions ==

= How is this different then Twitpress? =

1. Twitpress tweets all posts. In TwitCategory you can choose "All Posts" or a single category from which to tweet. Posts that are not in the specified category won't result in a tweet.
1. I changed the [permalink] variable to actually show that permalink as defined in your administrator settings (it used to show guid).
1. There are also a few new variables that you can use to modify your tweet: [guid] which shows the shorter guid link. [category] which displays the selected category. [post] which adds the post content to the remaining space.

= I installed twitcategory and clicked activate, but my blog entries aren't being twittered! =

Unfortunately twitcategory is not psychic. You need to configure your twitter username and password from the TwitCategory menu in the wordpress 'manage' menu

= What is that extra junk for bitly? =

Bitly requires a user name and API key. Go to http://bit.ly/account/register to set up an account. Your key can be found under 'account'.

== Screenshots ==

1. This is the admin screen.

== Changelog ==

= 0.1.9 =
Added functionality: Shorten with Bitly!

= 0.1.8 =
Added functionality: URL Shorten & Post

= 0.1.7 =
Stable version. Seems to work very well. Let me know if you find problems.

= 0.1.6 =
This version (and previous versions) were just used in testing. They weren't event submitted to http://wordpress.org/extend/plugins/

