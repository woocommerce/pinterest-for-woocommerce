=== Pinterest for WooCommerce ===
Contributors: automattic, pinterest, woocommerce
Tags: woocommerce, pinterest, advertise
Requires at least: 5.6
Tested up to: 5.9
Requires PHP: 7.3
Stable tag: 1.0.12
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Get your products in front of Pinterest users searching for ideas and things to buy. Connect your WooCommerce store to make your entire catalog browsable.

== Description ==

Pinterest gives people their next great idea. Part collection, part marketplace, it’s a one-stop shop for millions of pinners to source inspiration, new products and new possibilities. It’s like a visual search engine, guiding people to ideas, inspiration and products that are just right for them.

With the Pinterest for WooCommerce extension, you can put your products in front of Pinterest users who are already looking for ideas and things to buy. Connect your WooCommerce store to your *[Pinterest business account](https://business.pinterest.com/)* directly in the WooCommerce app. Your entire catalog will become browsable on Pinterest in just a few clicks.

= Open-minded and undecided =

People on Pinterest are eager for new ideas, which means they want to hear from you. In fact, 97% of top Pinterest searches are unbranded. Content from brands doesn’t interrupt on Pinterest—it inspires. Shopping features are built into both the organic Pinner experience, and our ad solutions.

We'll also automatically set up your Pinterest tag, and a shop tab on your Pinterest profile.

*[Learn more about Shopping on Pinterest](https://business.pinterest.com/en/shopping/)*

= Set up your foundation =

*Connect your account*

Install the extension and connect your account to quickly publish Product Pins, automatically update your product catalog every day, and track performance with the Pinterest tag.

*Catalogs*

Turn your entire product catalog into browsable product Pins, all at once.

*Pinterest tag*

Add the tag to your site to measure conversions and to optimize ads for shopping campaigns or retargeting.

Consider longer attribution windows to capture shoppers who take more time to convert.

*Build brand loyalty*

People on Pinterest are nearly 50% more likely to be open to new brands while shopping. And once they find a brand they like, they’re more loyal.

Become their new favorite with merchant solutions like the Shop Tab and the Verified Merchants Program. Shop Tab on profile: Consider this your always-on Pinterest shop. It’s automatically created when you upload your catalog so people can shop right from your profile.

*Verified Merchant Program*

People love to shop from brands they trust. That’s what the Verified Merchant Program is all about. It includes benefits like a “verified” badge on your profile and eligibility for enhanced distribution.

*More about Pinterest*

Pinterest is a visual discovery engine people use to find inspiration for their lives and make it easier to shop for home decor, fashion and style, electronics and more. 450 million people have saved more than 240 billion Pins across a range of interests, which others with similar tastes can discover through search and recommendations.

== Installation ==

= Minimum Requirements =

* WordPress 5.6 or greater
* WooCommerce 5.3 or greater
* PHP version 7.3 or greater (PHP 7.4 or greater is recommended)
* MySQL version 5.6 or greater

Visit the [WooCommerce server requirements documentation](https://docs.woocommerce.com/document/server-requirements/) for a detailed list of server requirements.

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of this plugin, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “Pinterest for WooCommerce” and click Search Plugins. Once you’ve found this plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading the plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Where can I report bugs or contribute to the project? =

Bugs should be reported in the [Pinterest for WooCommerce repository](https://github.com/woocommerce/pinterest-for-woocommerce/).

= This is awesome! Can I contribute? =

Yes you can! Join in on our [GitHub repository](https://github.com/woocommerce/pinterest-for-woocommerce/) :)

Release and roadmap notes available on the [WooCommerce Developers Blog](hhttps://developer.woocommerce.com/)

== Changelog ==

= 1.0.12 - 2022-05-05 =
* Dev - update trusted plugins in composer.json.
* Fix - Feed generation fails if there is no eligible product.
* Fix - Fix fatal error if `get_pinterest_code()` doesn't exists on Throwable object.

= 1.0.11 - 2022-04-12 =
* Add - Clear error when the merchant is connected to another e-commerce platform.
* Add - Mexico added to supported countries.
* Fix - Currency is now being sent on the product page visit event.
* Fix - Ensure add to cart tag data is consistent.
* Fix - Price mismatch when price includes taxes.
* Fix - Unit tests failing on WC 6.4.
* Tweak - Simplify tracking code.
* Tweak - Updated the Track event generation script to prevent future errors.
* Tweak - WC 6.3 compatibility.
* Tweak - WP 5.9 compatibility.

= 1.0.10 - 2022-03-31 =
* Update - Feed Refactor with Action Scheduler Framework. ( [#368](https://github.com/woocommerce/pinterest-for-woocommerce/pull/368) )

= 1.0.9 - 2022-03-29 =
* Add    - Plugin update framework. ( [#390](https://github.com/woocommerce/pinterest-for-woocommerce/pull/390 ) )
* Tweak  - Use website verification instead of domains. ( [#391](https://github.com/woocommerce/pinterest-for-woocommerce/pull/391) )
* Tweak  - Move deactivation hook to plugin file ( [#397](https://github.com/woocommerce/pinterest-for-woocommerce/pull/397) )
* Update - Add np:woocomerce param to partner data in the tag. ( [#404](https://github.com/woocommerce/pinterest-for-woocommerce/pull/404) )
* Fix    - Remove invalid XML characters from feed. ( [#409](https://github.com/woocommerce/pinterest-for-woocommerce/pull/409) )

= 1.0.8 - 2022-03-11 =
* Update - Shipping column format. ( [#370](https://github.com/woocommerce/pinterest-for-woocommerce/pull/370) )
* Fix    - Escape XML special chars in SKU for the XML MPN section. ( [#371](https://github.com/woocommerce/pinterest-for-woocommerce/pull/371) )
* Fix    - Clean account data if user Disconnect during the onboarding process with a personal account. ( [#381](https://github.com/woocommerce/pinterest-for-woocommerce/pull/381) )
* Fix    - Do not create merchant on get_feed_state. ( [#353](https://github.com/woocommerce/pinterest-for-woocommerce/pull/353) )
* Update - Disable enhanced match support when tracking is disabled. ( [#386](https://github.com/woocommerce/pinterest-for-woocommerce/pull/386) )
* Tweak  - Take full size images for the feed. ( [#383](https://github.com/woocommerce/pinterest-for-woocommerce/pull/383) )
* Update - Enable shipping column in the feed. ( [#388](https://github.com/woocommerce/pinterest-for-woocommerce/pull/388) )

= 1.0.7 - 2022-02-24 =
* Fix - Critical error on Jetpack sites.

= 1.0.6 - 2022-02-16 =
* Fix – Fix the changelog for the 1.0.5 release by adding omitted changes.

= 1.0.5 - 2022-02-15 =
* Fix - Strip HTML shortcodes from feed.
* Fix - Make the price format consistent in the feed.
* Fix - Exclude zero price items from product feed.
* Tweak - Force logging to be enabled when setup is not complete.
* Add – Shipping column for developers and testers.

= 1.0.4 - 2022-02-03 =
* Fix - Store merchant id during the account creation. ( [#343](https://github.com/woocommerce/pinterest-for-woocommerce/pull/343) )

= 1.0.3 - 2022-01-25 =
* Fix    - Allow proper setup for new merchants with no catalogs. ( [#339](https://github.com/woocommerce/pinterest-for-woocommerce/pull/339) )

= 1.0.2 - 2022-01-25 =
* Fix    - Update and improve feedstate. ( [#240](https://github.com/woocommerce/pinterest-for-woocommerce/pull/240) )
* Add    - Tooltips for the Publish Pins and Rich Pins options on the settings page. ( [#253](https://github.com/woocommerce/pinterest-for-woocommerce/pull/253) )
* Add    - Merchant guidelines link in the setup page. ( [#255](https://github.com/woocommerce/pinterest-for-woocommerce/pull/255) )
* Add    - Show a notice on the landing page if this extension does not support store country. ( [#256](https://github.com/woocommerce/pinterest-for-woocommerce/pull/256) )
* Update - Adjust image size for additional images. ( [#268](https://github.com/woocommerce/pinterest-for-woocommerce/pull/268) )
* Fix    - Error message for merchants with declined status. ( [#272](https://github.com/woocommerce/pinterest-for-woocommerce/pull/272) )
* Add    - Pinterest Ads Manager Call To Action UI. ( [#273](https://github.com/woocommerce/pinterest-for-woocommerce/pull/273) )
* Update - Tweak the UI of claim website. ( [#286](https://github.com/woocommerce/pinterest-for-woocommerce/pull/286) )
* Update - Remove unused parameters sent in create merchant request. ( [#294](https://github.com/woocommerce/pinterest-for-woocommerce/pull/294) )
* Add    - Adding Woo Tracker for Usage Tracking. ( [#301](https://github.com/woocommerce/pinterest-for-woocommerce/pull/301) )
* Add    - Implement Events Tracking. ( [#296](https://github.com/woocommerce/pinterest-for-woocommerce/pull/296) )
* Add    - Product Attributes. ( [#303](https://github.com/woocommerce/pinterest-for-woocommerce/pull/303) )
* Update - API to v4. ( [#305](https://github.com/woocommerce/pinterest-for-woocommerce/pull/305) )
* Update - Refactor AccountConnection component. ( [#312](https://github.com/woocommerce/pinterest-for-woocommerce/pull/312) )
* Add    - Product attribute for Google product category. ( [#317](https://github.com/woocommerce/pinterest-for-woocommerce/pull/317) )
* Fix    - Onboarding wizard steps ( 2 and 3 ) are not clickable. ( [#318](https://github.com/woocommerce/pinterest-for-woocommerce/pull/318) )
* Fix    - Fetch parent id for variable product during feed xml generation. ( [#320](https://github.com/woocommerce/pinterest-for-woocommerce/pull/320) )
* Fix    - Plugin is blocking some 3rd party scripts. ( [#324](https://github.com/woocommerce/pinterest-for-woocommerce/pull/324) )
* Fix    - Multiple catalog created on pinterest with no possibility to delete them. ( [#305](https://github.com/woocommerce/pinterest-for-woocommerce/pull/305) )
* Fix    - Feed registration status is incorrect when user has more than one feed profile. ( [#335](https://github.com/woocommerce/pinterest-for-woocommerce/pull/335) )

= 1.0.1 - 2021-11-16 =
* Fix - Add PHP, JS & CSS linting GH actions.
* Fix - Enable enhanced match by default .
* Fix - Fix error with WC Session when accessing REST API endpoints publicly.
* Fix - Fix npm vulnerabilities.
* Fix - Update, clean and make green CSS & JS linters.
* Fix - Use Task List API to detect if we should show Pinterest onboarding tasks.

= 1.0.0 - 2021-10-25 =
- Initial release
