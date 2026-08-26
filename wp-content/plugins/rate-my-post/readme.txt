=== Rate My Post - Star Rating Plugin by FeedbackWP ===
Contributors: collizo4sky, properfraction
Donate link: https://feedbackwp.com/pricing/
Tags: rating system, rate post, rate page, star rating, post rating
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 4.4.4
Requires PHP: 7.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add Star Rating to WordPress posts & pages, collect feedbacks from users and improve website SEO with Schema markup for Rich Snippets.

== Description ==

Rate My Post - Star Rating System by FeedbackWP allows you to easily add rating functionality to your WordPress website. Visitors can rate your posts/pages and send you private feedback after rating.

Functionality to add star rating and result widget to custom post types, use different rating icons, create custom rating widgets that work independently of posts and pages is available in the [Premium Version](https://feedbackwp.com/?utm_source=wprepo&utm_medium=link&utm_campaign=liteversion).

What sets Rate My Post apart from other WordPress rating plugins is its simplicity, performance and impact on engagement. It works with any page cache plugin and is probably the most customizable free WordPress rating plugin.

https://www.youtube.com/watch?v=DBbd3sXiT8U&rel=0

**Highlights**

* Add rating functionality to your website automatically or use shortcodes - no coding required!
* Option to show ratings visually before the content of each post (so-called results widget)
* Stats section where you can see how many votes each post/page received and what is its average rating.
* Analytics section with detailed information about each rating such as time, IP (optionally), username, title, time spent on page before rating etc.
* Easily change ratings in the admin panel
* Supports structured data for rich snippets according to the latest [Google guidelines](https://webmasters.googleblog.com/2019/09/making-review-rich-results-more-helpful.html)
* GDPR compliant
* Top rated posts widget
* Custom templates for complete customization
* Works with infinite scroll plugins and popups (implementation required)

> <strong>FeedbackWP Premium</strong><br />
> This is the lite version of [FeedbackWP Premium](https://feedbackwp.com/pricing/?utm_source=wprepo&utm_medium=link&utm_campaign=liteversion) rating plugin with structured data for rich snippets. <a href="https://feedbackwp.com/?utm_source=wprepo&utm_medium=link&utm_campaign=liteversion">Click here to purchase the best WordPress feedback, star rating and rate my post plugin.</a>

**Shortcodes**

[ratemypost] - embeds rating widget

[ratemypost-result] - embeds results widget

It is also possible to embed a rating of whichever post by passing the id of the post to the shortcode.

[ratemypost id="1"] - embeds rating widget for the post with the id of 1

[ratemypost-result id="1"] - embeds results widget for the post with the id of 1

**FeedbackWP Premium**

[FeedbackWP Premium](https://feedbackwp.com/?utm_source=wprepo&utm_medium=link&utm_campaign=liteversion) comes with advanced schema selector which allows you to select schema type directly in the post editor for each post and supports custom rating widgets. Read more about [FeedbackWP Premium](https://feedbackwp.com/?utm_source=wprepo&utm_medium=link&utm_campaign=liteversion).

Features to add rating and feedback result widgets to Custom Post Types (CPT), add rating to existing posts without votes, and support for a different icon other than the Star icon are available in [FeedbackWP Premium](https://feedbackwp.com/?utm_source=wprepo&utm_medium=link&utm_campaign=liteversion).

**Components**

The plugin includes five components; rating widget, results widget, social widget, feedback widget and top rated posts widget.

= Rating Widget Features: =

*Rating widget adds the basic rating functionality to your website*

1. Choose between different types of rating widget: Stars, Thumbs, Hearts, Smileys and Trophies

2. Add rating widget to any page/post with shortcode: [ratemypost]

3. Add rating widget to all posts or pages with one click in the settings

5. Exclude rating widget from certain pages and posts - such as About Us page etc.

6. All texts and colors can be easily changed in the settings

7. Option to add structured data for Rich Snippets to be displayed in search engines

8. Option to get email when a post is rated

9. Option to prevent accidental votes

10. Prevent double votes with cookies

11. Option to hide average rating and vote count

12. Option to show descriptive ratings while a user hovers over rating icons

13. Option to enable reCAPTCHA v3 protection

14. Option to show results (visual rating) on archive pages

15. Option to allow only logged in users to vote

16. Option to prevent double votes via IP addresses

= Results Widget Features: =

*Results widget is similar to the rating widget, but is not interactive - it only displays rating visually.*

1. Optional feature - you can enable it or disable it

2. Add results widget to any page/post with shortcode: [ratemypost-result]

3. Add results widget to all posts or pages with one click in the settings

= Feedback Widget Features: =

*Feedback widget enables visitors to leave you anonymous feedback*

1. Optional feature - you can enable it or disable it

2. After a negative rating (you define what is a negative rating in the settings), displays the feedback widget

3. Users who give you negative rating can help you improve your post

4. Feedback is not posted publicly - only you can see it

5. Option to get an email if somebody leaves you feedback

= Social Widget Features: =

*Social widget displays social follow or social share links after the rating has been submitted*

You can show social follow/share links after a positive rating (you define what is a positive rating in the settings).

= Top Rated Posts Widget: =

*Displays top rated posts on your website*

Display top-rated posts on your website with the "Top Rated Posts" widget. You can specify the number of posts, minimum average rating, and vote count required. Additionally, enable featured images and visual ratings.

[ratemypost-top-rated] - embeds the top-rated posts widget.

= Why use Rate My Post? =

1. Increase engagement

2. Get feedback and improve your content

3. Get more followers on social media

4. It's responsive, lightweight and simple to use

5. It's probably the most customizable free WordPress rating plugin

6. It uses AJAX and thus works super fast

7. It's compatible with caching plugins

8. It supports structured data for rich snippets

9. It works with multilingual websites

10. AMP compatibility

11. reCAPTCHA v3 protection

12. Migration tools - easily migrate from kk Star Ratings, YASR or WP-PostRatings

== Installation ==

**From the Dashboard (Recommended)**:
1. Navigate to Dashboard -> Plugins -> Add New
2. Search for Rate My Post
3. Click Install
4. Click Activate
5. Click Rate My Post -> Settings in the main menu and configure the plugin
6. Add shortcode [ratemypost] to your posts or embed rating widgets automatically in the Settings

**Manual Installation**:
1. Unzip downloaded archive and upload rate-my-post folder under your /wp-content/plugins/ directory
2. Navigate to Dashboard -> Plugins
3. Click Activate
4. Click Rate My Post -> Settings in the main menu and configure the plugin
5. Add shortcode [ratemypost] to your posts or embed rating widgets automatically in the Settings

== Frequently Asked Questions ==

= Support? =
For support use the support forum, but please do read the guidelines and [documentation](https://feedbackwp.com/docs/?utm_source=wprepo&utm_medium=link&utm_campaign=liteversion) before posting.
= Does it work with caching plugins? =
Rate My Post works with all caching plugins. If you are using caching plugin other than WP Super Cache, LiteSpeed Cache, WP Fastest Cache, WP Rocket or SG Optimizer, you should enable AJAX load results in the advanced settings.
= What can be rated? =
The plugin allows visitors to rate posts, pages and custom post types. It is not possible to rate archives (categories etc.) as the ratings are stored in the post meta. Nevertheless, custom rating widgets are coming soon.
= The rating widget is displayed multiple times =
This typically happens with various "page builder" themes. In such cases it's best to include the rating widget with the shortcode [ratemypost] instead of using the automatic option. If that's too much work, you can add it directly to the template of your theme. See the procedure in the [documentation](https://feedbackwp.com/docs/) under Troubleshooting -> The “Add rating widget to all posts” feature is not working.
= Can I have more than one rating widget on a single web page? =
Yes, it's possible to have multiple rating widgets on a single web page (posts, pages etc.). Nevertheless, this feature only works if ajax load results is disabled.
= Where do I find the documentation =
The documentation is available [here](https://feedbackwp.com/docs/?utm_source=wprepo&utm_medium=link&utm_campaign=liteversion).
= The vote count and average rating are not correct on page load =
Such issues typically appear due to caching. If you are encountering such issues enable Ajax load results in the advanced settings.
= The plugin stopped working after the update =
If you encounter problems with the plugin after the update, first clear the cache (page cache, minify cache, CDN cache such as CloudFlare etc.). Then open an incognito window and see if the problem has been solved. In case it hasn't don't hesitate to contact me via the support forum.
= I can't save the settings =
If you have trouble saving the settings, clear your browser cache. Such problems typically appear after the update because the browser is still serving old files from cache.
= Does it work with multilingual websites? =
Yes, the plugin is fully compatible with multilingual websites. If you are using the plugin on a multilingual website enable Multilingual website compatibility mode in the advanced settings and then translate strings through your plugin for translations.
= Do I have to translate the plugin if my website uses language other than English? =
Not necessarily because the plugin allows you to customize all frontend strings in the settings. However, backend strings can only be translated with translation files.
= Does this plugin show rich snippets? =
The plugin adds structured data for rich snippets, if you choose structured data type in the settings. Note that since September 2019 Google Shows aggregate rating rich snippets only for the following structured data types: Product, Book, Course, CreativeWorkSeason, CreativeWorkSeries, Episode, Game, LocalBusiness, MediaObject, Movie, MusicPlaylist, MusicRecording, Organization, Recipe, HowTo, SoftwareApplication and Event. The plugin supports all these structured data types except HowTo, SoftwareApplication and Event. SoftwareApplication and Event structured data types are available in the [Premium version](https://feedbackwp.com/?utm_source=wprepo&utm_medium=link&utm_campaign=liteversion). HowTO is at the moment not supported.
= Which structured data type should I choose? =
You should choose structured data type that fits your blog posts. If your blog posts are recipes than choose Recipe; if they are courses select Course etc. In case your blog posts don't fit any structured data type, then you are according to Google Guidelines not eligible for rich snippets. Learn more about this [here](https://webmasters.googleblog.com/2019/09/making-review-rich-results-more-helpful.html). If you are in doubt about which structured data type to choose (if any) ask for advice on [Google Help Community](https://support.google.com/websearch/community?hl=en). They will provide you with better answers than I can.
= Optional fields for rich snippets are missing =
Optional fields are not required for rich snippets to show. Hence, the plugin in some structured data types (for example product) skips the optional fields. The [Premium version](https://feedbackwp.com/?utm_source=wprepo&utm_medium=link&utm_campaign=liteversion) of the plugin supports optional fields for all structured data types and provides search engines with a more complete information about pages on your website.
= Rich snippets are not showing =
If rich snippets are not showing check that the structured data is valid [here](https://search.google.com/structured-data/testing-tool). If it's valid than search engines probably don't trust your website enough to show rich snippets. You can fix that by producing high-quality content.
= Where can I see the feedback? =
You can see the feedback for each post in the post editor at the bottom (meta box). There you can also manipulate ratings and see to which rating the feedback belongs. You can find more info about the rating in the analytics section.
= How to fix invalid WP token error? =
The invalid WP token error (refers to WP nonce) appears if a page is cached for more than 24 hours because WordPress nonces are valid for 24 hours. If you get invalid nonce error after rating a post, decrease page cache expiry. Most caching plugins have page cache expiry set to less than 24 hours. Therefore, this problem typically occurs on websites that use multiple page caching solutions. If that's the case on your website try disabling plugins until the error disappears, so you figure out what is causing the issue. You can read more about how to fix this problem [here](https://feedbackwp.com/docs/#nonce-info).

== Screenshots ==

1. Ratings in posts/pages
2. After vote, if feedback is enabled
3. After vote, if social is enabled
4. Plugin Settings
5. Plugin Customization
6. Plugin Stats
7. Manipulate Votes

== Changelog ==

= 4.4.4 =
* Switched to array data type for schema representation.

= 4.4.3 =
* Fixed migration where kk star CPT reviews did not import.

= 4.4.2 =
* Reverted accessibility improvements as many had styling issues with it.
* Added User ID support in email body filter.

= 4.4.1 =
* Added import/export of settings.
* Improved Accessibility.
* Set default structured-data-type to CreativeWorkSeries.

= 4.4.0 =
* Added button to export stats and analytics.

= 4.3.2 =
* Fixed bug with user tracking after rating not working.

= 4.3.1 =
* Added tracking of user on email notification after rating.
* Added tracking of user on email notification after feedback.
* Fixed bug where nonce verification were failing.

= 4.3.0 =
* Added blocks for rating, result and top-rated posts.
* Added rmp_is_not_votable filter.
* Fixed issue where structured datatype is missing after migration.

See the [changelog file](https://plugins.svn.wordpress.org/rate-my-post/trunk/changelog.txt) for full change log information.
