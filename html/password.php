<?php
/*
Urmi you happy me happy licence

Copyright (c) 2026 shreebhattji

License text:
https://github.com/shreebhattji/Urmi/blob/main/licence.md
*/

require_once __DIR__ . '/require_login.php';

$usersFile = '/var/www/users.json';

function load_json(string $file): array
{
    return is_file($file) ? json_decode(file_get_contents($file), true) ?: [] : [];
}

function save_json(string $file, array $data): void
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}


$error = '';
$success = '';

$currentUser = $_SESSION['user'] ?? '';
$users = load_json($usersFile);

if ($currentUser === '' || !isset($users[$currentUser])) {
    header('Location: /login.php');
    exit;
}

/* ---------- POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $newUsername = strtolower(trim($_POST['new_username'] ?? ''));
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // Verify current password
    if (!password_verify($currentPass, $users[$currentUser]['password'])) {
        $error = 'Current password is incorrect.';
    }

    // Validate new password if provided
    if (!$error && $newPass !== '') {
        if (strlen($newPass) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New passwords do not match.';
        }
    }

    // Validate new username if provided
    if (!$error && $newUsername !== '' && $newUsername !== $currentUser) {
        if (!preg_match('/^[a-z0-9_]{3,32}$/', $newUsername)) {
            $error = 'Username must be 3–32 chars (a–z, 0–9, underscore).';
        } elseif (isset($users[$newUsername])) {
            $error = 'Username already exists.';
        }
    }

    if (!$error) {
        // Apply changes
        $updatedUser = $currentUser;

        if ($newPass !== '') {
            $users[$currentUser]['password'] = password_hash($newPass, PASSWORD_DEFAULT);
        }

        if ($newUsername !== '' && $newUsername !== $currentUser) {
            $users[$newUsername] = $users[$currentUser];
            unset($users[$currentUser]);
            $updatedUser = $newUsername;
        }

        save_json($usersFile, $users);

        // Update session safely
        session_regenerate_id(true);
        $_SESSION['user'] = $updatedUser;

        $success = 'Credentials updated successfully.';
    }
}

include 'header.php';
?>

<div class="containerindex">
    <div class="grid">
        <div class="card wide">
            <h3>Change Username / Password</h3>
            <?php if ($error): ?>
                <div class="alert alert-error" style="background: rgba(220, 38, 38, 0.2); border: 1px solid #dc2626; color: #fca5a5; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" style="background: rgba(22, 163, 74, 0.2); border: 1px solid #16a34a; color: #86efac; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off" style="display: flex; flex-direction: column; gap: 16px; max-width: 500px;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

                <div class="form-group">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: var(--text-secondary);">New Username (optional)</label>
                    <input type="text" name="new_username" placeholder="leave blank to keep current (<?= htmlspecialchars($currentUser) ?>)" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.15); background: rgba(15, 23, 42, 0.6); color: #fff;">
                </div>

                <div class="form-group">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: var(--text-secondary);">Current Password (required)</label>
                    <input type="password" name="current_password" required style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.15); background: rgba(15, 23, 42, 0.6); color: #fff;">
                </div>

                <div class="form-group">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: var(--text-secondary);">New Password (optional)</label>
                    <input type="password" name="new_password" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.15); background: rgba(15, 23, 42, 0.6); color: #fff;">
                </div>

                <div class="form-group">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: var(--text-secondary);">Confirm New Password</label>
                    <input type="password" name="confirm_password" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.15); background: rgba(15, 23, 42, 0.6); color: #fff;">
                </div>

                <div style="margin-top: 8px;">
                    <button type="submit" class="green-btn"><i class="fas fa-key"></i> Update Credentials</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>