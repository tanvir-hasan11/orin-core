<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Admin;

use Orin\Core\Request;
use Orin\Core\Response;

/**
 * Shared helpers for super admin controllers.
 *
 * Access is already gated by the `role:admin` middleware on the route group,
 * so these controllers assume an authenticated admin/super_admin caller.
 */
abstract class BaseController
{
    /** @param array<string, mixed> $data */
    protected function panel(Request $request, string $template, array $data): Response
    {
        $data['sidebar'] = admin_sidebar();

        return Response::html(view($template, $data, 'layouts/panel'));
    }

    /** @param array<string, mixed> $meta */
    protected function audit(string $action, ?string $targetType = null, ?string $targetId = null, array $meta = []): void
    {
        $user = current_user();

        app('audit')->log(
            $action,
            $targetType,
            $targetId,
            isset($user['id']) ? (int) $user['id'] : null,
            $user['role'] ?? null,
            $meta,
        );
    }
}