"use client";

import { FormEvent, useEffect, useState } from "react";
import { Alert, RequireAuth } from "@/components/Gates";
import { useAuth } from "@/components/AuthProvider";
import { api, errorText } from "@/lib/api";
import type { Me } from "@/lib/types";

export default function ProfilePage() {
  return (
    <RequireAuth>
      <ProfileForm />
    </RequireAuth>
  );
}

function ProfileForm() {
  const auth = useAuth();
  const profile = auth.profile!;
  const [displayName, setDisplayName] = useState(profile.display_name ?? "");
  const [givenName, setGivenName] = useState(profile.given_name ?? "");
  const [familyName, setFamilyName] = useState(profile.family_name ?? "");
  const [phone, setPhone] = useState(profile.phone ?? "");
  const [locale, setLocale] = useState(profile.locale ?? "");
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    setDisplayName(profile.display_name ?? "");
    setGivenName(profile.given_name ?? "");
    setFamilyName(profile.family_name ?? "");
    setPhone(profile.phone ?? "");
    setLocale(profile.locale ?? "");
  }, [profile]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      const updated = await api<Me>("/api/me", {
        method: "PUT",
        body: JSON.stringify({
          display_name: displayName,
          given_name: givenName,
          family_name: familyName,
          phone,
          locale,
        }),
      });
      setMessage("Profile saved.");
      await auth.reload();
      setDisplayName(updated.display_name ?? "");
    } catch (err) {
      setError(errorText(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
      <form className="panel space-y-4 p-6" onSubmit={onSubmit}>
        <h1 className="font-display text-4xl">Profile</h1>
        <label className="block text-sm">
          Display name
          <input className="field mt-1" value={displayName} onChange={(e) => setDisplayName(e.target.value)} required />
        </label>
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="block text-sm">
            Given name
            <input className="field mt-1" value={givenName} onChange={(e) => setGivenName(e.target.value)} />
          </label>
          <label className="block text-sm">
            Family name
            <input className="field mt-1" value={familyName} onChange={(e) => setFamilyName(e.target.value)} />
          </label>
        </div>
        <label className="block text-sm">
          Phone
          <input className="field mt-1" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="+66 …" />
        </label>
        <label className="block text-sm">
          Locale
          <input className="field mt-1" value={locale} onChange={(e) => setLocale(e.target.value)} placeholder="en or th" />
        </label>
        {error ? <Alert tone="error">{error}</Alert> : null}
        {message ? <Alert tone="ok">{message}</Alert> : null}
        <button type="submit" className="btn-primary" disabled={busy}>
          {busy ? "Saving…" : "Save profile"}
        </button>
      </form>
      <aside className="panel space-y-3 p-6 text-sm">
        <h2 className="font-display text-2xl">From Keycloak</h2>
        <p>
          <span className="text-foam/60">Username</span>
          <br />
          {profile.preferred_username || "—"}
        </p>
        <p>
          <span className="text-foam/60">Email</span>
          <br />
          {profile.email || "—"}
        </p>
        <p>
          <span className="text-foam/60">Subject</span>
          <br />
          <span className="break-all font-mono text-xs">{profile.keycloak_sub}</span>
        </p>
        <p className="text-foam/70">
          Roles on this token: {profile.roles.join(", ") || "none"}. After an admin approves your shop, use Refresh
          session so the owner role is loaded into a new access token. Membership is stored in MySQL immediately.
        </p>
        <button type="button" className="btn-ghost" onClick={() => void auth.refreshSession()}>
          Refresh session
        </button>
      </aside>
    </div>
  );
}
