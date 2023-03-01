# Pinterest for WooCommerce Test  Documentation

## General Testing

Prerequisites: 
Configure WooCommerce with products, and a valid payment processor (dev Stripe, or cash on delivery option in WC Settings > Payments) so that you can complete checkout.
Business Pinterest Account already verified on Pinterest

## 1.0 Onboarding flow
- After installing the Pinterest plugin 
  - Install the plugin but keep it deactivated (first time)- check front-end/back-end/logs has no fatal error.
  - Activate plugin for the first time - check front-end/back-end/logs has no fatal error
 - Start the onboarding flow by going to
  - Marketing > Pinterest
  - WooCommerce > Home > Things to do next
  - For new WooCommerce navigation. (need to install “WooCommerce Admin”. Then go to “WooCommerce > Settings > Advanced Tab > Features” and check the “Navigation” option.)
  - WooCommerce > Extensions > Pinterest
And Click on “Get Started”
Steps: 
- In Step A you can Connect your existing Pinterest Business Account or Create a new one. Some special cases:
  - Personal Pinterest account: should guide you to Create a Business Account or Convert your Personal Account to a Business account.
  - Personal Account linked with the Business account: should connect to the Business account linked to the Personal account.
- On Step B “Verify your domain to claim your website” the domain should be verified.
  - If Domain is already claimed by the user, just needs to continue.
  - If Domain is not claimed by the user should need to click on “Verify Domain”
  - If Domain is claimed by other users, an error message should be displayed, and the user can’t go forward 
  - Pinterest will support Domain with sub_path (1.0.9 or later version) (Example: https://<url>/test )
- In Step C “Track conversions with the Pinterest tag”. Users should select or create the advertiser.
  - If a Merchant does not have an Advertiser, the same flow should guide you through creating one by accepting the terms and conditions.
  - If a Merchant has 1 Advertiser, just need to click on “Complete Setup”
  - If a Merchant has more than one Advertiser, they should select one and “Complete Setup”
- After Completing Setup should redirect you to the “Settings” page of the Pinterest plugin.
  
## 2.0 Product page (WooCommerce products)
The Pinterest plugin will add 2 fields to the products page for simple products.

  ![imagen](https://user-images.githubusercontent.com/37819277/210645439-592d4d45-9c2b-474d-b158-d51b744413f7.png)

For variable products, the Pinterest tab will be added to the main product (parent) containing the Google category.
Product conditions will be added to each product variation.
  
  ![2023-01-04_17-37-04](https://user-images.githubusercontent.com/37819277/210645505-1b5ba995-7460-465e-a7bd-7ee584f1ae53.png)
  ![imagen](https://user-images.githubusercontent.com/37819277/210646029-8a894257-50dc-4fd8-b59f-95df1182a6ee.png)


## 3.0 Settings page
Options: 
- Product Sync > Enable Product Sync - Enabled by default
- Tracking > Track conversions - Enabled by default
- Tracking > Enhanced Match support - Enabled by default (Field will be disabled if “Tracking conversions” is disabled)
- Rich Pins > Add Rich Pins for Products - Enabled by default
- Rich Pins > Add Rich Pins for Posts - Enabled by default
- Save to Pinterest > Save to Pinterest - Enabled by default
- Debug Logging > Enable Debug Logging
- Debug log should be added on WooCommerce > Status > Logs with the name:
 - pinterest-for-woocommerce-YYYY-MM-DD-.....log
 - pinterest-for-woocommerce-product-sync-YYYY-MM-DD-.....log
- Plugin Data > Erase Plugin Data 
  If the User wants to uninstall the plugin and this option is Enabled, all data related to the plugin will be deleted.
## 4.0 Connection page
- Should show the Pinterest user_id that is connected to the Pinterest plugin
- The merchant should be able to Disconnect the account by clicking on “Disconnect”
 - It will delete all feeds on the Pinterest account
- Should show the domain that is verified
- Should show the Advertiser and Tracking tag that was selected on the onboarding flow. If a Merchant has more than one, it can be updated.
 - If Merchant has more than one advertiser it should be possible to change it
## 5.0 Products Catalog page
- Should be displayed by default when the user navigates to
 - Marketing > Pinterest
 - WooCommerce > Extensions > Pinterest (New Navigation)
- Should show the status of the Products synced with the Pinterest Business account. Pinterest feed/sync needs to be approved by Pinterest.
- XML feed should be created on /wp-content/uploads/pinterest-for-woocommerce-xxxxxx.xml
- Pinterest feed should be updated every 10 minutes (hook pinterest-for-woocommerce-handle-sync on Scheduled Actions can be run manually) 
- If sync is approved, it should be synched every 24hs. (On the Pinterest Business account board: Ads > Catalogs should show the status)
- Product sync can be disabled on the Settings page (Product Sync > Enable Product Sync option)
- Feed will be deleted from the site (not from the Pinterest account) when:
 - Disable option on the Settings page
 - Deactivate plugin
- Feeds will be deleted from the Pinterest account when the user disconnects from the account on the Connection tab.

### Example of an item:
  ```
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<item>
<g:id>36</g:id>
<title>
<![CDATA[ Hoodie ]]>
</title>
<description>
<![CDATA[ Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo. ]]>
</description>
<g:product_type>Hoodies</g:product_type>
<link>
<![CDATA[ https://<<URLsite>>/product/hoodie/ ]]>
</link>
<g:image_link>
<![CDATA[ https://<<URLsite>>/wp-content/uploads/2021/11/hoodie.jpg ]]>
</g:image_link>
<g:availability>in stock</g:availability>
<g:price>45USD</g:price>
<sale_price>42USD</sale_price>
<g:mpn/>
</item>
</channel>
</rss>
```

#### Note: 
- The description field will be filled with  “Short description” if the description is not present. Variable products need to have the Description of each variable so they have it on the feed.
- List of products that should not be present on the feed (XML)
 - Grouped Products
 - Hidden (1.0.9 or later) /private/draft products 
 - Products on trash
 - Out-of-stock products will be excluded if Woo settings is checked. https://<url>/wp-admin/admin.php?page=wc-settings&tab=products&section=inventory
  
  ![imagen](https://user-images.githubusercontent.com/37819277/210650438-6ad289e2-9e5c-4953-94fa-d2d46ad7e32e.png)

 - Parent products of the variable product (only should be present the variables)  (1.0.9 or later)
 - Free or 0 price products
 - Subscription products
### Google category and Condition fields
Pinterest plugin will add to fields on the Product page edition. These fields are not required by Pinterest but if they are not filled they will show a Warning message on Catalog Tab (Alert message)
 ![imagen](https://user-images.githubusercontent.com/37819277/210645439-592d4d45-9c2b-474d-b158-d51b744413f7.png)
  
## 6.0 Tracking Conversion
Should track User <> Site interaction with the Pinterest Business Account (on Pinterest Business board can be checked on “Ads > Conversions”)

 - Tracking conversion can be disabled on the Settings page. (Enabled by default)

**Examples**:
- **Page Visit**: Should send the event every time the user visits a page
```
<script>pintrk( 'track', 'pagevisit', );</script>
```
- **View Category**: This should send the event every time a user visits a category page.
```
<script>pintrk( 'track', 'ViewCategory' , {"product_category":16,"category_name":"Accessories"},); </script>
```
- **Search**: This should send the event every time a user searches something on the page. 
```
<script>pintrk('track', 'search', {search_query: 'hoodie'},);</script>
```

- **Add to Cart**: Event should be sent every time user adds the product to the cart from Shop/Category/Product page
```
<script>pintrk('track', 'AddToCart', {product_id: 10,order_quantity: 1,});</script>
```
- **Checkout**: Event should be sent every time User places an order. 
```
<script>pintrk( 'track', 'checkout' , {"order_id":45,"value":"220.00","order_quantity":4,"currency":"USD","line_items":[{"product_id":3,"product_name":"Belt","product_price":"55","product_quantity":3,"product_category":["Accessories"]},{"product_id":4,"product_name":"Hoodie with Logo","product_price":"45","product_quantity":1,"product_category":["Hoodies"]}]});</script>
```
## 7.0 Enhanced Match support
Should add to the Tracking Conversion event a hashed email so can be matched on Pinterest
  ```
pintrk('load', '2613101893693', { em: '8302f4115fded8c3885dbc5cc115fe90' });
  ```

- Enhanced Match can be enabled/disabled on the Settings page.

- Enhanced Match should be sent when: 
 - The user is logged in to the site
 - Cookies saved on the browser After placing an order (Guests users)
## 8.0 Rich Pins
Merchants can expose [OpenGraph](https://ogp.me/) metadata for products and posts to enable Rich Pins.
- Metadata passes validation in [Pinterest Rich Pins Validator](https://developers.pinterest.com/tools/url-debugger/).
- Merchants can disable Rich Pins for products and/or pages. (Enabled by Default)

**Example for the product:**
  ```
<meta property="og:url" content="https://<<URLsite>>/product/beanie/">
<meta property="og:site_name" content="Mass Grasshopper">
<meta property="og:title" content="Beanie">
<meta property="og:type" content="og:product">
<meta property="og:image" content="https://<<URLsite>>/wp-content/uploads/2021/11/beanie.jpg">
<meta property="product:price:currency" content="USD">
<meta property="product:price:amount" content="18">
<meta property="og:price:standard_amount" content="20">
<meta property="og:description" content="Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.">
<meta property="og:availability" content="instock">
```
Example for Posts:
```
<meta property="og:url" content="https://<<URLsite>>/2021/11/08/hello-world/">
<meta property="og:site_name" content="Mass Grasshopper">
<meta property="og:type" content="article">
<meta property="og:title" content="Hello world!">
<meta property="og:description" content="Welcome to WordPress. This is your first post. Edit or delete it, then start writing!">
<meta property="article:published_time" content="2021-11-08T11:12:15+00:00">
<meta property="article:author" content="demo">
```
## 9.0 Save to Pinterest
Shoppers can save (pin) products to Pinterest from the product page and archive pages, e.g. shop page.
This option can be disabled on the Settings page. (Enabled by default)

### Pre-release tests that we must do before release.
- Install plugin but keep it deactivated (first time)- check front-end/back-end/logs has not fatal error
- Activate plugin for the first time - check front-end/back-end/logs has not fatal error
- Update plugin on sites that plugin is deactivated - check front-end/back-end/logs have not fatal error
- Update plugin on sites that plugin is activated - check front-end/back-end/logs has not a fatal error

