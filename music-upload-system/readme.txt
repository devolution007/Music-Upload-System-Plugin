=== Music Upload System ===
Contributors: luxproductions
Tags: music, upload, stripe, submissions, audio
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets independent artists register, pay a submission fee via Stripe, and upload songs for admin review before publication.

== Description ==

Music Upload System adds a full submission workflow to LuxProductions:

1. Artists create an account.
2. Artists upload an MP3, song title, and description.
3. Artists pay a $25 (configurable) submission fee via Stripe Checkout.
4. The submission is sent for admin review.
5. Once approved, the song is published with a front-end audio player.

**Features**

* Artist registration and login (front-end forms, no separate account system needed).
* Frontend audio upload form with MP3 validation.
* Secure Stripe Checkout integration (no card data ever touches your server).
* Admin approval system with approve/reject actions and email notifications.
* Audio player shortcode for published songs.
* Mobile-friendly, responsive forms and player.
* A dedicated "Song Upload Guidelines" page, created automatically on activation.

== Shortcodes ==

* `[mus_register]` - Artist registration form.
* `[mus_login]` - Artist login form.
* `[mus_logout]` - Log out link (shown only when logged in).
* `[mus_upload_form]` - Song upload form (requires login).
* `[mus_dashboard]` - Lists the current artist's submissions and statuses, with a "Retry Payment" action.
* `[mus_published_songs]` - Grid of approved songs with an HTML5 audio player.
* `[mus_guidelines]` - Renders the Song Upload Guidelines content (used automatically on the auto-created page).

== Installation ==

1. Upload the `music-upload-system` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to **Song Uploads > Settings** and enter your Stripe Publishable Key, Secret Key, and set the upload fee/currency.
4. In your Stripe Dashboard, add a webhook endpoint for the `checkout.session.completed` event pointing to the URL shown on the settings page (`/wp-json/mus/v1/stripe-webhook`), and paste its signing secret into the settings page.
5. Create pages containing the shortcodes above (e.g. a "Register" page, a "Login" page, an "Upload a Song" page, "My Submissions" page, and a "Published Songs" page). The "Song Upload Guidelines" page is created automatically.

== Submission Workflow Details ==

* New submissions start in **Pending Payment** status until Stripe confirms the charge (via webhook, with a return-URL fallback for immediate feedback).
* Once paid, submissions move to **Awaiting Review** and admins are notified by email.
* Admins approve or reject submissions from the Song Uploads list table or the submission's edit screen. Approving sets the post to **Published** (shown in `[mus_published_songs]`); rejecting sets it to **Rejected** and notifies the artist.
* Artists can track their submissions and retry payment on any still-pending submission from `[mus_dashboard]`.

== Changelog ==

= 1.0.0 =
* Initial release.
