<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$basePath = dirname($_SERVER['SCRIPT_NAME']);
$currentDate = date('Y-m-d');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : '';
$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'date';
$showFilter = isset($_GET['show']) ? trim($_GET['show']) : 'ticketed';

$eventsQuery = "
    SELECT e.*, 
           v.venue_name, v.building,
           o.org_name,
           COALESCE(MIN(tc.price), 0) as min_price,
           COALESCE(MAX(tc.price), 0) as max_price,
           COALESCE(SUM(tc.total_slots), 0) as total_slots,
           COALESCE(SUM(tc.slots_remaining), 0) as total_remaining,
           COUNT(tc.category_id) as category_count
    FROM Event e
    JOIN Venue v ON e.venue_id = v.venue_id
    JOIN Organization o ON e.org_id = o.org_id
    LEFT JOIN Ticket_Category tc ON e.event_id = tc.event_id
    WHERE e.event_date >= '$currentDate'
    AND e.status = 'upcoming'
";

if ($showFilter === 'ticketed') {
    $eventsQuery .= " AND e.requires_ticket = 1";
} elseif ($showFilter === 'unticketed') {
    $eventsQuery .= " AND e.requires_ticket = 0";
}

if (!empty($search)) {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $eventsQuery .= " AND (e.event_name LIKE '%$searchEsc%' OR e.description LIKE '%$searchEsc%' OR o.org_name LIKE '%$searchEsc%')";
}

if (!empty($typeFilter)) {
    $typeEsc = mysqli_real_escape_string($conn, $typeFilter);
    $eventsQuery .= " AND e.event_type = '$typeEsc'";
}

$eventsQuery .= " GROUP BY e.event_id";

switch ($sortBy) {
    case 'name': $eventsQuery .= " ORDER BY e.event_name ASC"; break;
    case 'price_asc': $eventsQuery .= " ORDER BY min_price ASC"; break;
    case 'price_desc': $eventsQuery .= " ORDER BY min_price DESC"; break;
    default: $eventsQuery .= " ORDER BY e.event_date ASC"; break;
}

$events = mysqli_query($conn, $eventsQuery);
$totalEvents = $events ? mysqli_num_rows($events) : 0;

$countTicketed = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM Event WHERE event_date >= '$currentDate' AND status = 'upcoming' AND requires_ticket = 1"
))['total'];

$countUnticketed = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM Event WHERE event_date >= '$currentDate' AND status = 'upcoming' AND requires_ticket = 0"
))['total'];

$countAll = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM Event WHERE event_date >= '$currentDate' AND status = 'upcoming'"
))['total'];

