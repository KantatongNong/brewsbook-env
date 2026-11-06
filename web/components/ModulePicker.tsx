"use client";

import { MODULES } from "@/lib/modules";

export function ModulePicker({
  value,
  onChange,
  disabled = false,
}: {
  value: string[];
  onChange: (next: string[]) => void;
  disabled?: boolean;
}) {
  return (
    <ul className="grid gap-2 sm:grid-cols-2">
      {MODULES.map((mod) => {
        const checked = value.includes(mod.key);
        return (
          <li key={mod.key}>
            <label
              className={`flex cursor-pointer gap-3 rounded-2xl border px-3 py-3 ${
                checked ? "border-copper/70 bg-copper/10" : "border-white/10 bg-black/20"
              } ${disabled ? "cursor-not-allowed opacity-60" : ""}`}
            >
              <input
                type="checkbox"
                className="mt-1 accent-copper"
                checked={checked}
                disabled={disabled}
                onChange={() => {
                  onChange(checked ? value.filter((key) => key !== mod.key) : [...value, mod.key]);
                }}
              />
              <span>
                <span className="block font-semibold">{mod.label}</span>
                <span className="block text-xs text-foam/70">{mod.hint}</span>
                <span className="mt-1 block font-mono text-[11px] text-foam/50">{mod.key}</span>
              </span>
            </label>
          </li>
        );
      })}
    </ul>
  );
}
