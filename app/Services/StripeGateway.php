<?php

namespace App\Services;

use App\Exceptions\StripeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Stripe client over the REST API.
 *
 * Only the handful of calls EventPast needs (Connect onboarding, Checkout,
 * webhook verification). Kept in one class so it can be swapped for the
 * official SDK without touching callers.
 */
class StripeGateway
{
    private const BASE = 'https://api.stripe.com/v1';

    /** Seconds a webhook timestamp may differ from now (Stripe's default). */
    private const WEBHOOK_TOLERANCE = 300;

    // ---- Connect -------------------------------------------------------

    /**
     * @param  array<string, string>  $metadata
     * @return array<string, mixed>
     */
    public function createConnectedAccount(string $email, array $metadata = []): array
    {
        return $this->post('/accounts', [
            'type' => 'express',
            'email' => $email,
            'capabilities' => [
                'card_payments' => ['requested' => 'true'],
                'transfers' => ['requested' => 'true'],
            ],
            'metadata' => $metadata,
        ]);
    }

    /** @return array<string, mixed> */
    public function createOnboardingLink(string $accountId, string $returnUrl, string $refreshUrl): array
    {
        return $this->post('/account_links', [
            'account' => $accountId,
            'type' => 'account_onboarding',
            'return_url' => $returnUrl,
            'refresh_url' => $refreshUrl,
        ]);
    }

    /** @return array<string, mixed> */
    public function retrieveAccount(string $accountId): array
    {
        return $this->get("/accounts/{$accountId}");
    }

    // ---- Checkout ------------------------------------------------------

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $params, string $idempotencyKey): array
    {
        return $this->post('/checkout/sessions', $params, $idempotencyKey);
    }

    // ---- Webhooks ------------------------------------------------------

    /**
     * Verify a webhook against one or more signing secrets and return the
     * decoded event. Throws StripeException if none match or it is stale.
     *
     * @param  array<int, string|null>  $secrets
     * @return array<string, mixed>
     */
    public function verifyWebhook(string $payload, ?string $header, array $secrets, ?int $now = null): array
    {
        $secrets = array_values(array_filter($secrets));

        if (! $header || ! $secrets) {
            throw new StripeException('Missing webhook signature or secret.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if (! $timestamp || ! $signatures) {
            throw new StripeException('Malformed webhook signature header.');
        }

        if (abs(($now ?? time()) - $timestamp) > self::WEBHOOK_TOLERANCE) {
            throw new StripeException('Webhook timestamp outside tolerance.');
        }

        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

            foreach ($signatures as $signature) {
                if (hash_equals($expected, $signature)) {
                    $event = json_decode($payload, true);

                    if (! is_array($event) || ! isset($event['type'])) {
                        throw new StripeException('Webhook payload is not a valid event.');
                    }

                    return $event;
                }
            }
        }

        throw new StripeException('Webhook signature does not match.');
    }

    // ---- HTTP ----------------------------------------------------------

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function post(string $path, array $params, ?string $idempotencyKey = null): array
    {
        $request = $this->client();

        if ($idempotencyKey) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        return $this->send(fn () => $request->asForm()->post(self::BASE.$path, $params));
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        return $this->send(fn () => $this->client()->get(self::BASE.$path));
    }

    private function client(): PendingRequest
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw new StripeException('Stripe is not configured (STRIPE_SECRET is empty).');
        }

        return Http::withToken($secret)
            ->withHeaders(['Stripe-Version' => config('services.stripe.api_version')])
            ->timeout(20)
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    /**
     * @param  callable(): \Illuminate\Http\Client\Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        try {
            $response = $call();
        } catch (ConnectionException $e) {
            throw new StripeException('Could not reach Stripe. Please try again.');
        }

        if ($response->failed()) {
            $error = $response->json('error') ?? [];

            throw new StripeException(
                $error['message'] ?? 'Stripe request failed.',
                $response->status(),
                $error['code'] ?? null,
            );
        }

        return $response->json() ?? [];
    }
}
