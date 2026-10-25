<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;

class AuthController
{
    public static function login(array $payload): void
    {
        $username = trim($payload['username'] ?? '');
        $password = (string)($payload['password'] ?? '');

        if (empty($username) || empty($password)) {
            Response::error('Username and password are required.', 400);
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `username` = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('Invalid username or password credentials.', 401, null, 'INVALID_CREDENTIALS');
        }

        if (empty($user['is_active'])) {
            Response::error('This account has been deactivated.', 403, null, 'ACCOUNT_DEACTIVATED');
        }

        // Update last login
        $stmtUpdate = $pdo->prepare('UPDATE `users` SET `last_login_at` = datetime("now") WHERE `id` = :id');
        $stmtUpdate->execute([':id' => $user['id']]);

        $token = Auth::createToken($user);

        Response::success([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'email' => $user['email'],
                'phone' => $user['phone'],
            ]
        ], 200, 'Login successful');
    }

    public static function pinLogin(array $payload): void
    {
        $pin = trim((string)($payload['pin'] ?? ''));
        $cashierId = isset($payload['user_id']) ? (int)$payload['user_id'] : null;

        if (empty($pin)) {
            Response::error('PIN code is required.', 400);
        }

        $pdo = Database::getInstance();

        if ($cashierId) {
            $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :id AND `is_active` = 1 LIMIT 1');
            $stmt->execute([':id' => $cashierId]);
            $users = $stmt->fetchAll();
        } else {
            // Find active users matching the PIN
            $stmt = $pdo->query('SELECT * FROM `users` WHERE `is_active` = 1');
            $users = $stmt->fetchAll();
        }

        $matchedUser = null;
        foreach ($users as $user) {
            if (password_verify($pin, $user['pin_code_hash'])) {
                $matchedUser = $user;
                break;
            }
        }

        if (!$matchedUser) {
            Response::error('Invalid PIN code.', 401, null, 'INVALID_PIN');
        }

        $token = Auth::createToken($matchedUser);

        Response::success([
            'token' => $token,
            'user' => [
                'id' => $matchedUser['id'],
                'username' => $matchedUser['username'],
                'full_name' => $matchedUser['full_name'],
                'role' => $matchedUser['role'],
                'email' => $matchedUser['email'],
            ]
        ], 200, 'PIN authentication successful');
    }

    public static function switchCashier(array $payload): void
    {
        self::pinLogin($payload);
    }

    public static function me(array $payload): void
    {
        $currentUser = Auth::getCurrentUser();
        if (!$currentUser) {
            Response::error('Unauthenticated', 401);
        }

        Response::success([
            'user' => $currentUser
        ]);
    }

    public static function usersList(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT `id`, `username`, `full_name`, `role`, `email`, `is_active` FROM `users` WHERE `is_active` = 1 ORDER BY `full_name` ASC');
        $users = $stmt->fetchAll();

        Response::success(['users' => $users]);
    }
}
