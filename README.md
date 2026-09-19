# TestShare — Enterprise Multi-Vendor Food Delivery & Merchant Management Ecosystem 🍔🚀

> **Enterprise Project**: A complete, full-stack multi-role web ecosystem built using **PHP, MySQL, Vanilla JavaScript, HTML5, Leaflet Maps API, and Modern CSS Architecture**.

---

## 📌 Project Overview

**TestShare** is a feature-rich, enterprise-grade multi-vendor food delivery and merchant management web application. It connects **Customers**, **Restaurant Merchants (Sellers)**, and **Platform Administrators** into a unified, location-aware digital marketplace.

Beyond standard food ordering, TestShare incorporates cutting-edge real-world features such as **Geofenced Delivery Radius Checkers**, **Tiered Merchant Subscriptions**, **Custom Catering Negotiations**, **Store-to-Store Raw Material Trading**, **Subsidized Platform Offers**, and **Automated Refund Workflows**.

---

## 🔥 Key Highlight Features

### 1. 📍 Location-Based Delivery & Haversine Geofencing Engine
- **Interactive OpenStreetMap Integration**: Draggable map pins and real-time reverse geocoding via Leaflet.js and OpenStreetMap Nominatim API.
- **GPS Auto-Detection**: One-click **"Use Current Location"** button detecting device latitude/longitude coordinates instantly.
- **Dynamic Delivery Radius Circle**: Visual circular geofence overlay scaling in real-time as merchants adjust their delivery radius slider (1 km to 50 km).
- **Strict Geofence Order Blocking**: Server-side and client-side **Haversine Distance Formula** calculation preventing order placement if a customer is located outside the restaurant's operational radius.

### 2. 💳 Tiered Merchant Subscription & Prorated Billing Model
- **3-Tier Subscription Scaling**:
  - **Starter Tier** (Free): Standard storefront listing with 5.0% platform transaction commission fee.
  - **Premium Tier** (₹49/mo): 2.0% transaction commission fee, highlighted placement (Rating ≥ 3.5), custom accent color theme, and custom order acceptance.
  - **Ultra Premium Tier** (₹149/mo): **0% Zero Commission Fee**, VIP #1 top priority storefront placement, Raw Material Marketplace access, and full panel customization.

### 3. 🏷️ Dynamic Promotional Offers & Global Subsidy Engine
- **Merchant Store Coupons**: Restaurant-specific promo codes with discount percentages, minimum order requirements, and expiry dates.
- **Global Platform Coupons**: Platform-funded offers where discount costs are shared between TestShare and merchants.

### 4. 🤝 Bulk Catering & Custom Order Negotiation System
- **Custom Price Quotations**: Customers can submit special custom/bulk order requests specifying guest count, target date, budget, and dietary requests.
- **Buyer-Seller Counter-Offer Workflow**: Built-in offer demand negotiation framework.

### 5. 🌾 Raw Material Purchase & B2B Marketplace
- **Store-to-Store Trading**: Exclusive Ultra Premium feature enabling merchants to buy and sell raw food ingredients in bulk.

---

## 🛠️ Technology Stack

| Layer | Technology Used |
|---|---|
| **Backend** | PHP 7.4+ / 8.x, PDO Prepared Statements, Object-Oriented View Renderer |
| **Database** | MySQL / MariaDB (Database Name: `TESTSHARE`) |
| **Frontend** | HTML5, Vanilla JavaScript (ES6+), Vanilla CSS, Tailwind CSS Utility Classes |
| **UI Components** | Google Material Symbols, Inter Typography, Modern Dark Theme |
| **Mapping & GPS** | Leaflet.js, OpenStreetMap Nominatim API, HTML5 Geolocation API, Haversine Formula |

---

## 💻 Running the Application

### 1. Database Connection (`TESTSHARE`)
- Database Name: `TESTSHARE`
- Connection configuration is defined in `web/db.php`:
  ```php
  define('DB_NAME', 'TESTSHARE');
  define('DB_USER', 'root');
  define('DB_PASS', '');
  ```

### 2. Start Local Server
Run PHP built-in web server inside `web/` directory:
```bash
cd web
F:\Apps\xampp\php\php.exe -S localhost:8000
```

Open your browser to:
[http://localhost:8000/index.php?route=user/home](http://localhost:8000/index.php?route=user/home)

---

## 🔐 Credentials & Default Accounts

Default Password for ALL accounts: **`password123`**

| Role | Email | Login Route |
|---|---|---|
| **Super Admin** | `admin@testshare.com` | `?route=user/login` / `?route=admin/sales_analytics` |
| **Seller (Royal Punjab Grill)** | `seller@testshare.com` | `?route=seller/login` |
| **Seller (Central Bakery)** | `sanjeev@bakery88.in` | `?route=seller/login` |
| **Seller (Curry Leaves)** | `kunal@curryleaves.in` | `?route=seller/login` |
| **Seller (Pista House Biryani)** | `owner@pistahouse.com` | `?route=seller/login` |
| **Customer (Rohan Malhotra)** | `customer@testshare.com` | `?route=user/login` |
| **Customer (Aarav Sharma)** | `aarav.sharma@testshare.in` | `?route=user/login` |
