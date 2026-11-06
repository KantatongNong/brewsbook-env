"use client";

import { useEffect, useState } from "react";
import { AdminGate, Alert, Pill } from "@/components/Gates";
import { ModulePicker } from "@/components/ModulePicker";
import { api, errorText } from "@/lib/api";
import { formatWhen } from "@/lib/format";
import type { ShopRequest } from "@/lib/types";

const FILTERS = ["all", "pending", "approved", "rejected"] as const;

export default function AdminRequestsPage() {
  return (
    <AdminGate>
      <Queue />
    </AdminGate>
  );
}

function Queue() {
  const [filter, setFilter] = useState<(typeof FILTERS)[number]>("pending");
  const [rows, setRows] = useState<ShopRequest[]>([]);
  const [modules, setModules] = useState<Record<number, string[]>>({});
  const [reasons, setReasons] = useState<Record<number, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  async function load(next = filter) {
    const query = next === "all" ? "" : `?status=${next}`;
    const body = await api<{ data: ShopRequest[] }>(`/api/admin/shop-requests${query}`);
    setRows(body.data);
  }

  useEffect(() => {
    load(filter).catch((err: unknown) => setError(errorText(err)));
    // filter is applied inside load calls from the buttons as well
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filter]);

  async function decide(id: number, action: "approve" | "reject") {
    setBusyId(id);
    setError(null);
    setMessage(null);
    try {
      const body =
        action === "approve"
          ? { action, enabled_modules: modules[id] ?? [] }
          : { action, rejection_reason: reasons[id] ?? "" };
      const updated = await api<ShopRequest>(`/api/admin/shop-requests/${id}`, {
        method: "PUT",
        body: JSON.stringify(body),
      });
      setMessage(
        action === "approve"
          ? `Approved ${updated.shop_name}. Shop #${updated.shop_id} is owned by the requester.`
          : `Rejected ${updated.shop_name}.`,
      );
      await load();
    } catch (err) {
      setError(errorText(err));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div className="space-y-4">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-4xl">Shop requests</h1>
          <p className="text-foam/70">Approve only after choosing at least one module.</p>
        </div>
        <div className="flex gap-2">
          {FILTERS.map((item) => (
            <button
              key={item}
              type="button"
              className={item === filter ? "btn-primary" : "btn-ghost"}
              onClick={() => setFilter(item)}
            >
              {item}
            </button>
          ))}
        </div>
      </header>
      {error ? <Alert tone="error">{error}</Alert> : null}
      {message ? <Alert tone="ok">{message}</Alert> : null}
      {rows.length === 0 ? <p className="text-foam/70">No requests in this view.</p> : null}
      {rows.map((request) => {
        const chosen = modules[request.id] ?? [];
        return (
          <article key={request.id} className="panel space-y-4 p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="font-display text-2xl">{request.shop_name}</h2>
                <p className="text-sm text-foam/70">
                  {request.requester.display_name || request.requester.preferred_username} · {request.requester.email}
                </p>
                <p className="mt-2 text-sm">{request.description || "No description"}</p>
                <p className="mt-1 text-xs text-foam/50">
                  {request.phone || "No phone"} · {request.address_line || "No address"} · {formatWhen(request.created_at)}
                </p>
              </div>
              <Pill tone={request.status}>{request.status}</Pill>
            </div>
            {request.status === "pending" ? (
              <>
                <ModulePicker
                  value={chosen}
                  onChange={(next) => setModules((current) => ({ ...current, [request.id]: next }))}
                />
                <div className="flex flex-wrap gap-3">
                  <button
                    type="button"
                    className="btn-primary"
                    disabled={busyId === request.id || chosen.length === 0}
                    onClick={() => void decide(request.id, "approve")}
                  >
                    Approve
                  </button>
                  <input
                    className="field max-w-sm"
                    placeholder="Rejection reason (optional)"
                    value={reasons[request.id] ?? ""}
                    onChange={(e) => setReasons((current) => ({ ...current, [request.id]: e.target.value }))}
                  />
                  <button
                    type="button"
                    className="btn-danger"
                    disabled={busyId === request.id}
                    onClick={() => void decide(request.id, "reject")}
                  >
                    Reject
                  </button>
                </div>
              </>
            ) : (
              <p className="text-sm text-foam/75">
                {request.enabled_modules?.length ? `Modules: ${request.enabled_modules.join(", ")}` : null}
                {request.rejection_reason ? `Reason: ${request.rejection_reason}` : null}
                {request.shop_id ? ` · shop #${request.shop_id}` : ""}
              </p>
            )}
          </article>
        );
      })}
    </div>
  );
}
