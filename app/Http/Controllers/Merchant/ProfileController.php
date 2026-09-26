<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\VerticalRegistry;

/**
 * The business profile decides how ORIN talks: which vertical, what tone,
 * what delivery promise. Without it the AI has nothing to ground on.
 */
final class ProfileController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var VerticalRegistry $verticals */
        $verticals = app('vertical_registry');

        return $this->panel('merchant/profile', [
            'title' => 'Business Profile - Orin',
            'merchant' => $merchant,
            'profile' => app('conversation_service')->profile((int) $merchant['id']),
            'verticals' => $verticals->all(),
            'saved' => (bool) app('session')->pullFlash('profile_saved', false),
        ]);
    }

    public function update(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var VerticalRegistry $verticals */
        $verticals = app('vertical_registry');

        $type = (string) $request->input('business_type', 'generic');
        if (!$verticals->has($type)) {
            $type = 'generic';
        }

        $values = [
            'business_type' => $type,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'tone' => trim((string) $request->input('tone', 'friendly')) ?: 'friendly',
            'greeting' => trim((string) $request->input('greeting', '')) ?: null,
            'working_hours' => trim((string) $request->input('working_hours', '')) ?: null,
            'delivery_info' => trim((string) $request->input('delivery_info', '')) ?: null,
            'service_area' => trim((string) $request->input('service_area', '')) ?: null,
            'language' => (string) $request->input('language', 'auto'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        /** @var Database $db */
        $db = app('db');
        $merchantId = (int) $merchant['id'];

        $exists = $db->first('SELECT merchant_id FROM merchant_profiles WHERE merchant_id = :m LIMIT 1', ['m' => $merchantId]);

        if ($exists === null) {
            $values['merchant_id'] = $merchantId;
            $values['created_at'] = date('Y-m-d H:i:s');
            $db->insert('merchant_profiles', $values);
        } else {
            $db->update('merchant_profiles', $values, ['merchant_id' => $merchantId]);
        }

        app('audit')->log('merchant.profile_update', 'merchant', (string) $merchantId, (int) $request->attributes['user_id'], 'merchant', ['business_type' => $type]);
        app('session')->flash('profile_saved', true);

        return Response::redirect('/merchant/profile');
    }
}
