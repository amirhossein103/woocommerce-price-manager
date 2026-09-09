# WooCommerce Price Manager

[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-8892BF.svg)](https://php.net/)
[![WordPress 6.3+](https://img.shields.io/badge/WordPress-6.3%2B-21759B.svg)](https://wordpress.org/)
[![WooCommerce 8.0+](https://img.shields.io/badge/WooCommerce-8.0%2B-96588A.svg)](https://woocommerce.com/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Managing prices one product at a time gets painful once a WooCommerce store has hundreds of products. This plugin adds a spreadsheet-style admin interface for editing prices, sale prices, and stock quantities across simple and variable products — without opening each product individually.

## Overview

WooCommerce Price Manager is a WordPress admin plugin that provides:

- **Inline editing** of regular prices, sale prices, and stock quantities directly in a product table
- **Bulk price operations** — increase or decrease prices by percentage or fixed amount across selected products
- **Variable product support** — expand any variable product to edit each variation's price and stock independently
- **Change history with rollback** — every edit is logged, and any change can be reverted with one click
- **Preview before applying** — bulk operations show a preview of calculated prices before touching the database

The plugin uses the WooCommerce CRUD API exclusively for all product writes (no direct `wp_posts` queries), runs entirely through the WordPress REST API (no `admin-ajax.php`), and ships a React-based single-page admin interface.

## Features

### Inline Product Editing

The main interface renders a paginated product table. Each row shows the product image, name, SKU, type, stock status, regular price, and sale price. Price and stock fields are editable inline — click a value, type the new one, and it saves immediately via the REST API.

Both simple products and individual variations of variable products can be edited the same way.

### Bulk Price Operations

Select multiple products using checkboxes, then apply one of:

| Operation | Description |
|---|---|
| Percentage increase | Increase prices by N% |
| Percentage decrease | Decrease prices by N% |
| Fixed increase | Add a fixed amount to prices |
| Fixed decrease | Subtract a fixed amount from prices |
| Set fixed price | Set all selected products to a specific price |

Bulk operations can target the regular price, sale price, or both. The plugin resolves variable products to their individual variations automatically, so selecting a variable product applies the operation to all its variations.

Before execution, a **preview modal** shows the calculated result for every affected product — including which products would fail validation (e.g., sale price exceeding regular price). You can exclude individual products from the preview before confirming.

### Stock Management

- Toggle stock management on/off per product or variation
- Edit stock quantities inline when stock management is enabled
- Change stock status (In Stock / Out of Stock / On Backorder) for unmanaged products
- Bulk stock operations: set quantity, increase quantity, or clear stock management

Stock status automatically syncs with quantity when stock management is enabled (quantity > 0 = in stock, 0 = out of stock).

### Variable Products and Variations

Variable products display their variation count in the table. Clicking the expand button loads all variations via a separate API call, showing them as indented rows beneath the parent product. Each variation shows its attribute values (e.g., "Color: Red, Size: L") and can be edited independently.

You can also set the default variation for a variable product directly from the table.

### Sale Schedule

A sale schedule modal lets you set `date_on_sale_from` and `date_on_sale_to` for individual products or variations. These dates are passed through to WooCommerce's native sale scheduling.

### Search and Filtering

The toolbar provides:

- **Text search** — searches by product name (or by product ID if the input is numeric)
- **Category filter** — filter by any WooCommerce product category (includes descendant categories)
- **Stock status filter** — In Stock, Out of Stock, On Backorder
- **Product status filter** — Published, Draft
- **Sorting** — by name, with ascending/descending order
- **Pagination** — configurable page size, server-side pagination

### Change History and Rollback

Every price or stock edit — whether manual or from a bulk operation — is recorded in a custom database table (`wp_wpm_change_log`). Each record stores:

- Product ID
- User who made the change
- Field changed (regular_price, sale_price, stock_quantity, etc.)
- Old value and new value
- Operation type (manual_edit, bulk_operation, rollback)
- Timestamp

A per-product history drawer shows recent changes and lets you roll back any individual change. The rollback re-validates through the domain layer, so it won't create an invalid state (e.g., it won't restore a sale price that would now exceed the current regular price).

Bulk operations are also tracked as a group, with a **global history view** that shows all bulk jobs and lets you roll back an entire batch at once.

To keep the database lean, the plugin automatically prunes history to retain only the 5 most recent changes per product.

### Access Control

The plugin creates a custom WordPress capability (`manage_product_pricing`) and a dedicated role (`pricing_manager`). On activation, this capability is automatically granted to Administrators and Shop Managers.

Every REST endpoint checks for this capability before returning data or accepting writes. Write operations also verify the WordPress REST nonce.

## Architecture

The codebase follows a layered structure:

```
src/
├── Domain/           # Pure PHP business logic (no WordPress dependencies)
│   ├── Contract/     # Repository interfaces
│   ├── Entity/       # Product, Variation, ChangeRecord
│   ├── Exception/    # Domain exceptions
│   ├── Pricing/      # PricingEngine — price calculations and validation
│   ├── Stock/        # StockManager — stock calculations and validation
│   └── ValueObject/  # BulkOperation, PriceSnapshot, StockSnapshot, Money, etc.
│
├── Application/      # Use-case orchestration
│   └── Service/      # ProductListingService, ProductSaveService,
│                     # BulkOperationService, RollbackService
│
├── Infrastructure/   # WordPress/WooCommerce integration
│   ├── AccessControl/  # Role and capability management
│   ├── Adapter/        # WooCommerceProductAdapter (WC CRUD wrapper)
│   ├── Admin/          # AdminMenu — WP admin page registration and asset enqueue
│   ├── DI/             # Simple DI container
│   ├── Database/       # Schema migrations (dbDelta)
│   ├── Repository/     # ChangeLogRepository, CategoryRepository
│   └── Rest/           # ProductController, HistoryController, BulkOperationController
│
└── Plugin.php        # Bootstrap, DI wiring, hook registration
```

The Domain layer contains no references to WordPress or WooCommerce functions. Infrastructure adapters implement domain interfaces, keeping business rules testable in isolation.

The frontend is a React SPA using `@wordpress/element`, `@wordpress/data` (Redux store), `@wordpress/components`, and `@wordpress/api-fetch`. It mounts inside a single `<div id="wpm-root">` rendered by the admin page.

### REST API Endpoints

All endpoints live under the `woo-ops/v1` namespace:

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/products` | List products with filtering and pagination |
| `GET` | `/products/{id}` | Get single product |
| `PUT` | `/products/{id}` | Update product price/stock |
| `GET` | `/products/{id}/variations` | Get variations for a variable product |
| `PUT` | `/products/{id}/default-variation` | Set default variation |
| `GET` | `/products/{id}/history` | Get change history for a product |
| `POST` | `/products/{id}/history/{change_id}/rollback` | Roll back a specific change |
| `POST` | `/products/bulk/preview` | Preview bulk operation results |
| `POST` | `/products/bulk/execute` | Execute bulk operation |
| `GET` | `/history/global` | List all bulk operation jobs |
| `POST` | `/history/global/{bulk_id}/rollback` | Roll back entire bulk operation |

## Installation

### Requirements

- PHP 8.0 or higher
- WordPress 6.3 or higher
- WooCommerce 8.0 or higher

### Steps

1. Download or clone this repository into `wp-content/plugins/woocommerce-price-manager/`
2. Activate the plugin from the WordPress admin plugins screen
3. Navigate to **WooCommerce → Price Manager** in the admin menu

On activation, the plugin creates the `pricing_manager` role, grants `manage_product_pricing` to Administrators and Shop Managers, and runs the database migration to create the `wp_wpm_change_log` table.

### Development Setup

If you want to modify the source:

```bash
# Install PHP dependencies (for tests)
composer install

# Install JS dependencies
npm install

# Start development build with file watching
npm start

# Production build
npm run build
```

The webpack configuration uses `@wordpress/scripts` with custom entry points at `assets/src/js/index.js` and `assets/src/css/index.css`, outputting to `assets/build/`.

## Usage

### Editing a Single Product

1. Open **WooCommerce → Price Manager**
2. Find the product using search or filters
3. Click on a price or stock cell to edit it
4. Type the new value and press Enter or click away to save
5. The change is applied immediately via the REST API

### Bulk Price Update

1. Select products using the checkboxes (or use "Select All")
2. The bulk toolbar appears at the top of the table
3. Choose an operation type (e.g., "Increase Price (%)")
4. Enter the parameter (e.g., `10` for 10%)
5. Choose the target field (Regular Price, Sale Price, or Both)
6. Click **Preview** to see calculated results
7. Review the preview — exclude any products if needed
8. Click **Apply** to execute

### Rolling Back a Change

1. Click the history icon (clock) on any product row
2. The history drawer shows recent changes with old → new values
3. Click **Rollback** next to any change to restore the previous value

## Internationalization

The plugin text domain is `woo-price-manager`. All user-facing strings use WordPress i18n functions (`__()`, `sprintf()` in PHP; `@wordpress/i18n` in JS).

A Farsi (fa_IR) translation is included in the `languages/` directory.

Currency formatting (symbol, position, decimal separator, thousand separator, decimal places) is read from WooCommerce settings at runtime — the plugin does not hardcode any currency format.

## Security

- All REST endpoints require the `manage_product_pricing` capability
- Write operations verify the WordPress REST nonce (`X-WP-Nonce`)
- All input parameters are sanitized via WordPress sanitization callbacks
- Database queries use `$wpdb->prepare()` with parameterized placeholders
- Product writes use the WooCommerce CRUD API (`WC_Product->set_*()` + `->save()`)

If you discover a security vulnerability, please report it by opening a private security advisory on the [GitHub repository](https://github.com/amirhossein103/woocommerce-price-manager/security/advisories/new).

## License

This project is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).
