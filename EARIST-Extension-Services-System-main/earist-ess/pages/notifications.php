<?php
// pages/notifications.php
// Comprehensive Notifications Management Page

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $user_id = $_SESSION['user_id'];
        
        // Mark notification as read/unread
        if (isset($_POST['toggle_notification_status'])) {
            $notification_id = (int)$_POST['notification_id'];
            $is_read = (int)$_POST['is_read'];
            
            $result = $db->query(
                "UPDATE notifications SET is_read = ? WHERE notification_id = ? AND user_id = ?", 
                [$is_read, $notification_id, $user_id]
            );
            
            echo json_encode(['success' => true, 'message' => $is_read ? 'Marked as read' : 'Marked as unread']);
            exit();
        }
        
        // Delete notification
        if (isset($_POST['delete_notification'])) {
            $notification_id = (int)$_POST['notification_id'];
            
            $result = $db->query(
                "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?", 
                [$notification_id, $user_id]
            );
            
            echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
            exit();
        }
        
        // Mark all as read
        if (isset($_POST['mark_all_read'])) {
            $result = $db->query(
                "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", 
                [$user_id]
            );
            
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
            exit();
        }
        
        // Delete all read notifications
        if (isset($_POST['delete_all_read'])) {
            $result = $db->query(
                "DELETE FROM notifications WHERE user_id = ? AND is_read = 1", 
                [$user_id]
            );
            
            echo json_encode(['success' => true, 'message' => 'All read notifications deleted']);
            exit();
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all'; // all, unread, read
$search = $_GET['search'] ?? '';
$page_num = (int)($_GET['p'] ?? 1);
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

// Build query conditions
$where_conditions = ["user_id = ?"];
$params = [$_SESSION['user_id']];

if ($filter === 'unread') {
    $where_conditions[] = "is_read = 0";
} elseif ($filter === 'read') {
    $where_conditions[] = "is_read = 1";
}

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR message LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$total_notifications = $db->fetch(
    "SELECT COUNT(*) as count FROM notifications WHERE {$where_clause}", 
    $params
)['count'];

// Get notifications with pagination
$notifications = $db->fetchAll(
    "SELECT * FROM notifications 
     WHERE {$where_clause} 
     ORDER BY created_at DESC 
     LIMIT ? OFFSET ?", 
    array_merge($params, [$per_page, $offset])
);

// Get counts for filter badges
$unread_count = $db->fetch(
    "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", 
    [$_SESSION['user_id']]
)['count'];

$read_count = $db->fetch(
    "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 1", 
    [$_SESSION['user_id']]
)['count'];

$total_count = $unread_count + $read_count;
$total_pages = ceil($total_notifications / $per_page);
?>

<style>
.notification-filters {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    margin-bottom: 25px;
}

.filter-btn {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 8px 16px;
    border-radius: 20px;
    text-decoration: none;
    margin-right: 10px;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.filter-btn:hover, .filter-btn.active {
    background: var(--earist-red);
    color: white;
    border-color: var(--earist-red);
    text-decoration: none;
}

.filter-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

.notification-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    margin-bottom: 15px;
    transition: all 0.3s;
    border-left: 4px solid transparent;
}

.notification-card.unread {
    border-left-color: var(--earist-red);
    background: linear-gradient(90deg, rgba(167, 0, 14, 0.02) 0%, white 2%);
}

.notification-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.notification-content {
    padding: 20px;
}

.notification-header {
    display: flex;
    justify-content: between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.notification-title {
    font-weight: 600;
    color: var(--earist-dark);
    margin-bottom: 8px;
    flex: 1;
}

.notification-actions {
    display: flex;
    gap: 8px;
    margin-left: 15px;
}

.action-btn {
    background: none;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 6px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 12px;
}

.action-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.action-btn.read {
    color: #059669;
    border-color: #10b981;
}

.action-btn.delete {
    color: #dc2626;
    border-color: #ef4444;
}

.action-btn.delete:hover {
    background: #fee2e2;
}

.notification-message {
    color: #64748b;
    line-height: 1.5;
    margin-bottom: 12px;
}

.notification-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #94a3b8;
}

.notification-type {
    background: #f1f5f9;
    color: #475569;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.bulk-actions {
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.search-box {
    max-width: 300px;
    flex: 1;
}

.search-box input {
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 8px 16px;
}

.bulk-action-btn {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 13px;
}

.bulk-action-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.pagination-wrapper {
    display: flex;
    justify-content: center;
    margin-top: 30px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 32px;
    color: #cbd5e1;
}

@media (max-width: 768px) {
    .notification-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .notification-actions {
        margin-left: 0;
        justify-content: flex-start;
    }
    
    .bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-box {
        max-width: none;
    }
}
</style>

<div class="notifications-page">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">All Notifications</h4>
            <p class="text-muted mb-0">Manage your notifications and stay updated</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-danger"><?= $unread_count ?> Unread</span>
            <span class="badge bg-secondary"><?= $total_count ?> Total</span>
        </div>
    </div>

    <!-- Filters -->
    <div class="notification-filters">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <div class="filter-buttons">
                <a href="?page=notifications&filter=all&search=<?= urlencode($search) ?>" 
                   class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> All
                    <span class="filter-badge"><?= $total_count ?></span>
                </a>
                <a href="?page=notifications&filter=unread&search=<?= urlencode($search) ?>" 
                   class="filter-btn <?= $filter === 'unread' ? 'active' : '' ?>">
                    <i class="fas fa-exclamation-circle"></i> Unread
                    <span class="filter-badge"><?= $unread_count ?></span>
                </a>
                <a href="?page=notifications&filter=read&search=<?= urlencode($search) ?>" 
                   class="filter-btn <?= $filter === 'read' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> Read
                    <span class="filter-badge"><?= $read_count ?></span>
                </a>
            </div>
        </div>
    </div>

    <!-- Bulk Actions & Search -->
    <div class="bulk-actions">
        <div class="search-box">
            <form method="GET" class="d-flex">
                <input type="hidden" name="page" value="notifications">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search notifications..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        
        <div class="bulk-actions-buttons">
            <?php if ($unread_count > 0): ?>
                <button class="bulk-action-btn" onclick="markAllAsRead()">
                    <i class="fas fa-check-double"></i> Mark All Read
                </button>
            <?php endif; ?>
            <?php if ($read_count > 0): ?>
                <button class="bulk-action-btn text-danger" onclick="deleteAllRead()">
                    <i class="fas fa-trash"></i> Delete All Read
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="notifications-list">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h5>No notifications found</h5>
                <p class="text-muted">
                    <?php if (!empty($search)): ?>
                        No notifications match your search criteria.
                    <?php elseif ($filter === 'unread'): ?>
                        You have no unread notifications.
                    <?php elseif ($filter === 'read'): ?>
                        You have no read notifications.
                    <?php else: ?>
                        You don't have any notifications yet.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search)): ?>
                    <a href="?page=notifications&filter=<?= $filter ?>" class="btn btn-outline-primary">
                        <i class="fas fa-times"></i> Clear Search
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-card <?= $notification['is_read'] ? 'read' : 'unread' ?>" 
                     id="notification-<?= $notification['notification_id'] ?>">
                    <div class="notification-content">
                        <div class="notification-header">
                            <div class="notification-title">
                                <div class="d-flex align-items-center gap-2">
                                    <?= htmlspecialchars($notification['title']) ?>
                                    <?php if (!$notification['is_read']): ?>
                                        <span class="badge bg-danger" style="font-size: 8px;">NEW</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="notification-actions">
                                <button class="action-btn <?= $notification['is_read'] ? 'read' : '' ?>" 
                                        onclick="toggleNotificationStatus(<?= $notification['notification_id'] ?>, <?= $notification['is_read'] ? 0 : 1 ?>)"
                                        title="<?= $notification['is_read'] ? 'Mark as unread' : 'Mark as read' ?>">
                                    <i class="fas fa-<?= $notification['is_read'] ? 'eye-slash' : 'eye' ?>"></i>
                                </button>
                                <button class="action-btn delete" 
                                        onclick="deleteNotification(<?= $notification['notification_id'] ?>)"
                                        title="Delete notification">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="notification-message">
                            <?= htmlspecialchars($notification['message']) ?>
                        </div>
                        
                        <div class="notification-meta">
                            <div>
                                <span class="notification-type">
                                    <i class="fas fa-info-circle"></i>
                                    <?= ucfirst($notification['type'] ?? 'notification') ?>
                                </span>
                            </div>
                            <div>
                                <i class="fas fa-clock"></i>
                                <?= date('M d, Y H:i', strtotime($notification['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
            <nav aria-label="Notifications pagination">
                <ul class="pagination">
                    <?php if ($page_num > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=notifications&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&p=<?= $page_num - 1 ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                        <li class="page-item <?= $i === $page_num ? 'active' : '' ?>">
                            <a class="page-link" href="?page=notifications&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&p=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=notifications&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&p=<?= $page_num + 1 ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script>
// Toggle notification read/unread status
function toggleNotificationStatus(notificationId, isRead) {
    const formData = new FormData();
    formData.append('toggle_notification_status', '1');
    formData.append('notification_id', notificationId);
    formData.append('is_read', isRead);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Reload page to update the view
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to update notification', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

// Delete notification
function deleteNotification(notificationId) {
    if (!confirm('Are you sure you want to delete this notification? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('delete_notification', '1');
    formData.append('notification_id', notificationId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Remove the notification card with animation
            const card = document.getElementById(`notification-${notificationId}`);
            if (card) {
                card.style.opacity = '0';
                card.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    card.remove();
                    // Reload if no notifications left
                    if (document.querySelectorAll('.notification-card').length === 0) {
                        location.reload();
                    }
                }, 300);
            }
        } else {
            showToast(data.error || 'Failed to delete notification', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

// Mark all notifications as read
function markAllAsRead() {
    if (!confirm('Mark all notifications as read?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('mark_all_read', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to mark all as read', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

// Delete all read notifications
function deleteAllRead() {
    if (!confirm('Are you sure you want to delete all read notifications? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('delete_all_read', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to delete notifications', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

// Enhanced toast function with better positioning
function showToast(message, type = 'info') {
    // Remove existing toasts
    document.querySelectorAll('.toast-notification').forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        z-index: 10001;
        transform: translateX(100%);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        font-weight: 500;
        font-size: 14px;
        max-width: 350px;
        word-wrap: break-word;
    `;
    
    // Add icon based on type
    const icon = type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ';
    toast.innerHTML = `<span style="margin-right: 8px;">${icon}</span>${message}`;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after 4 seconds
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }, 4000);
}

// Auto-refresh page every 5 minutes to get new notifications
setInterval(() => {
    // Only refresh if user is not actively interacting
    if (document.hidden === false) {
        location.reload();
    }
}, 300000); // 5 minutes
</script>