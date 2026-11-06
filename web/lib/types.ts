export interface Membership {
  shop_id: number;
  shop_name: string;
  shop_slug: string;
  role: string;
  platform_status: "enabled" | "disabled";
  open_status: "open" | "closed";
}

export interface Me {
  id: number;
  keycloak_sub: string;
  email: string | null;
  preferred_username: string | null;
  display_name: string | null;
  given_name: string | null;
  family_name: string | null;
  phone: string | null;
  locale: string | null;
  roles: string[];
  memberships: Membership[];
  created_at: string | null;
  updated_at: string | null;
}

export interface ShopRequest {
  id: number;
  request_type: string;
  status: "pending" | "approved" | "rejected";
  shop_id: number | null;
  shop_name: string;
  description: string | null;
  phone: string | null;
  address_line: string | null;
  payload: {
    shop_name?: string;
    description?: string | null;
    phone?: string | null;
    address_line?: string | null;
  } | null;
  rejection_reason: string | null;
  enabled_modules: string[] | null;
  requester: {
    id: number;
    email: string | null;
    display_name: string | null;
    preferred_username: string | null;
  };
  reviewed_by: number | null;
  reviewed_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ShopModule {
  module_key: string;
  enabled: boolean;
}

export interface Shop {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  phone: string | null;
  address_line: string | null;
  owner_user_id: number | null;
  owner: {
    id: number;
    email: string | null;
    display_name: string | null;
    preferred_username: string | null;
  } | null;
  platform_status: "enabled" | "disabled";
  open_status: "open" | "closed";
  modules: ShopModule[];
  enabled_modules: string[];
  created_at: string | null;
  updated_at: string | null;
}
