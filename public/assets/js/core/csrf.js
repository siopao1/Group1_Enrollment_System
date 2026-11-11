// Reads the CSRF token that index.php prints into <meta name="csrf-token">.
// It is sent in the X-CSRF-Token header on every request that changes data,
// and the server rejects those requests (419) when the token is missing or wrong.
export function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? "";
}

export default csrfToken;
