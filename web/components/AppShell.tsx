"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "./AuthProvider";

function HopMark() {
  return (
    <svg viewBox="0 0 32 32" aria-hidden="true" className="h-8 w-8">
      <circle cx="16" cy="16" r="15" fill="#e08a3c" />
      <path
        d="M16 6c2 4 2 7 0 10 3-1 6-1 9 1-3 2-5 5-5 9-2-3-4-4-7-4 1-3 2-6 1-10 1 2 2 3 4 3-1-3-1-6-2-9z"
        fill="#140e0b"
      />
    </svg>
  );
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { ready, authenticated, profile, login, logout } = useAuth();
  const admin = profile?.roles.includes("platform_admin") ?? false;

  const links = [
    { href: "/", label: "Home" },
    { href: "/profile", label: "Profile" },
    { href: "/shop-request", label: "Shop request" },
  ];
  if (admin) {
    links.push({ href: "/admin/requests", label: "Requests" }, { href: "/admin/shops", label: "Shops" });
  }

  return (
    <div className="mx-auto flex min-h-screen max-w-6xl flex-col px-4 py-6 sm:px-6">
      <header className="mb-8 flex flex-wrap items-center justify-between gap-4">
        <Link href="/" className="flex items-center gap-3">
          <HopMark />
          <span>
            <span className="block font-display text-2xl leading-none">Brewsbook</span>
            <span className="text-xs uppercase tracking-[0.18em] text-foam/70">Identity & profile</span>
          </span>
        </Link>
        <nav className="flex flex-wrap items-center gap-2">
          {links.map((link) => {
            const active = link.href === "/" ? pathname === "/" : pathname.startsWith(link.href);
            return (
              <Link
                key={link.href}
                href={link.href}
                className={`rounded-full px-3 py-1.5 text-sm ${active ? "bg-cream text-ink" : "text-foam hover:bg-white/10"}`}
              >
                {link.label}
              </Link>
            );
          })}
        </nav>
        <div>
          {!ready ? (
            <span className="text-sm text-foam/70">Connecting…</span>
          ) : authenticated ? (
            <button type="button" className="btn-ghost" onClick={logout}>
              Sign out{profile?.preferred_username ? ` · ${profile.preferred_username}` : ""}
            </button>
          ) : (
            <button type="button" className="btn-primary" onClick={login}>
              Sign in
            </button>
          )}
        </div>
      </header>
      <main className="flex-1">{children}</main>
      <footer className="mt-10 text-xs text-foam/50">Local stack · Keycloak realm brewsbook · API on port 8080</footer>
    </div>
  );
}
