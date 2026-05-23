# Release Notes for Commerce Product Feed

All notable changes to this project will be documented in this file.

## 1.3.0 - 2026-05-23
### Added
- **Variant Selector**: Added dynamic, client-side variant dropdown selector to the product detail page (`product.twig`).
- **Dynamic DOM Updates**: Added instant Vanilla JS syncing of SKU, price, and weight spec on option selection change, eliminating slow page reloads.
- **Improved Listing Navigation**: Enhanced product list cards (`index.twig`) with clickable images, a prominent detail page button for variable products, and compact details action links for simple products.
- **Cart Clarity**: Displayed selected variant title (e.g., `Variant: test 22`) directly underneath the parent product title inside the shopping cart template (`cart.twig`).
- **Asset Branding**: Added a premium vibrant plugin logo icon (`icon.svg`) to the `src` folder.

### Fixed
- **Variant Availability**: Resolved DDEV database configuration issue where the second test variant `test 22` was marked as unavailable for purchase, blocking test checkouts.

## 1.2.0 - 2026-05-23
### Added
- **Social Commerce Expansions**: Implemented three major international feeds formats:
  - **Meta Shop Catalog XML**: High-compliance feed mapping.
  - **Pinterest Catalog XML**: Catalog RSS synchronization feed.
  - **TikTok Shop XML**: TikTok Catalog compliant structure.
- **Dynamic Routing**: Registered public feed access endpoints for the new formats (`/feeds/meta.xml`, `/feeds/pinterest.xml`, `/feeds/tiktok.xml`).
- **Statistics & Copy Controls**: Extended the Control Panel admin settings page (`_settings.twig`) with toggles, dynamic clipboard copy inputs, and metrics counters.
- **Namespaced Settings Prefixing**: Fixed settings panel clipboard selector name clashes inside the DOM.

## 1.1.0 - 2026-05-23
### Added
- **Real-Time Feed Compliance Validator Widget**: Added an element CP edit sidebar diagnostic card by hooking into Craft CMS's core `Product::EVENT_DEFINE_SIDEBAR_HTML` event, giving merchants instant compliance warnings for missing GTIN, Brand, MPN, or images before export.

## 1.0.0 - 2026-05-23
### Added
- **Initial Release**: Ported the original WordPress WooCommerce syndication plugin (`tka-product-feeds`) to Craft CMS & Craft Commerce 5.
- **Core Formats Support**: Support for Google Shopping XML, OpenAI JSON, Ricardo.ch JSON, and generic CSV formats.
- **Robust Cache Pipeline**: Automatic feed generation caching with instant invalidation hooks on product save/delete events.
