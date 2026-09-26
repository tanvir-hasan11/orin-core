<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Database;
use Orin\Core\Session;
use Orin\Models\Merchant;
use Orin\Models\User;

final class AuthService
{
    private User $users;
    private Merchant $merchants;

    public function __construct(
        private Database $db,
        private Session $session,
        private AuditService $audit,
    ) {
        $this->users = new User($db);
        $this->merchants = new Merchant($db);
    }

    /** @return array<string, mixed>|null */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            return null;
        }

        if (($user['status'] ?? 'active') !== 'active') {
            return null;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        $this->startSession($user);

        return $user;
    }

    /** @param array<string, mixed> $user */
    public function startSession(array $user): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        $this->session->set('user_role', (string) $user['role']);
        $this->session->set('user_email', (string) $user['email']);
        $this->session->set('user_name', (string) $user['name']);

        $this->users->update((int) $user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        $this->audit->log('auth.login', 'user', (string) $user['id'], (int) $user['id'], (string) $user['role']);
    }

    public function logout(): void
    {
        $userId = $this->session->get('user_id');
        if ($userId !== null) {
            $this->audit->log('auth.logout', 'user', (string) $userId, (int) $userId);
        }
        $this->session->destroy();
    }

    public function hash(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        return password_hash($password, $algo);
    }

    /**
     * Register a new merchant user + merchant record.
     *
     * @param array{name:string,email:string,password:string,company:string} $data
     * @return array<string, mixed>
     */
    public function registerMerchant(array $data): array
    {
        $this->db->beginTransaction();

        try {
            $userId = $this->users->create([
                'role' => 'merchant',
                'email' => strtolower($data['email']),
                'password_hash' => $this->hash($data['password']),
                'name' => $data['name'],
                'status' => 'active',
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]);

            $planId = $this->defaultPlanId();

            $slug = Merchant::slugify($data['company']);
            $slug = $this->uniqueSlug($slug);

            $merchantId = $this->merchants->create([
                'owner_user_id' => $userId,
                'company_name' => $data['company'],
                'slug' => $slug,
                'status' => 'trial',
                'plan_id' => $planId,
                'trial_ends_at' => date('Y-m-d H:i:s', time() + 14 * 86400),
            ]);

            if ($planId !== null) {
                $this->db->insert('subscriptions', [
                    'merchant_id' => $merchantId,
                    'plan_id' => $planId,
                    'status' => 'active',
                    'starts_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->db->commit();

            $this->audit->log('auth.register', 'merchant', (string) $merchantId, $userId, 'merchant');

            return $this->users->find($userId) ?? [];
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function requestPasswordReset(string $email): ?string
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $this->db->insert('password_resets', [
            'email' => $email,
            'token_hash' => $hash,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    public function resetPassword(string $email, string $token, string $newPassword): bool
    {
        $hash = hash('sha256', $token);

        $row = $this->db->first(
            'SELECT * FROM password_resets WHERE email = :email AND token_hash = :hash AND expires_at >= NOW() LIMIT 1',
            ['email' => $email, 'hash' => $hash]
        );

        if ($row === null) {
            return false;
        }

        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return false;
        }

        $this->users->update((int) $user['id'], ['password_hash' => $this->hash($newPassword)]);
        $this->db->execute('DELETE FROM password_resets WHERE email = :email', ['email' => $email]);

        $this->audit->log('auth.password_reset', 'user', (string) $user['id'], (int) $user['id']);

        return true;
    }

    private function defaultPlanId(): ?int
    {
        $row = $this->db->first(
            'SELECT id FROM plans WHERE is_active = 1 ORDER BY price_usd ASC LIMIT 1'
        );

        return $row === null ? null : (int) $row['id'];
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 1;

        while ($this->merchants->findBySlug($slug) !== null) {
            $suffix++;
            $slug = $base . '-' . $suffix;
        }

        return $slug;
    }
}
