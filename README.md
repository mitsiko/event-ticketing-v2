
# Event Management & Ticketing System

**University of Perpetual Help System DALTA — Molino Campus**
A university-focused web application for creating, managing, and registering for school events with digital ticket generation and validation.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php) ![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql) ![Python](https://img.shields.io/badge/Python-3.12-3776AB?logo=python)  ![XAMPP](https://img.shields.io/badge/XAMPP-8.x-FB7A24?logo=xampp)  ![License](https://img.shields.io/badge/License-Academic-blue)
----------

## Table of Contents

-   System Overview
-   Features
-   Technology Stack
-   Prerequisites
-   Installation & Setup
-   File Structure
-   Core Modules
-   CRUD Operations
-   Ports & Configuration
-   Accessing the System
-   Color Theme
-   License

----------

## System Overview

The Event Management and Ticketing System enables accredited student organizations, alumni groups, university offices, and external organizers to create and manage school events. Attendees — including students, employees, alumni, and guests — can register online and receive unique digital ticket codes tied to their identity. The system enforces per-event audience rules and provides ticket validation at event entrances.

The system consists of two portals:
-   **Admin Portal** (`/index.php`) — For event organizers and administrators
-   **Audience Portal** (`/online-reg.php`) — For attendees to browse and register for events
    

----------

## Features

### Admin Portal

-   Dashboard with real-time statistics (events, attendees, tickets, revenue)
-   Full CRUD management for Events, Venues, Organizations, Ticket Categories, and Attendees
-   UUID-based ticket generation with QR codes
-   Ticket validation system for event entrances
-   Payment status management with strict business rules
-   Overlay modal editing for Venues, Organizations, Categories, and Attendees
-   Detailed view pages with analytics and ticket history
-   Foreign key protection preventing accidental data loss

### Audience Portal (Public)
-   Browse upcoming events with search and filtering
-   Three-view toggle: Ticketed Events, Free Entry, and All Events
-   Full-width welcome section with campus branding
-   Streamlined 3-step registration wizard (Personal Info → Ticket Selection → Confirmation)
-   Dynamic ticket options filtered by attendee type
-   Digital ticket with QR code generation
-   Public ticket lookup page with print capability
-   Responsive design for mobile devices

----------

## Technology Stack

|Layer| Technology|
|-----|-----|
|**Frontend**|HTML5, CSS3, JavaScript (Vanilla)|
|**Backend**|PHP 8.2|
|**Database**|MySQL (MariaDB 10.4) via XAMPP|
|**Server**|Apache (XAMPP)|
|**Fonts**|Inter, Playfair Display (Google Fonts)|
|**QR Codes**|QRCode.js library|
|**UUID Generation**| Python 3|

----------


## Prerequisites

Before installing, ensure you have the following:

1.  **XAMPP** (version 8.2.12 or higher recommended)
    -   Download from: [https://www.apachefriends.org/](https://www.apachefriends.org/)
    -   Includes Apache, MySQL (MariaDB), PHP, and phpMyAdmin
        
2.  **Python 3** (for UUID generation)
    -   Download from: [https://www.python.org/downloads/](https://www.python.org/downloads/)
    -   Ensure Python is added to your system PATH
    -   Required package: `mysql-connector-python` — install using:
        ``` bash
         pip install mysql-connector-python
        ```
        
3.  **A modern web browser** (Chrome, Firefox, Edge, or Safari)
4.  **Git** (optional, for cloning the repository)


## Installation & Setup

### ⚠️ CRITICAL: Folder Name Requirement

**You MUST name your project folder `event-ticketing-v2`.** The entire project uses this folder name in all internal links, redirects, and resource paths. Using any other folder name will result in broken links, missing CSS, and non-functional navigation.

### Step 1: Clone or Download the Project

**Option A: Using Git**

```bash
 cd C:\xampp\htdocs
 git clone <repository-url> event-ticketing-v2
```
**Option B: Manual Download**

1.  Download the project ZIP file
2.  Extract it to `C:\xampp\htdocs\`
3.  **Rename the extracted folder to exactly:**  `event-ticketing-v2`
    

Your final path should be:
```text
 C:\xampp\htdocs\event-ticketing-v2\
```

### Step 2: Configure XAMPP MySQL Port

> **IMPORTANT:** This project uses MySQL port **3307** instead of the default 3306.

1.  Open the **XAMPP Control Panel**
    
2.  Click the **Config** button on the MySQL row → Select `my.ini`
    
3.  Find the lines that say `port=3306` and change them to `port=3307` (there are usually two occurrences)
    
4.  Save the file
    
5.  Click **Stop** then **Start** on MySQL to restart with the new port
    
6.  Verify MySQL is running on port 3307
    

### Step 3: Start XAMPP Services

1.  Open **XAMPP Control Panel**
    
2.  Click **Start** on **Apache**
    
3.  Click **Start** on **MySQL** (should show Port: 3307)
    
4.  Both services should show green indicators
    

### Step 4: Create the Database

1.  Open your browser and go to: `http://localhost:3307/phpmyadmin`
    
    > Note: Use port 3307, not the default 3306
    
2.  Click **New** in the left sidebar
    
3.  Enter database name: `event_ticketing_db`
    
4.  Choose `utf8mb4_unicode_ci` as collation
    
5.  Click **Create**
    

### Step 5: Import the Database Structure & Seed Data

1.  Select the `event_ticketing_db` database from the left sidebar
    
2.  Click the **Import** tab at the top
    
3.  Click **Choose File** and select `event_ticketing_db.sql` from the project folder
    
4.  Click **Go** at the bottom of the page
    
5.  You should see a success message with tables created and seed data inserted
    

### Step 6: Verify the Installation

1.  Open your browser and navigate to: `http://localhost/event-ticketing-v2/`
    
2.  You should see the Admin Login screen with the UPHSD logo
    
3.  Log in with default credentials:
    
    -   **Username:**  `admin`
        
    -   **Password:**  `admin123`
        
4.  Test the Audience Portal at: `http://localhost/event-ticketing-v2/online-reg.php`
    

----------

## File Structure

```text

	event-ticketing-v2/
	│
	├── api/
	│   ├── check-email.php              # AJAX endpoint for email validation
	│   ├── fetch-categories.php         # AJAX endpoint for dynamic category loading
	│   └── ticket_validate.php          # Ticket validation API endpoint
	│
	├── assets/
	│   ├── css/
	│   │   ├── style.css                # Admin portal stylesheet
	│   │   ├── modal-edit.css           # Overlay edit modal styles
	│   │   └── user-reg.css             # Audience portal stylesheet
	│   └── js/
	│       ├── modal-edit.js            # Overlay edit modal functionality
	│       ├── script.js                # Admin portal JavaScript
	│       └── user-reg.js              # Audience portal JavaScript
	│
	├── config/
	│   └── database.php                 # Database connection configuration
	│
	├── includes/
	│   ├── header.php                   # Admin header, sidebar, login, session management
	│   ├── footer.php                   # Admin footer, closing tags, script includes
	│   └── functions.php                # Helper functions, utilities, back URL logic
	│
	├── modules/
	│   ├── attendees/
	│   │   ├── create.php               # Register new attendee form
	│   │   ├── delete.php               # Delete attendee
	│   │   ├── edit.php                 # Edit attendee details
	│   │   ├── index.php                # List all attendees
	│   │   └── view.php                 # View attendee profile & ticket history
	│   │
	│   ├── categories/
	│   │   ├── create.php               # Add new ticket category
	│   │   ├── delete.php               # Delete ticket category
	│   │   ├── edit.php                 # Edit ticket category
	│   │   └── index.php                # List ticket categories
	│   │
	│   ├── events/
	│   │   ├── create.php               # Create new event form
	│   │   ├── delete.php               # Delete event
	│   │   ├── edit.php                 # Edit event details
	│   │   ├── index.php                # List all events
	│   │   └── view.php                 # View event details & sales analytics
	│   │
	│   ├── organizations/
	│   │   ├── create.php               # Register new organization
	│   │   ├── delete.php               # Delete organization
	│   │   ├── edit.php                 # Edit organization details
	│   │   └── index.php                # List all organizations
	│   │
	│   ├── tickets/
	│   │   ├── delete.php               # Delete ticket (restores slot)
	│   │   ├── download.php             # Download ticket as HTML
	│   │   ├── download-pdf.php         # Download ticket PDF version
	│   │   ├── edit.php                 # Edit ticket payment details
	│   │   ├── generate.php             # Generate new UUID ticket
	│   │   ├── index.php                # List all tickets
	│   │   ├── validate.php             # Validate ticket at entrance
	│   │   └── view.php                 # View ticket with QR code
	│   │
	│   └── venues/
	│       ├── create.php               # Add new venue
	│       ├── delete.php               # Delete venue
	│       ├── edit.php                 # Edit venue details
	│       └── index.php                # List all venues
	│
	├── python/
	│   ├── generate_reports.py          # Generate analytics reports
	│   ├── generate_uuid.py             # Generate UUID ticket codes
	│   └── validate_ticket.py           # Validate ticket codes (Python)
	│
	├── event_ticketing_db.sql           # Database schema & seed data (import via phpMyAdmin)
	├── index.php                        # Admin dashboard (login required)
	├── MOLINO-Campus-Facade-2.2.jpg     # Campus facade background image
	├── online-reg.php                   # Public event listing & registration portal
	├── online-register.php              # Public event registration form
	├── ticket-lookup.php                # Public ticket lookup & QR viewer
	├── uphsd-logo.png                   # University logo
	└── README.md                        # This file
	
```
----------

## Core Modules

### Dashboard (`/index.php`)

Displays real-time statistics including total events, upcoming events, attendees, tickets issued, validated tickets, and revenue. Shows recent events and recent ticket activity.

### Events (`/modules/events/`)

Create and manage university events. Each event connects to a venue and an organization. Supports audience type restrictions (student-only, employee-only, alumni-only, open-to-all) and ticketed/free entry options. The view page shows ticket sales analytics, attendee distribution, and revenue.

### Venues (`/modules/venues/`)

Manage university facilities including gymnasiums, auditoriums, classrooms, fields, courtyards, and amphitheaters. Tracks capacity, building location, floor level, and AV system availability. Edit buttons open in overlay modals for faster workflow.

### Organizations (`/modules/organizations/`)

Register and manage event organizers including student organizations, alumni groups, external partners, and university offices. Tracks adviser information, contact details, and accreditation status.

### Ticket Categories (`/modules/categories/`)

Define ticket tiers per event with audience eligibility, pricing, and slot limits. The system automatically tracks slots remaining as tickets are generated. Supports sorting by price and filtering by event.

### Attendees (`/modules/attendees/`)

Register event attendees with type-specific fields (student ID, employee ID, alumni ID, guest ID). The registration form dynamically adapts based on attendee type. The view page shows complete ticket history and event participation summary.

### Tickets (`/modules/tickets/`)

Generate unique UUID-based tickets with QR codes. Payment status follows strict business rules:

-   **Free events** → Only "Free" status allowed
-   **Pending tickets** → Can only change to "Paid"
-   **Paid tickets** → Can only change to "Refunded"
-   **Refunded tickets** → Cannot be changed
    

### Ticket Validation (`/modules/tickets/validate.php`)

Scan or enter ticket codes at event entrances. Validates UUID codes against the database and marks tickets as validated. Prevents duplicate entry by checking validation status. Includes QR code scanning via camera and file upload.

----------

## CRUD Operations

All six core modules follow the Create-Read-Update-Delete pattern with built-in protections:

| Module | Create | Read | Update | Delete |
|-----|-----|-----|-----|-----|
|**Venues**| Add new university facilities with type, capacity, building, and AV details| List view with type badges and capacity display| Edit all venue details via overlay modal | Protected — blocked if venue has associated events|
|**Organizations**|Register organizers with type, adviser, contact info, and accreditation status| List view with accreditation badges| Edit all organization details via overlay modal | Protected — blocked if organization has associated events |
|**Events**| Create events connecting venue, organization, audience type, and ticket requirements | List with search, status filter, and detailed view page | Edit all event fields including status and audience type | Protected — blocked if event has ticket categories |
|**Ticket Categories**|Add audience tiers with eligibility, pricing, and slot limits per event|List with sorting, filtering by event, and fill-rate progress bars|Edit pricing and slots (cannot reduce below sold count)|Protected — blocked if category has issued tickets
|**Attendees**|Register with dynamic type-specific fields (student/employee/alumni/guest)|List with search, type filter, and detailed profile view|Edit all attendee details|Protected — blocked if attendee has tickets|
|**Tickets**| Generate UUID codes with QR codes and automatic payment status|List with payment filters, view with QR display|Edit payment status with strict transition rules|Slots automatically restored to category|

----------

## Ports & Configuration

### MySQL Port: 3307

This project uses **port 3307** for MySQL instead of the XAMPP default port 3306.

**Why port 3307?** This avoids conflicts with other MySQL installations on the same machine and allows multiple MySQL instances to run simultaneously.

### Database Configuration

The database connection is configured in `config/database.php`:

```php
 define('DB_HOST', 'localhost:3307');
 define('DB_USER', 'root');
 define('DB_PASS', '');
 define('DB_NAME', 'event_ticketing_db');
```
### Python Scripts

Python scripts handle UUID generation and ticket validation. Ensure Python 3 is installed and the `mysql-connector-python` package is available:

```bash
pip install mysql-connector-python
```

The Python scripts connect to MySQL using:

```python
 DB_CONFIG = {
  'host': 'localhost',
  'port': 3307,
  'user': 'root',
  'password': '',
  'database': 'event_ticketing_db'
 }
```
----------

## Accessing the System

### Admin Portal

```text
 URL:  http://localhost/event-ticketing-v2/
 User: admin
 Pass: admin123
```

### Audience Registration Portal

```text
URL: http://localhost/event-ticketing-v2/online-reg.php
(No login required — accessible to the public)
```
### Ticket Lookup (Public)

```text
URL: http://localhost/event-ticketing-v2/ticket-lookup.php?code={TICKET_CODE}
```

### phpMyAdmin

```text
 URL: http://localhost:3307/phpmyadmin
 User: root
 Pass: (leave blank)
```
----------

## Color Theme

The system uses a **60-30-10** color distribution for visual hierarchy:

|Percentage|Color|Hex Code|Usage|
|-----|-----|-----|-----|
|**60%**|Off-white/Warm gray|`#fdfbfc`, `#f8f5f6`|Page backgrounds, card surfaces|
|**30%**|Deep crimson|`#7e1416`|Headers, sidebar, primary buttons|
|**10%**|Golden yellow|`#f9be1b`|Accents, highlights, active states, progress bars|
|**Support**|Neutral grays|`#e5e5e5` → `#171717`|Borders, text hierarchy, contrast|

----------

## License

This project is developed for the **University of Perpetual Help System DALTA — Molino Campus**. All rights reserved.