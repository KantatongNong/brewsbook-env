"use client";

import { useEffect, useState } from "react";
import { AdminGate, Alert, Pill } from "@/components/Gates";
import { ModulePicker } from "@/components/ModulePicker";
import { api, errorText } from "@/lib/api";
import type { Shop } from "@/lib/types";

interface Draft {
  name: string;
  description: string;
  phone: string;
  address_line: string;
  open_status: "open" | "closed";
  slug: string;
  modules: string[];
}

export default function AdminShopsPage() {
  return (
    <AdminGate>
      <ShopList />
    </AdminGate>
  );
}

function ShopList() {
  const [shops, setShops] = useState<Shop[]>([]);
  const [drafts, setDrafts] = useState<Record<number, Draft>>({});
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  function remember(rows: Shop[]) {
    setShops(rows);
    setDrafts(
      Object.fromEntries(
        rows.map((shop) => [
          shop.id,
          {
            name: shop.name,
            description: shop.description ?? "",
            phone: shop.phone ?? "",
            address_line: shop.address_line ?? "",
            open_status: shop.open_status,
            slug: shop.slug,
            modules: shop.enabled_modules,
          },
        ]),
      ),
    );
  }

  async function load() {
    const body = await api<{ data: Shop[] }>("/api/admin/shops");
    remember(body.data);
  }

  useEffect(() => {
    load().catch((err: unknown) => setError(errorText(err)));
  }, []);

  function patchDraft(id: number, patch: Partial<Draft>) {
    setDrafts((current) => ({ ...current, [id]: { ...current[id], ...patch } }));
  }

  async function saveDetails(shop: Shop) {
    const draft = drafts[shop.id];
    setBusyId(shop.id);
    setError(null);
    setMessage(null);
    try {
      const updated = await api<Shop>(`/api/admin/shops/${shop.id}`, {
        method: "PUT",
        body: JSON.stringify({
          name: draft.name,
          description: draft.description,
          phone: draft.phone,
          address_line: draft.address_line,
          open_status: draft.open_status,
          slug: draft.slug,
        }),
      });
      setMessage(`Saved ${updated.name}. Platform status is still ${updated.platform_status}.`);
      await load();
    } catch (err) {
      setError(errorText(err));
    } finally {
      setBusyId(null);
    }
  }

  async function saveModules(shop: Shop) {
    const draft = drafts[shop.id];
    setBusyId(shop.id);
    setError(null);
    setMessage(null);
    try {
      await api<Shop>(`/api/admin/shops/${shop.id}/modules`, {
        method: "PUT",
        body: JSON.stringify({ enabled_modules: draft.modules }),
      });
      setMessage(`Updated modules for ${shop.name}.`);
      await load();
    } catch (err) {
      setError(errorText(err));
    } finally {
      setBusyId(null);
    }
  }

  async function togglePlatform(shop: Shop) {
    const next = shop.platform_status === "enabled" ? "disabled" : "enabled";
    setBusyId(shop.id);
    setError(null);
    setMessage(null);
    try {
      const updated = await api<Shop>(`/api/admin/shops/${shop.id}/status`, {
        method: "PUT",
        body: JSON.stringify({ platform_status: next }),
      });
      setMessage(
        `${updated.name} is ${updated.platform_status} on the platform. Open/closed stays ${updated.open_status}.`,
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
      <header>
        <h1 className="font-display text-4xl">Shops</h1>
        <p className="text-foam/70">
          Platform status blocks platform use. Open/closed is the shop&apos;s own sign and is stored separately.
        </p>
      </header>
      {error ? <Alert tone="error">{error}</Alert> : null}
      {message ? <Alert tone="ok">{message}</Alert> : null}
      {shops.length === 0 ? <p className="text-foam/70">No shops yet. Approve a request first.</p> : null}
      {shops.map((shop) => {
        const draft = drafts[shop.id];
        if (!draft) return null;
        return (
          <article key={shop.id} className="panel space-y-4 p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="font-display text-2xl">{shop.name}</h2>
                <p className="text-sm text-foam/70">
                  Owner {shop.owner?.display_name || shop.owner?.email || "—"} · #{shop.id}
                </p>
              </div>
              <div className="flex gap-2">
                <Pill tone={shop.platform_status}>platform {shop.platform_status}</Pill>
                <Pill tone={shop.open_status}>store {shop.open_status}</Pill>
              </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="text-sm">
                Name
                <input className="field mt-1" value={draft.name} onChange={(e) => patchDraft(shop.id, { name: e.target.value })} />
              </label>
              <label className="text-sm">
                Slug
                <input className="field mt-1" value={draft.slug} onChange={(e) => patchDraft(shop.id, { slug: e.target.value })} />
              </label>
              <label className="text-sm sm:col-span-2">
                Description
                <textarea className="field mt-1 min-h-20" value={draft.description} onChange={(e) => patchDraft(shop.id, { description: e.target.value })} />
              </label>
              <label className="text-sm">
                Phone
                <input className="field mt-1" value={draft.phone} onChange={(e) => patchDraft(shop.id, { phone: e.target.value })} />
              </label>
              <label className="text-sm">
                Address
                <input className="field mt-1" value={draft.address_line} onChange={(e) => patchDraft(shop.id, { address_line: e.target.value })} />
              </label>
              <label className="text-sm">
                Open / closed
                <select
                  className="field mt-1"
                  value={draft.open_status}
                  onChange={(e) => patchDraft(shop.id, { open_status: e.target.value as "open" | "closed" })}
                >
                  <option value="closed">closed</option>
                  <option value="open">open</option>
                </select>
              </label>
            </div>
            <div className="flex flex-wrap gap-3">
              <button type="button" className="btn-primary" disabled={busyId === shop.id} onClick={() => void saveDetails(shop)}>
                Save shop
              </button>
              <button type="button" className="btn-ghost" disabled={busyId === shop.id} onClick={() => void togglePlatform(shop)}>
                {shop.platform_status === "enabled" ? "Disable platform access" : "Enable platform access"}
              </button>
            </div>
            <div className="space-y-3 border-t border-white/10 pt-4">
              <h3 className="font-semibold">Modules</h3>
              <ModulePicker value={draft.modules} onChange={(modules) => patchDraft(shop.id, { modules })} />
              <button type="button" className="btn-ghost" disabled={busyId === shop.id || draft.modules.length === 0} onClick={() => void saveModules(shop)}>
                Save modules
              </button>
            </div>
          </article>
        );
      })}
    </div>
  );
}
