# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer Bearer {YOUR_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Use the token returned from <code>POST /login</code> in the <code>Authorization</code> header.
