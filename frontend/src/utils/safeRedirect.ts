/**
 * Guards against open-redirect payloads in post-login navigation.
 *
 * The saved "return to" path comes from the URL the user was blocked on, so a
 * crafted link could otherwise carry an external target (`//evil.com`,
 * `/\evil.com`, `https://evil.com`). Only same-origin absolute paths are
 * allowed through; everything else falls back to a known-safe route.
 *
 * This also neutralises the reachable half of the react-router 6 advisory
 * GHSA-wrjc-x8rr-h8h6 for our navigation flow.
 */
export function safeRedirectPath(candidate: unknown, fallback: string): string {
  if (typeof candidate !== 'string' || candidate.length === 0) {
    return fallback;
  }

  // Must be a rooted path, and must not start a protocol-relative or
  // backslash-escaped external URL.
  const normalized = candidate.replace(/\\/g, '/');
  if (!normalized.startsWith('/') || normalized.startsWith('//')) {
    return fallback;
  }

  // Reject anything that still parses as an absolute URL with a host.
  if (/^[a-z][a-z0-9+.-]*:/i.test(normalized)) {
    return fallback;
  }

  return normalized;
}
