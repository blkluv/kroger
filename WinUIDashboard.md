Goal: A browser-based admin panel that feels like a WinUI 3 shell: clean, pane-based, with a left nav and content cards.

Core sections:

Dashboard

Tiles:

Total users

Total stores

Total products

Price changes in last 24h

API error count (last 24h)

Charts:

Price changes over time (from product_price_history)

Top searched products (if you log searches)

Store coverage map (stores by state)

Stores

List: stores (search by city, state, postal, chain)

Detail view:

Basic info (name, address, phone, division, store_number)

Map (lat/long)

Hours (parsed from hours_json)

Raw JSON viewer (raw_json)

Linked products (via product_price_history.kroger_location_id)

Products

List: products with filters:

brand, category (parsed), inventory_level, fulfillment flags

Detail:

Core info (description, brand, size, image)

Pricing (regular, sale, national)

Fulfillment flags

Aisle locations (parsed from aisle_locations)

Price history chart (from product_price_history)

Raw JSON viewer (raw_json)

Users

List: users

Detail:

Profile info

Grocery list items (grocery_list_items)

Monthly staples (monthly_staples)

Order history (once you add orders table)

API Monitor

Log of last N API calls (if you log them)

Error breakdown by endpoint

Rate limit status (if you track headers)

UI patterns:

Left nav: Dashboard, Stores, Products, Users, API Monitor, Settings

Top bar: Environment badge (DEV/PROD), current store, user menu

Cards: Use subtle shadows, rounded corners, WinUI-like typography

Colors: Neutral base, accent color matching Kroger/Fry’s blue