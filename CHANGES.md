# Changelog

## 1.5.0

**Security release: update as soon as you can.** Read "Before you update" first: webhooks need a new setting.

### Before you update

- **Create a webhook signing secret.** In the Paystack Dashboard, open Settings > API Keys & Webhooks and create a signing secret (`whsec_…`). After updating, paste it into the Test or Live webhook signing secret field. Until you do, Moodle rejects every webhook. Students who return to Moodle after paying are still enrolled, but students who close the payment window before returning aren't.
- **Your current keys keep working.** Payments continue with the secret key and public key you already saved. You don't need to change anything to keep taking payments.

### What's new

- **Restricted keys.** The settings page now takes a Paystack restricted key (`rk_test_…` / `rk_live_…`) instead of a secret key and public key. Give it only **Account: read** and **Transactions: read**. When you save it, Moodle checks the key with Paystack and tells you what to fix if a permission is missing or not needed. Saving a restricted key replaces and deletes that mode's old secret key. After that, roll the old secret key in the Paystack Dashboard.
- **Secret and public key fields are removed.** Sites upgraded from 1.4.x keep using their saved keys, and the settings page reminds you to replace them with a restricted key. To change an old secret key, save a restricted key.
- **Signed webhooks.** Webhooks are accepted only when they're signed with your webhook signing secret. The old check against the secret key is removed.
- **Safer payment checks.** Before enrolling a student, Moodle confirms that the payment matches the course's price and currency, the student who paid, and the active mode. A payment reference can only be used once.
- **Saved keys stay hidden.** Keys are never shown again after saving. The page shows the last four characters and a **Remove the saved key** option.
- **No key, no payment button.** If there's no usable key for the active mode, students see a message instead of a payment button they couldn't complete.

### After you update

- **Roll your secret key** in the Paystack Dashboard. Earlier versions recorded keys in plain text in Moodle's configuration log. The update removes those entries, but treat the old keys as exposed.
- **Don't set keys with `admin/cli/cfg.php`.** It writes them to the configuration log unmasked, and keys set that way aren't checked with Paystack.

### For developers

- `paystack::validate_webhook()` and `paystack::initialize_transaction()` are removed.
- `paystack::verify_transaction()` now returns `['http' => …, 'body' => …, 'data' => …]`.
- The minimum Moodle version is unchanged (3.6).
