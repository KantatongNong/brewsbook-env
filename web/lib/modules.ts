export const MODULES = [
  { key: "ecommerce", label: "Ecommerce", hint: "Flag only — catalog is out of scope" },
  { key: "shop_store", label: "Shop Store", hint: "Flag only — storefront is out of scope" },
  { key: "location", label: "Location", hint: "Flag only — maps are out of scope" },
  { key: "beer_keg", label: "Beer Keg", hint: "Flag only — keg tracking is out of scope" },
] as const;

export type ModuleKey = (typeof MODULES)[number]["key"];
