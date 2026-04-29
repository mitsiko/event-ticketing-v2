<?php
require_once __DIR__ . '/../../includes/header.php';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $org_id = (int)$_GET['delete'];
    
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM Event WHERE org_id = $org_id");
    $count = mysqli_fetch_assoc($check)['count'];
    
    if ($count > 0) {
        $_SESSION['error'] = "Cannot delete organization: It has associated events.";
    } else {
        $sql = "DELETE FROM Organization WHERE org_id = $org_id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Organization deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting organization: " . mysqli_error($conn);
        }
    }
    redirect('/event-ticketing-v2/modules/organizations/');
}

$organizations = mysqli_query($conn, "SELECT * FROM Organization ORDER BY org_name");
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Organizations</div>
            <div class="page-sub">Event organizers and hosting bodies</div>
        </div>
        <a href="/event-ticketing-v2/modules/organizations/create.php" class="btn btn-primary">+ Add Organization</a>
    </div>

    <?php echo displayMessage(); ?>

    <div class="card-flush">
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Adviser</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Accredited</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($organizations) > 0): ?>
                        <?php while ($org = mysqli_fetch_assoc($organizations)): ?>
                            <tr>
                                <td style="font-weight:500"><?php echo h($org['org_name']); ?></td>
                                <td><span class="badge b-gray"><?php echo h(str_replace('_', ' ', $org['org_type'])); ?></span></td>
                                <td style="font-size:12px">
                                    <?php 
                                    if ($org['adviser_first_name'] && $org['adviser_last_name']) {
                                        echo h($org['adviser_first_name'] . ' ' . $org['adviser_last_name']);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td style="font-size:12px"><?php echo h($org['contact_email']); ?></td>
                                <td style="font-size:12px"><?php echo h($org['contact_phone'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge <?php echo $org['is_accredited'] ? 'b-green' : 'b-red'; ?>">
                                        <?php echo $org['is_accredited'] ? 'Yes' : 'No'; ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="/event-ticketing-v2/modules/organizations/edit.php?id=<?php echo $org['org_id']; ?>" class="btn btn-sm">Edit</a>
                                    <a href="/event-ticketing-v2/modules/organizations/?delete=<?php echo $org['org_id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this organization?')">Del</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--color-text-secondary)">No organizations found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>