"use client";

import { FormEvent, useEffect, useState } from "react";
import { Alert, Pill, RequireAuth } from "@/components/Gates";
import { useAuth } from "@/components/AuthProvider";
import { api, errorText } from "@/lib/api";
import { formatWhen } from "@/lib/format";
import type { ShopRequest } from "@/lib/types";

export default function ShopRequestPage() {
  return (
    <RequireAuth>
      <ShopRequestScreen />
    </RequireAuth>
  );
}

function ShopRequestScreen() {
  const auth = useAuth();
  const [requests, setRequests] = useState<ShopRequest[]>([]);
  const [shopName, setShopName] = useState("");
  const [description, setDescription] = useState("");
  const [phone, setPhone] = useState("");
  const [address, setAddress] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function load() {
    const body = await api<{ data: ShopRequest[] }>("/api/shop-requests/me");
    setRequests(body.data);
  }

  useEffect(() => {
    load().catch((err: unknown) => setError(errorText(err)));
  }, []);

  const pending = requests.some((request) => request.status === "pending");

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      await api<ShopRequest>("/api/shop-requests", {
        method: "POST",
        body: JSON.stringify({
          shop_name: shopName,
          description,
          phone,
          address_line: address,
        }),
      });
      setShopName("");
      setDescription("");
      setPhone("");
      setAddress("");
      setMessage("Request submitted. Status is pending.");
      await load();
      await auth.reload();
    } catch (err) {
      setError(errorText(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-6">
      <form className="panel space-y-4 p-6" onSubmit={onSubmit}>
        <h1 className="font-display text-4xl">Request a shop</h1>
        <p className="text-foam/75">Any signed-in buyer can submit. A platform admin chooses modules when they approve.</p>
        <label className="block text-sm">
          Shop name
          <input className="field mt-1" value={shopName} onChange={(e) => setShopName(e.target.value)} required minLength={2} maxLength={120} />
        </label>
        <label className="block text-sm">
          Description
          <textarea className="field mt-1 min-h-28" value={description} onChange={(e) => setDescription(e.target.value)} />
        </label>
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="block text-sm">
            Phone
            <input className="field mt-1" value={phone} onChange={(e) => setPhone(e.target.value)} />
          </label>
          <label className="block text-sm">
            Address line
            <input className="field mt-1" value={address} onChange={(e) => setAddress(e.target.value)} />
          </label>
        </div>
        {pending ? <Alert>You already have a pending request. Wait for a decision before sending another.</Alert> : null}
        {error ? <Alert tone="error">{error}</Alert> : null}
        {message ? <Alert tone="ok">{message}</Alert> : null}
        <button type="submit" className="btn-primary" disabled={busy || pending}>
          {busy ? "Submitting…" : "Submit request"}
        </button>
      </form>

      <section className="space-y-3">
        <h2 className="font-display text-2xl">Your requests</h2>
        {requests.length === 0 ? <p className="text-foam/70">No requests yet.</p> : null}
        {requests.map((request) => (
          <article key={request.id} className="panel p-5">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h3 className="font-display text-2xl">{request.shop_name}</h3>
              <Pill tone={request.status}>{request.status}</Pill>
            </div>
            <p className="mt-2 text-sm text-foam/75">{request.description || "No description"}</p>
            <p className="mt-2 text-xs text-foam/50">Submitted {formatWhen(request.created_at)}</p>
            {request.rejection_reason ? <p className="mt-2 text-sm text-rosewine">Rejected: {request.rejection_reason}</p> : null}
            {request.shop_id ? (
              <p className="mt-2 text-sm text-hop">
                Shop #{request.shop_id}
                {request.enabled_modules?.length ? ` · modules ${request.enabled_modules.join(", ")}` : ""}
              </p>
            ) : null}
          </article>
        ))}
      </section>
    </div>
  );
}