if ($showFilter === 'ticketed') {
    $pageTitle = 'EVENTS OPEN FOR REGISTRATION';
} elseif ($showFilter === 'unticketed') {
    $pageTitle = 'Free Entry Events';
} else {
    $pageTitle = 'All Upcoming Events';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration — UPHSD Molino</title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/user-reg.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Manrope:wght@200..800&family=Michroma&display=swap" rel="stylesheet">
    <style>
        /* Full-width welcome section with background image */
        .welcome-section {
            width: 100vw;
            margin-left: calc(-50vw + 50%);
            margin-right: calc(-50vw + 50%);
            padding: 0;
            position: relative;
            background: linear-gradient(rgba(0,0,0,0.65), rgba(0,0,0,0.55)), 
                        url('<?php echo $basePath; ?>/MOLINO-Campus-Facade-2.2.jpg') center/cover no-repeat;
            min-height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .welcome-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            text-align: center;
            color: white;
            width: 100%;
        }
        
        .welcome-content .logo-img {
            width: 80px;
            height: auto;
            margin-bottom: 16px;
        }
        
        .welcome-content h2 {
            font-family: 'Inter', serif;
            font-size: 28px;
            margin: 0 0 10px;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .welcome-content .subtitle {
            font-size: 15px;
            opacity: 0.9;
            margin: 0 0 8px;
            color: #f9be1b;
            font-weight: 500;
        }
        
        .welcome-content p {
            font-size: 14px;
            max-width: 650px;
            margin: 0 auto 20px;
            line-height: 1.6;
            color: rgba(255,255,255,0.85);
        }
        
        .welcome-highlights {
            display: flex;
            justify-content: center;
            gap: 32px;
            flex-wrap: wrap;
        }
        
        .highlight-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            background: rgba(255,255,255,0.1);
            padding: 8px 18px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .highlight-icon {
            font-size: 18px;
        }
        
        .filter-count { font-size: 11px; opacity: 0.8; font-weight: 500; }
        
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center; }
        .modal-overlay.show { display:flex; }
        .lookup-card { background:#fdfbfc; border:1px solid #e5e5e5; border-radius:6px; padding:24px; width:90%; max-width:420px; box-shadow:0 10px 25px rgba(0,0,0,0.15); }
        .lookup-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
        .lookup-header h3 { margin:0; font-size:16px; color:#7e1416; font-family:'Anton',serif; }
        .lookup-close { background:none; border:none; font-size:22px; cursor:pointer; color:#737373; padding:0 6px; line-height:1; }
        .lookup-close:hover { color:#7e1416; }
        .lookup-input { width:100%; padding:10px 12px; border:1px solid #e5e5e5; border-radius:4px; font-size:13px; box-sizing:border-box; }
        .lookup-input:focus { outline:none; border-color:#7e1416; box-shadow:0 0 0 2px rgba(126,20,22,0.08); }
        
        @media (max-width:768px) {
            .welcome-section { min-height: 260px; }
            .welcome-content { padding: 30px 16px; }
            .welcome-content h2 { font-size: 22px; }
            .welcome-highlights { gap: 10px; }
            .highlight-item { padding: 6px 14px; font-size: 12px; }
        }
    </style>
</head>
<body class="user-portal">

    <header class="user-header">
        <div class="header-content">
            <div class="header-brand">
                <img src="<?php echo $basePath; ?>/uphsd-logo.png" alt="UPHSD Logo" style="width:40px;height:auto;">
                <div>
                    <h1>UNIVERSITY OF PERPETUAL HELP SYSTEM DALTA</h1>
                    <p>Molino Campus — Event Registration Portal</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="<?php echo $basePath; ?>/" class="btn-outline-light">⚙️ Admin Portal</a>
            </div>
        </div>
    </header>

    <!-- ===== FULL-WIDTH WELCOME SECTION ===== -->
    <section class="welcome-section">
        <div class="welcome-content">
            <img src="<?php echo $basePath; ?>/uphsd-logo.png" alt="UPHSD Logo" class="logo-img">
            <h2>Welcome, Perpetualites!</h2>
            <p>Browse and register for upcoming events. Select an event below to secure your spot and receive your digital ticket.</p>
        </div>
    </section>

    <div class="user-container">

        <section class="search-section">
            <form method="GET" action="<?php echo $basePath; ?>/online-reg.php" class="search-form">
                <input type="text" name="search" placeholder="Search events by name or keyword..." 
                       value="<?php echo h($search); ?>" class="search-input">
                <select name="type" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Event Types</option>
                    <option value="academic" <?php echo $typeFilter==='academic'?'selected':''; ?>>📚 Academic</option>
                    <option value="cultural" <?php echo $typeFilter==='cultural'?'selected':''; ?>>🎭 Cultural</option>
                    <option value="sports" <?php echo $typeFilter==='sports'?'selected':''; ?>>⚽ Sports</option>
                    <option value="concert" <?php echo $typeFilter==='concert'?'selected':''; ?>>🎵 Concert</option>
                    <option value="seminar" <?php echo $typeFilter==='seminar'?'selected':''; ?>>📝 Seminar</option>
                    <option value="graduation" <?php echo $typeFilter==='graduation'?'selected':''; ?>>🎓 Graduation</option>
                    <option value="orientation" <?php echo $typeFilter==='orientation'?'selected':''; ?>>📋 Orientation</option>
                    <option value="other" <?php echo $typeFilter==='other'?'selected':''; ?>>📌 Other</option>
                </select>
                <input type="hidden" name="show" value="<?php echo $showFilter; ?>">
                <button type="submit" class="btn-search">Search</button>
                <?php if (!empty($search) || !empty($typeFilter)): ?>
                    <a href="<?php echo $basePath; ?>/online-reg.php?show=<?php echo $showFilter; ?>" class="btn-reset">Clear</a>
                <?php endif; ?>
            </form>
        </section>

        <div class="filter-toggle-bar">
            <a href="<?php echo $basePath; ?>/online-reg.php?show=ticketed" 
               class="filter-toggle-btn <?php echo $showFilter==='ticketed'?'active':''; ?>">
                🎟️ Ticketed Events <span class="filter-count">(<?php echo $countTicketed; ?>)</span>
            </a>
            <a href="<?php echo $basePath; ?>/online-reg.php?show=unticketed" 
               class="filter-toggle-btn <?php echo $showFilter==='unticketed'?'active':''; ?>">
                🎉 Free Entry <span class="filter-count">(<?php echo $countUnticketed; ?>)</span>
            </a>
            <a href="<?php echo $basePath; ?>/online-reg.php?show=all" 
               class="filter-toggle-btn <?php echo $showFilter==='all'?'active':''; ?>">
                📋 All Events <span class="filter-count">(<?php echo $countAll; ?>)</span>
            </a>
        </div>

        <section class="events-section">
            <?php if ($events && $totalEvents > 0): ?>
                <div class="events-header">
                    <h2><?php echo $pageTitle; ?></h2>
                    <span class="events-count"><?php echo $totalEvents; ?> event<?php echo $totalEvents!==1?'s':''; ?></span>
                </div>
                <div class="events-grid">
                    <?php while ($event = mysqli_fetch_assoc($events)): 
                        $slotsAvailable = $event['total_remaining'] ?? 0;
                        $slotsTotal = $event['total_slots'] ?? 0;
                        $categoryCount = $event['category_count'] ?? 0;
                        $requiresTicket = $event['requires_ticket'] ?? 1;
                        $percentFilled = $slotsTotal > 0 ? round((($slotsTotal - $slotsAvailable) / $slotsTotal) * 100) : 0;
                        $hasCategories = $categoryCount > 0;
                        $hasSlots = $slotsAvailable > 0;
                        $isAlmostFull = $percentFilled >= 80 && $hasSlots;
                        
                        $dateLabel = '';
                        if ($event['event_date'] === date('Y-m-d')) $dateLabel = 'Today';
                        elseif ($event['event_date'] === date('Y-m-d', strtotime('+1 day'))) $dateLabel = 'Tomorrow';
                        
                        if (!$hasCategories && $requiresTicket) {
                            $priceDisplay = '<span class="price-tba">TBA</span>';
                        } elseif ($event['min_price'] == 0 && $event['max_price'] == 0) {
                            $priceDisplay = '<span class="price-free">FREE</span>';
                        } elseif ($event['min_price'] == $event['max_price']) {
                            $priceDisplay = '<span class="price-value">₱' . number_format($event['min_price'], 2) . '</span>';
                        } else {
                            $priceDisplay = '<span class="price-range">₱' . number_format($event['min_price'], 2) . ' - ₱' . number_format($event['max_price'], 2) . '</span>';
                        }
                        
                        if (!$requiresTicket) {
                            $buttonHtml = '<span class="btn-free-entry">🎉 Free Entry — No Ticket Needed</span>';
                        } elseif (!$hasCategories) {
                            $buttonHtml = '<span class="btn-unavailable">No Tickets Yet</span>';
                        } elseif (!$hasSlots) {
                            $buttonHtml = '<span class="btn-unavailable">Sold Out</span>';
                        } else {
                            $buttonHtml = '<a href="' . $basePath . '/online-register.php?event_id=' . $event['event_id'] . '" class="btn-register">Register Now →</a>';
                        }
                    ?>
                    <div class="event-card">
                        <div class="event-card-header">
                            <div class="event-badges-row">
                                <span class="event-type-badge badge-<?php echo $event['event_type']; ?>">
                                    <?php echo ucfirst($event['event_type']); ?>
                                </span>
                                <?php if (!$requiresTicket): ?>
                                    <span class="badge-free-entry">Free Entry</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($dateLabel): ?>
                                <span class="event-date-badge"><?php echo $dateLabel; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="event-card-body">
                            <h3 class="event-title"><?php echo h($event['event_name']); ?></h3>
                            <div class="event-meta">
                                <div class="meta-item"><span class="meta-icon">📅</span><?php echo date('M d, Y', strtotime($event['event_date'])); ?></div>
                                <div class="meta-item"><span class="meta-icon">🕐</span><?php echo date('g:i A', strtotime($event['start_time'])); ?> - <?php echo date('g:i A', strtotime($event['end_time'])); ?></div>
                                <div class="meta-item"><span class="meta-icon">📍</span><?php echo h($event['venue_name']); ?></div>
                                <div class="meta-item"><span class="meta-icon">👤</span><?php echo str_replace('_', ' ', ucfirst($event['audience_type'])); ?></div>
                            </div>
                            <?php if (!empty($event['description'])): ?>
                                <p class="event-description">
                                    <?php echo h(substr($event['description'], 0, 150)); ?>
                                    <?php echo strlen($event['description']) > 150 ? '...' : ''; ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($requiresTicket && $hasCategories): ?>
                            <div class="slot-indicator">
                                <div class="slot-progress-bar">
                                    <div class="slot-fill <?php echo $isAlmostFull?'warning':''; ?> <?php echo !$hasSlots?'sold-out':''; ?>" 
                                         style="width:<?php echo $percentFilled; ?>%"></div>
                                </div>
                                <div class="slot-text">
                                    <?php if (!$hasSlots): ?>
                                        <span class="slots-urgent">All <?php echo $slotsTotal; ?> slots taken</span>
                                    <?php elseif ($slotsAvailable <= 5): ?>
                                        <span class="slots-urgent">⚠ Only <?php echo $slotsAvailable; ?> left!</span>
                                    <?php elseif ($isAlmostFull): ?>
                                        <span class="slots-warning">Filling fast! <?php echo $slotsAvailable; ?> remaining</span>
                                    <?php else: ?>
                                        <span class="slots-available"><?php echo $slotsAvailable; ?> of <?php echo $slotsTotal; ?> available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="event-footer">
                                <div><?php echo $priceDisplay; ?></div>
                                <?php echo $buttonHtml; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-events">
                    <div class="no-events-icon">📭</div>
                    <h3>No Events Found</h3>
                    <p>Try adjusting your filters or check back later for new events.</p>
                </div>
            <?php endif; ?>
        </section>

        <footer class="user-footer">
            <p>University of Perpetual Help System DALTA — Molino Campus</p>
            <p class="footer-sub">© <?php echo date('Y'); ?> Event Management & Ticketing System. All rights reserved.</p>
        </footer>
    </div>

    <!-- Quick Ticket Lookup FAB -->
    <div class="quick-lookup-fab" onclick="openLookup()" title="Quick Ticket Lookup">🎟️</div>
    
    <div id="lookup-modal" class="modal-overlay" onclick="if(event.target===this)closeLookup()">
        <div class="lookup-card">
            <div class="lookup-header">
                <h3>QUICK TICKET LOOKUP</h3>
                <button class="lookup-close" onclick="closeLookup()">&times;</button>
            </div>
            <p style="font-size:13px;color:#525252;margin-bottom:12px;">Enter your ticket code to view your ticket and QR code.</p>
            <form id="lookup-form" onsubmit="lookupTicket(event)">
                <input type="text" id="lookup-code" placeholder="Paste your ticket code here..." class="lookup-input" required>
                <button type="submit" class="btn-primary" style="width:100%;margin-top:10px;">View Ticket</button>
            </form>
            <div id="lookup-error" style="display:none;margin-top:10px;color:#991b1b;font-size:12px;"></div>
        </div>
    </div>

    <script>
        function openLookup() { 
            document.getElementById('lookup-modal').classList.add('show');
            document.getElementById('lookup-code').focus();
            document.getElementById('lookup-error').style.display = 'none';
        }
        function closeLookup() { 
            document.getElementById('lookup-modal').classList.remove('show');
        }
        
        function lookupTicket(e) {
            e.preventDefault();
            var code = document.getElementById('lookup-code').value.trim();
            var errorDiv = document.getElementById('lookup-error');
            
            if (!code) {
                errorDiv.textContent = 'Please enter a ticket code.';
                errorDiv.style.display = 'block';
                return;
            }
            
            window.location.href = '<?php echo $basePath; ?>/ticket-lookup.php?code=' + encodeURIComponent(code);
        }
        
        document.addEventListener('keydown', function(e) { 
            if(e.key==='Escape') closeLookup(); 
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.event-card').forEach(function(c, i) {
                c.style.opacity = '0';
                c.style.transform = 'translateY(20px)';
                c.style.transition = 'opacity 0.4s ease ' + (i*0.06) + 's, transform 0.4s ease ' + (i*0.06) + 's';
                requestAnimationFrame(function() { c.style.opacity = '1'; c.style.transform = 'translateY(0)'; });
            });
        });
    </script>
</body>
</html>