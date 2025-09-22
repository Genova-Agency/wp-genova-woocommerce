# WP Genova WooCommerce
- Customers can submit claims using a shortcode-based **claim form**.
- Admins can configure API base URL, API key, and trigger type.


Built with **security** (API key encryption), **resilience** (retry queue with Action Scheduler), and **transparency** (logging + admin order columns).


---


## Installation


1. Upload the plugin folder `wp-genova-woocommerce` to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins → Installed Plugins**.
3. Go to **WooCommerce → Genova Insurance** and configure:
- Insurance API Base URL (e.g., `https://example.com/api/insurance`)
- API Key
- Purchase Trigger (Order Processed or Payment Complete)


4. On checkout, customers will see insurance options. A fee is applied if selected.


5. To allow customers to make claims, add this shortcode to a page:
```
[wp_genova_claim_form]
```


---


## Frequently Asked Questions


### Where does the insurance data come from?
From your configured **Genova Insurance API** (`/plans`, `/purchase`, `/claim`).


### How are API keys stored?
The API key is encrypted using `openssl_encrypt` with your WordPress `AUTH_SALT`. It’s decrypted only when sending API requests.


### What happens if purchase fails?
Failed purchase calls are queued with **Action Scheduler** and retried with exponential backoff.


### How do I view insurance policy IDs in admin?
A **Policy ID** column is added to the WooCommerce Orders list.


---


## Shortcodes


- **`[wp_genova_claim_form]`** – renders a customer claim submission form.


---


## WP-CLI Commands

- Retry all failed purchases:
```bash
wp genova retry-failed
```


---


## Developer Notes


- **Hooks:**
- `woocommerce_review_order_before_submit` → injects insurance UI.
- `woocommerce_cart_calculate_fees` → adds insurance fee.
- `woocommerce_checkout_update_order_meta` → saves insurance plan.
- `woocommerce_checkout_order_processed` / `woocommerce_payment_complete` → triggers purchase call.


- **Assets:**
- `assets/wp-genova.js` → handles plan selection, AJAX calls, checkout update.
- `assets/wp-genova.css` → basic styling.


- **Tests:** PHPUnit test skeleton included in `/tests`.


---


## Screenshots


1. Insurance selection at checkout.
2. Admin settings screen for API base and key.
3. Order list with policy ID column.
4. Claim form page.


---


## Changelog


### 1.0.0
* Initial release with checkout insurance integration, claim form, retries, encryption, and admin UI.


---


## Upgrade Notice


### 1.0.0
Initial release — install to embed Genova insurance in WooCommerce checkout with claims support.