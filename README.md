# Music Upload System

A WordPress plugin built for LuxProductions that lets independent artists register, pay a submission fee via Stripe, and upload songs for admin review before publication.

## Workflow

1. Artist creates an account.
2. Artist uploads an MP3 file, song title, and description.
3. Artist pays a $25 (configurable) submission fee via Stripe Checkout.
4. The submission is sent for admin review.
5. Once approved, the song is published with a front-end audio player.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- A [Stripe](https://stripe.com) account (test or live mode)
- HTTPS on the site (required by Stripe Checkout and for a publicly reachable webhook URL)

## Installation

1. Copy the `music-upload-system` folder into your site's `wp-content/plugins/` directory.
   - Or zip the `music-upload-system` folder and upload it via **Plugins > Add New > Upload Plugin**.
2. Go to **Plugins** in wp-admin and activate **Music Upload System**.
   - On activation the plugin creates the `Artist` role and a **Song Upload Guidelines** page automatically.

## Configuration

### 1. Stripe keys

Go to **Song Uploads > Settings** in wp-admin and enter:

| Field | Where to find it |
|---|---|
| Stripe Publishable Key | Stripe Dashboard > Developers > API keys |
| Stripe Secret Key | Stripe Dashboard > Developers > API keys |
| Upload Fee | Defaults to `25.00` |
| Currency | Defaults to `usd` |

### 2. Stripe webhook

The settings page displays your webhook URL, in the form:

```
https://your-site.com/wp-json/mus/v1/stripe-webhook
```

In the Stripe Dashboard, go to **Developers > Webhooks > Add endpoint**, paste that URL, and subscribe to the `checkout.session.completed` event. Copy the endpoint's **Signing secret** back into the **Stripe Webhook Signing Secret** field in the plugin settings.

> The webhook confirms payment authoritatively. A return-URL check also runs immediately after checkout as a fallback so artists get instant feedback even if the webhook is briefly delayed.

### 3. Create the front-end pages

Create WordPress pages containing these shortcodes (page titles/slugs are up to you):

| Page | Shortcode |
|---|---|
| Register | `[mus_register]` |
| Login | `[mus_login]` |
| Upload a Song | `[mus_upload_form]` |
| My Submissions | `[mus_dashboard]` |
| Published Songs | `[mus_published_songs]` |

The **Song Upload Guidelines** page is created automatically and uses `[mus_guidelines]`.

## Admin review

Submissions appear under **Song Uploads** in wp-admin once an artist has paid. Each submission shows the artist, description, and an inline audio player, with **Approve & Publish** / **Reject** actions. Approving publishes the song (it appears in `[mus_published_songs]`); rejecting notifies the artist and lets them see the reason on their dashboard.

## Uninstalling

Deleting the plugin (not just deactivating) removes the `Artist` role, plugin settings, the Guidelines page, and all song submissions and their attached audio files.

## Shortcode reference

- `[mus_register]` – Artist registration form.
- `[mus_login]` – Artist login form.
- `[mus_logout]` – Log out link (shown only when logged in).
- `[mus_upload_form]` – Song upload form (requires login).
- `[mus_dashboard]` – Artist's own submissions and statuses, with a "Retry Payment" action.
- `[mus_published_songs]` – Grid of approved songs with an audio player.
- `[mus_guidelines]` – Song Upload Guidelines content.
