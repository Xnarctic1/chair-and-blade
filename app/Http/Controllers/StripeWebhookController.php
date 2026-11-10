<?php

namespace App\Http\Controllers;

use App\StripeCheckout;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeCheckout $stripe): Response
    {
        if (! $stripe->configured()) {
            return response('Payments are not configured.', 503);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature', ''), config('payments.webhook_secret'));
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('Invalid signature or payload.', 400);
        }

        if (in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.expired'], true)) {
            $stripe->applySession($event->data->object->toArray());
        }

        return response('Received.', 200);
    }
}
