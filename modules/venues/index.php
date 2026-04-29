<?php
require_once __DIR__ . '/../../includes/header.php';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $venue_id = (int)$_GET['delete'];
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM Event WHERE venue_id = $venue_id");
    $count = mysqli_fetch_assoc($check)['count'];
    
    if ($count > 0) {
        $_SESSION['error'] = "Cannot delete venue: It has associated events.";
    } else {
        $sql = "DELETE FROM Venue WHERE venue_id = $venue_id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Venue deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting venue: " . mysqli_error($conn);
        }
    }
    redirect('/event-ticketing-v2/modules/venues/');
}

$venues = mysqli_query($conn, "SELECT * FROM Venue ORDER BY venue_name");
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Venues</div>
            <div class="page-sub">University facilities for hosting events</div>
        </div>
        <a href="/event-ticketing-v2/modules/venues/create.php" class="btn btn-primary">+ Add Venue</a>
    </div>

    <?php echo displayMessage(); ?>

    <div class="card-flush">
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Building</th>
                        <th>Floor</th>
                        <th>Capacity</th>
                        <th>AV</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($venues) > 0): ?>
                        <?php while ($venue = mysqli_fetch_assoc($venues)): ?>
                            <tr>
                                <td style="font-weight:500"><?php echo h($venue['venue_name']); ?></td>
                                <td><span class="badge b-gray"><?php echo h(ucfirst($venue['venue_type'])); ?></span></td>
                                <td><?php echo h($venue['building'] ?? '-'); ?></td>
                                <td><?php echo h($venue['floor_level'] ?? '-'); ?></td>
                                <td><?php echo number_format($venue['capacity']); ?></td>
                                <td>
                                    <span class="badge <?php echo $venue['has_av_system'] ? 'b-green' : 'b-gray'; ?>">
                                        <?php echo $venue['has_av_system'] ? 'Yes' : 'No'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions" style="display:flex;gap:4px;flex-wrap:wrap;">
                                        <button class="btn btn-sm" 
                                                onclick="openEditModal('/event-ticketing-v2/modules/venues/edit.php?id=<?php echo $venue['venue_id']; ?>', 'Edit Venue')">
                                            Edit
                                        </button>
                                        <a href="/event-ticketing-v2/modules/venues/?delete=<?php echo $venue['venue_id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this venue?')">Del</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--color-text-secondary)">No venues found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>