<?php
require_once __DIR__ . '/../../includes/header.php';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];
    
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM Ticket WHERE category_id = $category_id");
    $count = mysqli_fetch_assoc($check)['count'];
    
    if ($count > 0) {
        $_SESSION['error'] = "Cannot delete category: It has associated tickets.";
    } else {
        $sql = "DELETE FROM Ticket_Category WHERE category_id = $category_id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Category deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting category: " . mysqli_error($conn);
        }
    }
    redirect('/event-ticketing-v2/modules/categories/');
}

// Filter by event

$event_filter = get('event_id');
$sort_field = get('sort_field', 'price');
$sort_order = get('sort_order', 'asc');

$query = "SELECT tc.*, e.event_name 
          FROM Ticket_Category tc
          JOIN Event e ON tc.event_id = e.event_id
          WHERE 1=1";

if ($event_filter) {
    $event_filter = (int)$event_filter;
    $query .= " AND tc.event_id = $event_filter";
}

$allowed_fields = ['price', 'total_slots', 'slots_remaining'];
$allowed_orders = ['asc', 'desc'];
$field = in_array($sort_field, $allowed_fields) ? $sort_field : 'price';
$order = in_array($sort_order, $allowed_orders) ? $sort_order : 'asc';
$query .= " ORDER BY tc.$field $order, tc.category_name ASC";

$categories = mysqli_query($conn, $query);

// Get events for filter dropdown
$events = mysqli_query($conn, "SELECT event_id, event_name FROM Event ORDER BY event_date DESC");
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Ticket Categories</div>
            <div class="page-sub">Manage audience-based ticket tiers per event</div>
        </div>
        <a href="/event-ticketing-v2/modules/categories/create.php" class="btn btn-primary">+ Add Category</a>
    </div>

    <?php echo displayMessage(); ?>


    <div class="search-row">
        <form method="GET" id="filter-form" style="display:flex;gap:8px;width:100%">
            <select name="event_id" style="width:180px">
                <option value="">All Events</option>
                <?php 
                mysqli_data_seek($events, 0);
                while ($event = mysqli_fetch_assoc($events)): ?>
                    <option value="<?php echo $event['event_id']; ?>" <?php echo $event_filter == $event['event_id'] ? 'selected' : ''; ?>>
                        <?php echo h($event['event_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="sort_field" style="width:140px">
                <option value="price" <?php if ($sort_field == 'price') echo 'selected'; ?>>Price</option>
                <option value="total_slots" <?php if ($sort_field == 'total_slots') echo 'selected'; ?>>Total Slots</option>
                <option value="slots_remaining" <?php if ($sort_field == 'slots_remaining') echo 'selected'; ?>>Remaining</option>
            </select>
            <select name="sort_order" style="width:120px">
                <option value="asc" <?php if ($sort_order == 'asc') echo 'selected'; ?>>Ascending</option>
                <option value="desc" <?php if ($sort_order == 'desc') echo 'selected'; ?>>Descending</option>
            </select>
            <button type="submit" class="btn">Filter</button>
            <?php if ($event_filter): ?>
                <a href="/event-ticketing-v2/modules/categories/" class="btn">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-flush">
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Category Name</th>
                        <th>Eligible</th>
                        <th>Price</th>
                        <th>Total Slots</th>
                        <th>Remaining</th>
                        <th>Sold</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($categories) > 0): ?>
                        <?php while ($cat = mysqli_fetch_assoc($categories)): 
                            $sold = $cat['total_slots'] - $cat['slots_remaining'];
                            $pct = $cat['total_slots'] > 0 ? round(($sold / $cat['total_slots']) * 100) : 0;
                        ?>
                            <tr>
                                <td style="font-size:12px"><?php echo h($cat['event_name']); ?></td>
                                <td style="font-weight:500"><?php echo h($cat['category_name']); ?></td>
                                <td><span class="badge <?php echo getAttendeeTypeBadge($cat['eligible_type']); ?>"><?php echo h($cat['eligible_type']); ?></span></td>
                                <td><?php echo $cat['price'] > 0 ? '₱' . number_format($cat['price'], 2) : '<span class="badge b-green">Free</span>'; ?></td>
                                <td><?php echo $cat['total_slots']; ?></td>
                                <td><?php echo $cat['slots_remaining']; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px">
                                        <div style="width:60px;height:8px;background:var(--color-background-secondary);border-radius:4px;overflow:hidden">
                                            <div style="width:<?php echo $pct; ?>%;height:100%;background:#378ADD;border-radius:4px"></div>
                                        </div>
                                        <span style="font-size:11px"><?php echo $sold; ?></span>
                                    </div>
                                </td>
                                <td class="actions">
                                    <a href="/event-ticketing-v2/modules/categories/edit.php?id=<?php echo $cat['category_id']; ?>" class="btn btn-sm">Edit</a>
                                    <a href="/event-ticketing-v2/modules/categories/?delete=<?php echo $cat['category_id']; ?>&event_id=<?php echo $event_filter; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this category?')">Del</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--color-text-secondary)">No categories found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>