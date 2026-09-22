# 🏨 Haven Hotel - Full-Stack Hotel Management System

A production-ready, mobile-first Hotel Management & Reservation Web Application built with **PHP 8.3**, **MySQL**, **Vanilla JavaScript (ES6+)**, and **Modern Responsive CSS3**.

---

## 🌐 Live Website & Demo

- **Live URL**: [https://buck-essential-examples-amounts.trycloudflare.com](https://buck-essential-examples-amounts.trycloudflare.com)
- **GitHub Repository**: [https://github.com/ShreyashPatil123/hotel-management](https://github.com/ShreyashPatil123/hotel-management)

---

## ✨ Key Features

### 1. 📱 Responsive Mobile-First Design
- **Dynamic Adaptability**: Seamless touch experience on iOS, Android, tablets, and desktops.
- **Left-Handed Mobile Drawer**: Floating hamburger trigger on the top-left opening a glassmorphic navigation drawer with thumb-zone ergonomics.
- **Touch-Friendly Booking Cards**: Dedicated dual-mode layout in *My Bookings* featuring responsive cards on mobile screens ($\le 768\text{px}$) and a high-density data table on desktop.
- **Bottom-Sheet Cancellation Modals**: Native bottom-sheet interaction on mobile devices with touch backdrop dismissal.

### 2. 🛏️ Room Capacity & Dynamic Extra Mattress Add-ons
- Displays **Base Capacity** and **Maximum Capacity** for every room tier (e.g. *2 Standard / 3 Max*).
- Dynamic calculation and live badge display for extra mattress requests with explicit daily charges (**+₹800/night/mattress**).
- Prevents overbooking beyond maximum allowable room capacities with real-time client & server validation.

### 3. ⚠️ Transparent Cancellation & Full Refund Policy
- **Cancelled by Hotel**:
  - Automatically records hotel operational cancellation reason (e.g., *Emergency Room Maintenance & Plumbing Repairs*, *Force Majeure*, *Deep Sanitization*).
  - Flags **100% Full Charges Reverted** with zero cancellation fees.
  - Prominently displays `⚠️ Cancelled by Hotel` callout banner across both desktop and mobile views.
- **Cancelled by Customer**:
  - Free cancellation window with **100% full refund** if cancelled $\ge 48$ hours before check-in.
  - Transparent late cancellation fee (10%) if cancelled within 48 hours of check-in, reverting 90% of charges.
  - Documents optional customer reason and tracks refund processing status.

### 4. ⚡ Real-Time Live Synchronization & Audio Feedback
- Cross-tab and multi-device synchronization engine powered by `BroadcastChannel` and 2-second fast polling.
- Web Audio API synthesizer for positive (approval) and alert (cancellation) chimes.
- Floating toast notification center for live reservation status alerts.

### 5. 🛡️ Admin Management Portal
- Real-time reservation status updates (Pending, Confirmed, Cancelled, Completed).
- Hotel Cancellation modal enforcing mandatory reason documentation and 100% refund confirmation.
- Room inventory & pricing management with high-resolution relatable hotel photography.

---

## 🔑 Demo Credentials

| Portal | Email | Password |
|---|---|---|
| **Admin** | `admin@havenhotel.com` | `password` |
| **Customer** | `customer@example.com` | `password` |

---

## 🛠️ Technology Stack

- **Backend**: Native PHP 8.3, PDO Prepared Statements, RESTful JSON Endpoints
- **Database**: MySQL 8.0 / MariaDB (InnoDB, Foreign Key Constraints, B-Tree Indexes)
- **Frontend**: Vanilla JavaScript (ES6+), Modern CSS3 (CSS Variables, Flexbox, CSS Grid)
- **Real-Time Engine**: Web Audio API, BroadcastChannel API, Polling Event Log
- **Container / Deployment**: Docker, Apache2 (`php:8.3-apache`), Render Blueprint (`render.yaml`), Vercel (`vercel.json`), Netlify (`netlify.toml`), Cloudflare Edge

---

## 💻 Local Development Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/ShreyashPatil123/hotel-management.git
   cd hotel-management
   ```

2. **Import Database**:
   - Create a MySQL database named `hotel_management`.
   - Import `db/hotel_management.sql`.

3. **Configure Connection**:
   - The app connects to `localhost:3306` with user `root` and blank password by default.
   - Alternatively set environment variables: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_PORT` or `MYSQL_URL`.

4. **Start PHP Server**:
   ```bash
   php -S 0.0.0.0:8000
   ```
   Open `http://localhost:8000` in your browser.
