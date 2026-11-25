import type { NextConfig } from "next";

/**
 * The browser only ever talks to this origin. API, Sanctum and API-docs
 * requests are passed on to Laravel, so session cookies stay first-party and
 * no CORS setup is needed. In production the reverse proxy does the same.
 */
const BACKEND_URL = process.env.BACKEND_URL ?? "http://localhost:8000";

const nextConfig: NextConfig = {
  // Off on purpose: Ant Design's CSS-in-JS registry creates a random instance
  // id while rendering, which Cache Components refuses to prerender. The panel
  // is client-rendered from API data, so it gains little from them anyway.
  cacheComponents: false,
  // The Docker image runs the self-contained server (see infra/docker/frontend.Dockerfile).
  output: process.env.NEXT_OUTPUT === "standalone" ? "standalone" : undefined,
  poweredByHeader: false,
  async rewrites() {
    return [
      { source: "/api/:path*", destination: `${BACKEND_URL}/api/:path*` },
      { source: "/sanctum/:path*", destination: `${BACKEND_URL}/sanctum/:path*` },
      { source: "/docs/api", destination: `${BACKEND_URL}/docs/api` },
      { source: "/docs/api.json", destination: `${BACKEND_URL}/docs/api.json` },
    ];
  },
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "X-Frame-Options", value: "DENY" },
          { key: "Permissions-Policy", value: "camera=(self), microphone=(), geolocation=(self)" },
        ],
      },
    ];
  },
};

export default nextConfig;
