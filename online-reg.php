<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$basePath = dirname($_SERVER['SCRIPT_NAME']);
$currentDate = date('Y-m-d');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : '';
$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'date';
$showAll = isset($_GET['show']) ? trim($_GET['show']) : '';

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

if ($showAll !== '1') {
    $eventsQuery .= " AND e.requires_ticket = 1";
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

$countResult = mysqli_query($conn, "SELECT COUNT(*) as total FROM Event WHERE event_date >= '$currentDate' AND status = 'upcoming'");
$totalAllUpcoming = $countResult ? mysqli_fetch_assoc($countResult)['total'] : 0;

$countTicketedResult = mysqli_query($conn, "SELECT COUNT(*) as total FROM Event WHERE event_date >= '$currentDate' AND status = 'upcoming' AND requires_ticket = 1");
$totalTicketed = $countTicketedResult ? mysqli_fetch_assoc($countTicketedResult)['total'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration — University Events</title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/user-reg.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="user-portal">

    <!-- ===== HEADER (Full Width) ===== -->
    <header class="user-header">
        <div class="header-content">
            <div class="header-brand">
                <div class="brand-icon">🎓</div>
                <div>
                    <h1>University Events</h1>
                    <p>Browse and register for upcoming university events</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="<?php echo $basePath; ?>/modules/tickets/validate.php" class="btn-outline-light">🎟️ Validate Ticket</a>
                <a href="<?php echo $basePath; ?>/" class="btn-outline-light">⚙️ Admin Portal</a>
            </div>
        </div>
    </header>

    <div class="user-container">

        <!-- ===== STATS BAR ===== -->
        <section class="stats-bar">
            <div class="stat-item">
                <span class="stat-number"><?php echo $totalAllUpcoming; ?></span>
                <span class="stat-label">Total Upcoming</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $totalTicketed; ?></span>
                <span class="stat-label">Require Tickets</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">✓</span>
                <span class="stat-label">Digital System</span>
            </div>
        </section>

        <!-- ===== SEARCH & FILTER ===== -->
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
                <?php if ($showAll === '1'): ?><input type="hidden" name="show" value="1"><?php endif; ?>
                <button type="submit" class="btn-search">Search</button>
                <?php if (!empty($search) || !empty($typeFilter)): ?>
                    <a href="<?php echo $basePath; ?>/online-reg.php<?php echo $showAll==='1'?'?show=1':''; ?>" class="btn-reset">Clear</a>
                <?php endif; ?>
            </form>
        </section>

        <!-- ===== FILTER TOGGLE ===== -->
        <div class="filter-toggle-bar">
            <a href="<?php echo $basePath; ?>/online-reg.php" class="filter-toggle-btn <?php echo $showAll!=='1'?'active':''; ?>">
                🎟️ Ticketed Events
            </a>
            <a href="<?php echo $basePath; ?>/online-reg.php?show=1" class="filter-toggle-btn <?php echo $showAll==='1'?'active':''; ?>">
                📋 All Events
            </a>
        </div>

        <!-- ===== EVENTS GRID ===== -->
        <section class="events-section">
            <?php if ($events && $totalEvents > 0): ?>
                <div class="events-header">
                    <h2><?php echo $showAll==='1'?'All Upcoming Events':'Events Open for Registration'; ?></h2>
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
                    <h3 style="color:var(--user-primary);font-size:1.125rem;margin:0 0 0.5rem;">No Events Found</h3>
                    <p style="color:var(--user-text-muted);">Try adjusting your filters or check back later.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- ===== FOOTER ===== -->
        <footer class="user-footer">
            <p>University Event Management & Ticketing System</p>
            <p class="footer-sub">© <?php echo date('Y'); ?> University Events. All rights reserved.</p>
        </footer>
    </div>

    <!-- ===== QUICK LOOKUP FAB ===== -->
    <div class="quick-lookup-fab" onclick="openLookup()" title="Quick Ticket Lookup">🎟️</div>
    
    <div id="lookup-modal" class="modal-overlay" onclick="if(event.target===this)closeLookup()">
        <div class="lookup-card">
            <div class="lookup-header">
                <h3>Quick Ticket Lookup</h3>
                <button class="lookup-close" onclick="closeLookup()">&times;</button>
            </div>
            <form action="<?php echo $basePath; ?>/modules/tickets/validate.php" method="GET">
                <input type="text" name="code" placeholder="Paste your ticket code here..." class="lookup-input" required>
                <button type="submit" class="btn-primary" style="width:100%;margin-top:10px;">Lookup Ticket</button>
            </form>
        </div>
    </div>

    <style>
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center; }
        .modal-overlay.show { display:flex; }
        .lookup-card { background:var(--user-card-bg); border:1px solid var(--user-border); border-radius:var(--user-radius); padding:1.5rem; width:90%; max-width:400px; box-shadow:0 10px 25px rgba(0,0,0,0.15); }
        .lookup-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
        .lookup-header h3 { margin:0; font-size:1rem; color:var(--user-primary); }
        .lookup-close { background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--user-text-muted); padding:0 0.25rem; line-height:1; }
        .lookup-close:hover { color:var(--user-primary); }
        .lookup-input { width:100%; padding:0.625rem 0.75rem; border:1px solid var(--user-border); border-radius:var(--user-radius-xs); font-size:0.8125rem; box-sizing:border-box; }
        .lookup-input:focus { outline:none; border-color:var(--user-primary); box-shadow:0 0 0 2px rgba(126,20,22,0.08); }
    </style>

    <script>
        function openLookup() { document.getElementById('lookup-modal').classList.add('show'); }
        function closeLookup() { document.getElementById('lookup-modal').classList.remove('show'); }
        document.addEventListener('keydown', function(e) { if(e.key==='Escape') closeLookup(); });
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