<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\ChannelService;

/**
 * Where a merchant connects the WhatsApp number and Facebook page that ORIN
 * will answer on. These are the merchant's own business accounts.
 */
final class ChannelController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var ChannelService $channels */
        $channels = app('channel_service');

        return $this->panel('merchant/channels', [
            'title' => 'Channels - Orin',
            'merchant' => $merchant,
            'connections' => $channels->listFor((int) $merchant['id']),
            'whatsappCallback' => rtrim((string) (app('config')['app']['url'] ?? ''), '/') . '/webhooks/whatsapp',
            'messengerCallback' => rtrim((string) (app('config')['app']['url'] ?? ''), '/') . '/webhooks/messenger',
            'errors' => app('session')->pullFlash('channel_errors', []),
        ]);
    }

    public function connectWhatsApp(Request $request): Response
    {
        return $this->connect($request, 'whatsapp');
    }

    public function connectMessenger(Request $request): Response
    {
        return $this->connect($request, 'messenger');
    }

    public function disconnect(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);

        /** @var ChannelService $channels */
        $channels = app('channel_service');
        $channels->disconnect((int) $merchant['id'], $id, (int) $request->attributes['user_id']);

        return Response::redirect('/merchant/channels');
    }

    private function connect(Request $request, string $channel): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $accountId = trim((string) $request->input('external_account_id', ''));
        $token = trim((string) $request->input('access_token', ''));

        if ($accountId === '' || $token === '') {
            app('session')->flash('channel_errors', ['Account ID and access token are both required.']);

            return Response::redirect('/merchant/channels');
        }

        /** @var ChannelService $channels */
        $channels = app('channel_service');
        $channels->connect((int) $merchant['id'], $channel, [
            'external_account_id' => $accountId,
            'display_name' => (string) $request->input('display_name', ''),
            'access_token' => $token,
            'app_secret' => (string) $request->input('app_secret', ''),
            'verify_token' => (string) $request->input('verify_token', ''),
        ], (int) $request->attributes['user_id']);

        return Response::redirect('/merchant/channels');
    }
}
