# ProcWebViewSession

Shopware 6 plugin that bridges a mobile app's WebView sessions to the Shopware storefront. It registers or updates customers via a REST API, returns a Shopware context token, and ensures every WebView storefront request is processed as an authenticated customer session.

---

## How It Works

```
Mobile App
  │
  ├── 1. POST /api/proc-webview-customer  (OAuth2 bearer token)
  │         { appUserId, email, firstName, lastName, languageCode }
  │
  │   ← { contextToken: "sw-context-token-value" }
  │
  ├── 2. Inject contextToken as a cookie into the WebView
  │         Cookie name = value of `frontendTokenCookieName` plugin config
  │
  └── 3. WebView loads Shopware storefront
            └── Plugin reads cookie → injects as `sw-context-token` header
                    └── Shopware resolves authenticated SalesChannelContext
```

**Session validation on every storefront request:**
- No cookie → `401` (app must re-initialize the session)
- Cookie token not mapped to a customer in DB → `401`
- Valid customer token → request proceeds as authenticated

---

## Plugin Configuration

Navigate to **Shopware Admin → Extensions → ProcWebViewSession → Configure**.

| Field | Description |
|---|---|
| **SW Context Token Cookie Name** | Name of the cookie the mobile app injects into the WebView. Must match what the app sets. |
| **Sales Channel** | The storefront sales channel to resolve customer sessions against. |
| **Default Customer Address ID** | UUID of a `customer_address` entity used as the default billing/shipping address for newly created customers. |
| **Trigger session cookie on Save** *(Debug)* | Toggle ON + Save to generate a session cookie for the selected debug customer. Resets to OFF automatically. For browser-based WebView testing. |
| **Customer for Debug Login** *(Debug)* | The customer whose session is triggered by the debug toggle above. |

---

## API

### Authentication

The endpoint requires an OAuth2 **client credentials** token. Obtain one from an integration in Shopware Admin → Settings → Integrations.

```
POST /api/oauth/token
Content-Type: application/json

{
  "grant_type": "client_credentials",
  "client_id": "<access_key>",
  "client_secret": "<secret_access_key>"
}
```

### Upsert Customer

```
POST /api/proc-webview-customer
Authorization: Bearer <token>
Content-Type: application/json

{
  "appUserId":    "user-123",          // required — unique mobile app user identifier
  "email":        "user@example.com",  // required
  "firstName":    "John",              // required
  "lastName":     "Doe",               // required
  "languageCode": "en-GB",             // required — must match a Shopware locale
  "customerGroupId": "<uuid>"          // optional — Shopware customer group UUID
}
```

**Response:**

```json
{
  "contextToken": "sw-context-token-xxxxxxxxxxxxxxxx",
  "customerId":   "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

The mobile app stores `contextToken` and injects it as a cookie (named per `frontendTokenCookieName`) into every WebView request.

---

## Testing

### 1. API — Create/update a customer

Use Postman or curl. Set up OAuth2 client credentials first (see Authentication above).

```bash
curl -X POST http://<shopware-host>/api/proc-webview-customer \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "appUserId": "test-user-1",
    "email": "test@example.com",
    "firstName": "Test",
    "lastName": "User",
    "languageCode": "en-GB"
  }'
```

Expected: `200 OK` with `contextToken` and `customerId` in the response body.

### 2. Storefront session — verify authenticated WebView

**Option A — Debug toggle (browser testing):**
1. Admin → Extensions → ProcWebViewSession → Configure
2. Select a customer under "Customer for Debug Login"
3. Toggle "Trigger session cookie on Save" to ON, click Save
4. Open the storefront in a browser — the session cookie is now set
5. Navigate to the customer account section — it should display the customer's details (not a login prompt)
6. Toggle OFF + Save to clear the session

**Option B — Simulate the app flow manually:**
1. Call `POST /api/proc-webview-customer` and capture the `contextToken`
2. In a browser DevTools console (on the storefront domain), set the cookie:
   ```js
   document.cookie = "<frontendTokenCookieName>=<contextToken>; path=/";
   ```
3. Reload the storefront — the customer should be authenticated

### 3. Session invalidation

Clear or remove the cookie and reload the storefront. The response should be `401` with the message asking to re-initialize via the API.

---

## Requirements

- Shopware 6.7+
- PHP 8.2+

---

## License

Proprietary — (c) ProCoders
