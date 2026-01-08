<?php

declare(strict_types=1);
include 'header.php';
?>
<?php
$usersFile    = '/var/www/users.json';
function load_json(string $file): array
{
    return is_file($file) ? json_decode(file_get_contents($file), true) ?: [] : [];
}
function save_json(string $file, array $data): void
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

$currentUser = $_SESSION['user'];
$users = load_json($usersFile);

if (!isset($users[$currentUser])) {
    // Safety fallback
    session_destroy();
    header('Location: /login.php');
    exit;
}

/* ---------- POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('Invalid request');
    }

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
            $users[$currentUser]['password'] =
                password_hash($newPass, PASSWORD_DEFAULT);
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

?>

<div class="containerindex">
    <div class="grid">
        <div class="card wide">
            <h3>Change Username / Password</h3>
            <?php if ($error): ?>
                <p style="color:#dc2626"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <?php if ($success): ?>
                <p style="color:#16a34a"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

                <p>
                    <label>New Username (optional)</label><br>
                    <input type="text" name="new_username" placeholder="leave blank to keep current">
                </p>

                <p>
                    <label>Current Password (required)</label><br>
                    <input type="password" name="current_password" required>
                </p>

                <p>
                    <label>New Password (optional)</label><br>
                    <input type="password" name="new_password">
                </p>

                <p>
                    <label>Confirm New Password</label><br>
                    <input type="password" name="confirm_password">
                </p>

                <button type="submit">Update</button>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>