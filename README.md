# Commerce Product Feed Plugin for Craft CMS & Commerce 5

A premium, high-performance plugin to gather and export Craft Commerce products and variants into optimized RSS XML and JSON feed formats compliant with Google Shopping, Meta (Facebook/Instagram) Catalog, Pinterest Product Pin Catalog, TikTok Shop, Ricardo.ch, OpenAI (ChatGPT) Merchants, and custom CSV integrations.

---

## Features

- **Dynamic Public Feeds:** Streamlined dynamic controllers return cached feed payloads with correct HTTP headers.
- **Multiple Feed Formats:**
  - **Google Shopping RSS (XML):** Namespaced metadata (`xmlns:g="http://base.google.com/ns/1.0"`).
  - **Meta Catalog (XML):** Compliant Meta (Facebook/Instagram Shop) catalog feed.
  - **Pinterest Catalog (XML):** Pinterest compliant Product Pin catalog RSS feed.
  - **TikTok Shop Catalog (XML):** TikTok Catalog RSS specification compliant feed.
  - **OpenAI Merchant (JSON):** Paginated list of variations, prices, weight/dimension descriptors.
  - **Ricardo.ch (JSON):** Swiss-market compliant (CHF) with shipping metadata, specs, and return policies.
  - **Generic Products List (CSV):** Comma-separated spreadsheet with UTF-8 BOM byte marker for Excel compatibility.
- **Control Panel Real-Time Feed Compliance Validator:** A beautiful diagnostic card shown natively on the Product Edit Control Panel screen by hooking into Craft CMS's core `Product::EVENT_DEFINE_SIDEBAR_HTML` event, giving merchants instant warnings for missing GTIN, Brand, MPN, or images before catalog export.
- **Craft-Idiomatic Field Overrides:** Maps any custom text, rich-text, asset relation, or category fields to feed columns without hacking core models.
- **Real-Time Cache Invalidation:** Automatically purges feed caches whenever a Product or Variant saves or deletes.
- **Developer CLI Tooling:** Clean console actions to clear cache, warm feeds, or inspect diagnostics inside the shell.

---

## Requirements

This plugin requires:
- **Craft CMS** `5.10.0` or later
- **Craft Commerce** `5.6.0` or later
- **PHP** `8.2` or later

---

## Installation

Run the following commands inside your Craft CMS terminal directory:

```bash
# Install package from path repository
ddev composer require thekitchen-agency/craft-commerce-product-feed

# Install plugin into Craft
ddev php craft plugin/install commerce-product-feed
```

---

## Developer Step-by-Step Setup Guide

To get your feeds fully populated, follow these steps to map your custom field configurations in Craft CMS.

### Step 1: Create Your Custom Overrides Fields
Go to **Settings → Fields** and create the fields you want to use for product feeds overrides. Some typical examples:
1. **Brand (Plain Text):** Field handle `productBrand` (used to define manufacturer names).
2. **GTIN / EAN (Plain Text):** Field handle `productGtin` (strongly recommended for Google Shopping, Meta, and Ricardo.ch).
3. **MPN (Plain Text):** Field handle `productMpn` (Manufacturer Part Number).
4. **Exclude From Feeds (Lightswitch):** Field handle `excludeFromFeeds` (allows turning off feed sync for specific entries).

### Step 2: Assign Fields to Element Layouts
Go to **Commerce → Settings → Product Types** and click your product type:
- You can add these fields directly to the **Product Field Layout** (applies to the entire product and all variants).
- Alternatively, you can add fields (like GTIN or Brand) to the **Variant Field Layout** if different variants have different barcodes or brands. *Our helper automatically checks the Variant layout first and falls back to the Product layout.*

### Step 3: Configure Settings in the Control Panel
Go to **Settings → Commerce Product Feed** in your Craft Control Panel:
1. **General Config:** Choose which feed formats to activate (Google, Meta, Pinterest, TikTok, OpenAI, Ricardo, CSV) and set default product condition fallback.
2. **Fields Mapping:** Select your custom fields handles using the interactive dropdown selections.
3. **Live Feed Status:** Copy your public feed endpoints and inspect real-time validation error alerts.

---

## Feed Endpoint URLs

Once enabled, your feeds are publicly available at the following sites routes:

- **Google Shopping RSS:** `https://yourdomain.test/feeds/google-feed.xml`
- **Meta Shop XML:** `https://yourdomain.test/feeds/meta.xml`
- **Pinterest Catalog XML:** `https://yourdomain.test/feeds/pinterest.xml`
- **TikTok Shop XML:** `https://yourdomain.test/feeds/tiktok.xml`
- **OpenAI Feed (Paginated):** `https://yourdomain.test/feeds/openai.json?page=1&per_page=100`
- **Ricardo.ch Feed:** `https://yourdomain.test/feeds/ricardo.json`
- **Generic Products CSV:** `https://yourdomain.test/feeds/product-feed.csv`
- **Raw Products (Debug):** `https://yourdomain.test/feeds/products.json`

*To bypass yii cache during debugging or development, simply append `?nocache=1` to any URL.*

---

## Console API Commands (Cron Setup)

To warm or update caches periodically on massive catalogs, register a system cron job to execute the Craft console action.

### 1. Warm / Regenerate Cache
Warms/generates the feed caches in the background. Good for high-traffic environments:
```bash
# Warm all active feeds
ddev php craft commerce-product-feed/feed/generate

# Warm only a specific format
ddev php craft commerce-product-feed/feed/generate google
ddev php craft commerce-product-feed/feed/generate meta
ddev php craft commerce-product-feed/feed/generate pinterest
ddev php craft commerce-product-feed/feed/generate tiktok
```

### 2. Clear Feed Cache
```bash
# Clear all caches
ddev php craft commerce-product-feed/feed/clear

# Clear specific format
ddev php craft commerce-product-feed/feed/clear google
ddev php craft commerce-product-feed/feed/clear meta
```

### 3. CLI Diagnostics Status
Displays feed statistics, product counts, sizes, and prints a shell report of all schema validation warnings:
```bash
ddev php craft commerce-product-feed/feed/status
```

### 4. Background Server Cron Recommendation
To warm the feed caches hourly, add this crontab entry to your server:
```cron
0 * * * * /usr/local/bin/php /path/to/craft commerce-product-feed/feed/generate >/dev/null 2>&1
```
# craft-commerce-product-feed
