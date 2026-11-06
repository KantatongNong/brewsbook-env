"use client";

import { useAuth } from "./AuthProvider";

export function RequireAuth({ children }: { children: React.ReactNode }) {
  const auth = useAuth();
  if (!auth.ready) return <p className="text-foam/70">Checking your session…</p>;
  if (!auth.authenticated) {
    return (
      <section className="panel max-w-xl p-6">
        <h1 className="font-display text-3xl">Sign in first</h1>
        <p className="mt-2 text-foam/80">This page calls the Brewsbook API with your Keycloak access token.</p>
        <button type="button" className="btn-primary mt-5" onClick={auth.login}>
          Sign in with Keycloak
        </button>
      </section>
    );
  }
  if (auth.profileError && !auth.profile) {
    return <p className="text-rosewine">{auth.profileError}</p>;
  }
  if (!auth.profile) return <p className="text-foam/70">Loading your profile…</p>;
  return children;
}

export function AdminGate({ children }: { children: React.ReactNode }) {
  const auth = useAuth();
  if (!auth.ready) return <p className="text-foam/70">Checking your session…</p>;
  if (!auth.authenticated) {
    return (
      <section className="panel max-w-xl p-6">
        <h1 className="font-display text-3xl">Platform admin</h1>
        <p className="mt-2 text-foam/80">Sign in with the platform_admin test user.</p>
        <button type="button" className="btn-primary mt-5" onClick={auth.login}>
          Sign in
        </button>
      </section>
    );
  }
  if (!auth.profile) return <p className="text-foam/70">Loading your profile…</p>;
  if (!auth.profile.roles.includes("platform_admin")) {
    return (
      <section className="panel max-w-xl p-6">
        <h1 className="font-display text-3xl">403</h1>
        <p className="mt-2 text-foam/80">
          This account is not a platform admin. The API returns 403 for <code>/api/admin/*</code>.
        </p>
      </section>
    );
  }
  return children;
}

export function Alert({ tone = "info", children }: { tone?: "info" | "error" | "ok"; children: React.ReactNode }) {
  const toneClass =
    tone === "error" ? "border-rosewine/40 bg-rosewine/10" : tone === "ok" ? "border-hop/40 bg-hop/10" : "border-white/10 bg-white/5";
  return <p className={`rounded-2xl border px-4 py-3 text-sm ${toneClass}`}>{children}</p>;
}

export function Pill({ children, tone }: { children: React.ReactNode; tone: "pending" | "approved" | "rejected" | "enabled" | "disabled" | "open" | "closed" | "role" }) {
  const tones: Record<string, string> = {
    pending: "bg-copper/20 text-copper-2",
    approved: "bg-hop/20 text-hop",
    enabled: "bg-hop/20 text-hop",
    open: "bg-hop/20 text-hop",
    rejected: "bg-rosewine/20 text-rosewine",
    disabled: "bg-rosewine/20 text-rosewine",
    closed: "bg-white/10 text-foam",
    role: "bg-cream/15 text-cream",
  };
  return <span className={`pill ${tones[tone]}`}>{children}</span>;
}
