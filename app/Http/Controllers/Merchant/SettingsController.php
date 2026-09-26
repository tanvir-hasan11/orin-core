<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Core\Validator;
use Orin\Models\User;
use Orin\Services\AuthService;

final class SettingsController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        return $this->panel('merchant/settings', [
            'title' => 'Settings - Orin',
            'merchant' => $merchant,
            'user' => current_user(),
        ]);
    }

    public function update(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $name = trim((string) $request->input('name', ''));
        $company = trim((string) $request->input('company', ''));

        $validator = new Validator(['name' => $name, 'company' => $company], [
            'name' => 'required|min:2|max:120',
            'company' => 'required|min:2|max:180',
        ]);

        if (!$validator->validate()) {
            return $this->panel('merchant/settings', [
                'title' => 'Settings - Orin',
                'merchant' => $merchant,
                'user' => current_user(),
                'errors' => $validator->errors(),
            ]);
        }

        /** @var \Orin\Core\Database $db */
        $db = app('db');
        $userId = (int) $request->attributes['user_id'];

        (new User($db))->update($userId, ['name' => $name]);
        $db->update('merchants', ['company_name' => $company, 'updated_at' => date('Y-m-d H:i:s')], ['id' => (int) $merchant['id']]);

        app('session')->set('user_name', $name);
        app('audit')->log('merchant.settings_update', 'merchant', (string) $merchant['id'], $userId, 'merchant');

        return Response::redirect('/merchant/settings');
    }

    public function updatePassword(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $current = (string) $request->input('current_password', '');
        $new = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('new_password_confirmation', '');

        $validator = new Validator(['new_password' => $new, 'new_password_confirmation' => $confirm], [
            'new_password' => 'required|min:8|same:new_password_confirmation',
        ]);

        /** @var \Orin\Core\Database $db */
        $db = app('db');
        $user = (new User($db))->find((int) $request->attributes['user_id']);

        if (!$validator->validate() || $user === null || !password_verify($current, (string) $user['password_hash'])) {
            return $this->panel('merchant/settings', [
                'title' => 'Settings - Orin',
                'merchant' => $merchant,
                'user' => current_user(),
                'passwordErrors' => $validator->validate() ? ['current_password' => ['Current password is incorrect.']] : $validator->errors(),
            ]);
        }

        /** @var AuthService $auth */
        $auth = app('auth');
        (new User($db))->update((int) $user['id'], ['password_hash' => $auth->hash($new)]);
        app('audit')->log('merchant.password_change', 'user', (string) $user['id'], (int) $user['id'], 'merchant');

        return Response::redirect('/merchant/settings');
    }
}
