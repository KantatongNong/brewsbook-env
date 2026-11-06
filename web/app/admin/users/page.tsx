"use client";

import { FormEvent, useEffect, useState } from "react";
import { AdminGate, Alert } from "@/components/Gates";
import { api, errorText } from "@/lib/api";
import { formatWhen } from "@/lib/format";
import type { DirectoryUser } from "@/lib/types";

export default function AdminUsersPage() {
  return (
    <AdminGate>
      <UserList />
    </AdminGate>
  );
}

function text(value: string | null): string {
  return value && value.trim() !== "" ? value : "—";
}

function legalName(user: DirectoryUser): string {
  const parts = [user.given_name, user.family_name].filter((part): part is string => Boolean(part && part.trim() !== ""));
  return parts.length > 0 ? parts.join(" ") : "—";
}

function UserList() {
  const [q, setQ] = useState("");
  const [applied, setApplied] = useState("");
  const [rows, setRows] = useState<DirectoryUser[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  async function load(query: string) {
    const trimmed = query.trim();
    const params = trimmed ? `?q=${encodeURIComponent(trimmed)}` : "";
    const body = await api<{ data: DirectoryUser[] }>(`/api/admin/users${params}`);
    setRows(body.data);
    setApplied(trimmed);
  }

  useEffect(() => {
    load("")
      .catch((err: unknown) => setError(errorText(err)))
      .finally(() => setLoading(false));
  }, []);

  function onSearch(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setLoading(true);
    load(q)
      .catch((err: unknown) => setError(errorText(err)))
      .finally(() => setLoading(false));
  }

  return (
    <div className="space-y-4">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-4xl">Users</h1>
          <p className="text-foam/70">
            Profiles stored in MySQL after someone calls the API. This page does not create or edit Keycloak accounts.
          </p>
        </div>
        <form className="flex flex-wrap gap-2" onSubmit={onSearch}>
          <input
            className="field w-64"
            placeholder="Name or email"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            aria-label="Search users"
          />
          <button type="submit" className="btn-primary">
            Search
          </button>
        </form>
      </header>
      {error ? <Alert tone="error">{error}</Alert> : null}
      {loading ? <p className="text-foam/70">Loading users…</p> : null}
      {!loading && rows.length === 0 ? (
        <p className="text-foam/70">{applied ? `No users match "${applied}".` : "No users have signed in yet."}</p>
      ) : null}
      {!loading && rows.length > 0 ? (
        <div className="panel overflow-x-auto">
          <p className="px-4 pt-4 text-sm text-foam/70">
            {rows.length} {rows.length === 1 ? "user" : "users"}
            {applied ? ` matching "${applied}"` : ""}
          </p>
          <table className="w-full min-w-[960px] text-left text-sm">
            <thead className="text-xs uppercase tracking-wide text-foam/60">
              <tr>
                <th className="px-4 py-3 font-semibold">Name</th>
                <th className="px-4 py-3 font-semibold">Username</th>
                <th className="px-4 py-3 font-semibold">Email</th>
                <th className="px-4 py-3 font-semibold">Phone</th>
                <th className="px-4 py-3 font-semibold">Locale</th>
                <th className="px-4 py-3 font-semibold">Keycloak sub</th>
                <th className="px-4 py-3 font-semibold">Created</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((user) => (
                <tr key={user.id} className="border-t border-white/10 align-top">
                  <td className="px-4 py-3">
                    <p className="font-semibold">{text(user.display_name)}</p>
                    <p className="text-xs text-foam/60">
                      {legalName(user)} · #{user.id}
                    </p>
                  </td>
                  <td className="px-4 py-3">{text(user.preferred_username)}</td>
                  <td className="px-4 py-3">{text(user.email)}</td>
                  <td className="px-4 py-3">{text(user.phone)}</td>
                  <td className="px-4 py-3">{text(user.locale)}</td>
                  <td className="max-w-xs break-all px-4 py-3 font-mono text-xs text-foam/80">{user.keycloak_sub}</td>
                  <td className="px-4 py-3">
                    <p>{formatWhen(user.created_at)}</p>
                    <p className="text-xs text-foam/50">Updated {formatWhen(user.updated_at)}</p>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
