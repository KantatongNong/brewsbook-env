"use client";

import Link from "next/link";
import { Alert, Pill } from "@/components/Gates";
import { useAuth } from "@/components/AuthProvider";

export default function HomePage() {
  const auth = useAuth();

  if (!auth.ready) {
    return <p className="text-foam/70">Checking your session…</p>;
  }

  if (!auth.authenticated || !auth.profile) {
    return (
      <div className="grid gap-6 lg:grid-cols-[1.4fr_0.8fr]">
        <section className="panel p-8">
          <p className="text-xs uppercase tracking-[0.22em] text-copper-2">Local ledger</p>
          <h1 className="mt-3 font-display text-5xl leading-tight">Who is brewing, and which shop they may open.</h1>
          <p className="mt-4 max-w-xl text-lg text-foam/80">
            Sign in through the Brewsbook Keycloak realm. Buyers keep a profile and submit a shop request. Platform
            admins approve modules and turn platform access on or off.
          </p>
          <button type="button" className="btn-primary mt-6" onClick={auth.login}>
            Sign in with Keycloak
          </button>
          {auth.profileError ? (
            <div className="mt-4">
              <Alert tone="error">{auth.profileError}</Alert>
            </div>
          ) : null}
        </section>
        <aside className="panel p-6">
          <h2 className="font-display text-2xl">Local test accounts</h2>
          <dl className="mt-4 space-y-4 text-sm">
            <div>
              <dt className="text-foam/60">Buyer</dt>
              <dd className="font-mono">buyer / buyer123</dd>
            </div>
            <div>
              <dt className="text-foam/60">Platform admin</dt>
              <dd className="font-mono">platform_admin / admin123</dd>
            </div>
          </dl>
          <p className="mt-4 text-xs text-foam/60">
            New Keycloak registrations receive the realm default role <span className="font-mono">buyer</span>. These
            passwords are for the local realm only.
          </p>
        </aside>
      </div>
    );
  }

  const me = auth.profile;
  return (
    <div className="space-y-6">
      <section className="panel p-8">
        <p className="text-xs uppercase tracking-[0.22em] text-copper-2">Signed in</p>
        <h1 className="mt-2 font-display text-4xl">{me.display_name || me.preferred_username || "Brewer"}</h1>
        <p className="mt-2 text-foam/75">{me.email}</p>
        <div className="mt-4 flex flex-wrap gap-2">
          {me.roles.map((role) => (
            <Pill key={role} tone="role">
              {role}
            </Pill>
          ))}
        </div>
        <div className="mt-6 flex flex-wrap gap-3">
          <Link href="/profile" className="btn-primary">
            Edit profile
          </Link>
          <Link href="/shop-request" className="btn-ghost">
            Shop request
          </Link>
          <button type="button" className="btn-ghost" onClick={() => void auth.refreshSession()}>
            Refresh session
          </button>
        </div>
      </section>
      <section className="panel p-6">
        <h2 className="font-display text-2xl">Shop memberships</h2>
        {me.memberships.length === 0 ? (
          <p className="mt-3 text-foam/70">No shop yet. A platform admin grants Owner when they approve a request.</p>
        ) : (
          <ul className="mt-4 space-y-3">
            {me.memberships.map((membership) => (
              <li key={membership.shop_id} className="rounded-2xl border border-white/10 px-4 py-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p className="font-semibold">{membership.shop_name}</p>
                    <p className="text-sm text-foam/60">{membership.shop_slug}</p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Pill tone="role">{membership.role}</Pill>
                    <Pill tone={membership.platform_status}>{membership.platform_status}</Pill>
                    <Pill tone={membership.open_status}>{membership.open_status}</Pill>
                  </div>
                </div>
                {membership.platform_status === "disabled" ? (
                  <p className="mt-2 text-sm text-rosewine">Platform access is disabled. Open/closed is unchanged.</p>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}
