import type { NextConfig } from "next";

/**
 * Where the Laravel API listens; the browser never talks to it directly.
 * Rewrites are fixed at build time, so the Docker image takes it as a
 * build argument.
 */
const backendUrl = process.env.BACKEND_URL ?? "http://127.0.0.1:8000";

const nextConfig: NextConfig = {
  // Self-contained server bundle, only for the Docker image (`next start`
  // does not support it).
  output: process.env.NEXT_OUTPUT === "standalone" ? "standalone" : undefined,
  cacheComponents: true,
  partialPrefetching: true,
  // The API is served under the same origin as the panel, so the Sanctum
  // session cookie and CSRF token work without any CORS setup.
  async rewrites() {
    return [
      { source: "/api/:path*", destination: `${backendUrl}/api/:path*` },
      { source: "/sanctum/:path*", destination: `${backendUrl}/sanctum/:path*` },
    ];
  },
};

export default nextConfig;
