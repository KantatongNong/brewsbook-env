#!/usr/bin/env python3
"""Exercise Identity & Profile acceptance checks against the local stack."""

from __future__ import annotations

import json
import os
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

API = os.environ.get("API_URL", "http://localhost:8080")
KC = os.environ.get("KEYCLOAK_URL", "http://localhost:8081")
REALM = "brewsbook"
CLIENT = "brewsbook-nextjs"


def load_dotenv(path: str) -> None:
    if not os.path.isfile(path):
        return
    with open(path, encoding="utf-8") as handle:
        for line in handle:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            os.environ.setdefault(key, value)


def request(method: str, url: str, body: dict | None = None, token: str | None = None, form: dict | None = None):
    data = None
    headers = {"Accept": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    if form is not None:
        data = urllib.parse.urlencode(form).encode()
        headers["Content-Type"] = "application/x-www-form-urlencoded"
    elif body is not None:
        data = json.dumps(body).encode()
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=20) as res:
            raw = res.read().decode()
            return res.status, json.loads(raw) if raw else None
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode()
        try:
            parsed = json.loads(raw) if raw else None
        except json.JSONDecodeError:
            parsed = {"message": raw}
        return exc.code, parsed


def token_for(username: str, password: str) -> str:
    status, body = request(
        "POST",
        f"{KC}/realms/{REALM}/protocol/openid-connect/token",
        form={
            "grant_type": "password",
            "client_id": CLIENT,
            "username": username,
            "password": password,
        },
    )
    if status != 200 or not isinstance(body, dict) or "access_token" not in body:
        raise SystemExit(f"token for {username} failed: {status} {body}")
    return str(body["access_token"])


def expect(label: str, status: int, body, code: int, pred=None) -> None:
    if status != code or (pred and not pred(body)):
        raise SystemExit(f"FAIL {label}: HTTP {status} body={body}")
    print(f"ok  {label}")


def admin_token() -> str:
    status, body = request(
        "POST",
        f"{KC}/realms/master/protocol/openid-connect/token",
        form={
            "grant_type": "password",
            "client_id": "admin-cli",
            "username": os.environ.get("KEYCLOAK_ADMIN", "admin"),
            "password": os.environ.get("KEYCLOAK_ADMIN_PASSWORD", ""),
        },
    )
    if status != 200 or not isinstance(body, dict):
        raise SystemExit(f"keycloak admin token failed: {status} {body}")
    return str(body["access_token"])


def main() -> None:
    load_dotenv(os.path.join(os.path.dirname(__file__), "..", ".env"))
    status, health = request("GET", f"{API}/health")
    expect("health", status, health, 200, lambda b: isinstance(b, dict) and b.get("status") == "ok")

    status, body = request("GET", f"{API}/api/me")
    expect("AC1 missing token", status, body, 401)

    status, body = request("GET", f"{API}/api/me", token="not-a-jwt")
    expect("AC1 invalid token", status, body, 401)

    buyer = token_for("buyer", "buyer123")
    status, me = request("GET", f"{API}/api/me", token=buyer)
    expect(
        "AC2 buyer profile",
        status,
        me,
        200,
        lambda b: isinstance(b, dict) and "buyer" in b.get("roles", []) and "platform_admin" not in b.get("roles", []),
    )

    kc_admin = admin_token()
    username = f"qa-new-{int(time.time())}"
    status, created = request(
        "POST",
        f"{KC}/admin/realms/{REALM}/users",
        token=kc_admin,
        body={
            "username": username,
            "enabled": True,
            "email": f"{username}@brewsbook.local",
            "emailVerified": True,
            "firstName": "New",
            "lastName": "Buyer",
            "credentials": [{"type": "password", "value": "buyer123", "temporary": False}],
        },
    )
    if status not in (201, 204):
        raise SystemExit(f"could not create keycloak user: {status} {created}")
    new_token = token_for(username, "buyer123")
    status, new_me = request("GET", f"{API}/api/me", token=new_token)
    expect("AC2 new user is buyer", status, new_me, 200, lambda b: "buyer" in b.get("roles", []))
    found, listing = request("GET", f"{KC}/admin/realms/{REALM}/users?username={username}&exact=true", token=kc_admin)
    if found == 200 and isinstance(listing, list) and listing:
        request("DELETE", f"{KC}/admin/realms/{REALM}/users/{listing[0]['id']}", token=kc_admin)

    status, updated = request(
        "PUT",
        f"{API}/api/me",
        token=buyer,
        body={"display_name": "Bea Buyer", "phone": "+66 81 000 0000", "locale": "th"},
    )
    expect("AC9 profile update", status, updated, 200, lambda b: b.get("display_name") == "Bea Buyer" and b.get("locale") == "th")

    status, forbidden = request("GET", f"{API}/api/admin/shop-requests", token=buyer)
    expect("AC4 buyer forbidden", status, forbidden, 403)
    status, forbidden = request("PUT", f"{API}/api/admin/shops/1/status", token=buyer, body={"platform_status": "disabled"})
    expect("AC4 buyer status forbidden", status, forbidden, 403)

    stamp = int(time.time())
    status, created_req = request(
        "POST",
        f"{API}/api/shop-requests",
        token=buyer,
        body={
            "shop_name": f"Hop House {stamp}",
            "description": "A small taproom",
            "phone": "0812345678",
            "address_line": "Chiang Mai",
        },
    )
    expect("AC3 create pending", status, created_req, 201, lambda b: b.get("status") == "pending" and b.get("payload", {}).get("shop_name"))
    request_id = created_req["id"]
    status, mine = request("GET", f"{API}/api/shop-requests/me", token=buyer)
    expect("AC3 list mine", status, mine, 200, lambda b: any(row["id"] == request_id for row in b.get("data", [])))
    status, dup = request(
        "POST",
        f"{API}/api/shop-requests",
        token=buyer,
        body={"shop_name": "Second pending"},
    )
    expect("AC3 one pending", status, dup, 409)

    admin = token_for("platform_admin", "admin123")
    status, queue = request("GET", f"{API}/api/admin/shop-requests?status=pending", token=admin)
    expect("AC5 list pending", status, queue, 200, lambda b: any(row["id"] == request_id for row in b.get("data", [])))
    status, bad = request(
        "PUT",
        f"{API}/api/admin/shop-requests/{request_id}",
        token=admin,
        body={"action": "approve", "enabled_modules": []},
    )
    expect("AC5 approve requires modules", status, bad, 422)
    status, bad = request(
        "PUT",
        f"{API}/api/admin/shop-requests/{request_id}",
        token=admin,
        body={"action": "approve", "enabled_modules": ["rating"]},
    )
    expect("AC5 reject unknown module", status, bad, 422)

    status, rejected = request(
        "PUT",
        f"{API}/api/admin/shop-requests/{request_id}",
        token=admin,
        body={"action": "reject", "rejection_reason": "Need a clearer name"},
    )
    expect("AC5 reject", status, rejected, 200, lambda b: b.get("status") == "rejected" and b.get("rejection_reason"))

    status, second = request(
        "POST",
        f"{API}/api/shop-requests",
        token=buyer,
        body={"shop_name": f"Approved House {stamp}", "description": "Ready"},
    )
    expect("create after reject", status, second, 201)
    second_id = second["id"]
    status, approved = request(
        "PUT",
        f"{API}/api/admin/shop-requests/{second_id}",
        token=admin,
        body={"action": "approve", "enabled_modules": ["ecommerce", "shop_store"]},
    )
    expect(
        "AC6 approve",
        status,
        approved,
        200,
        lambda b: b.get("status") == "approved" and b.get("shop_id") and set(b.get("enabled_modules") or []) == {"ecommerce", "shop_store"},
    )
    shop_id = approved["shop_id"]

    fresh_buyer = token_for("buyer", "buyer123")
    status, me2 = request("GET", f"{API}/api/me", token=fresh_buyer)
    expect(
        "AC6 owner role and membership",
        status,
        me2,
        200,
        lambda b: "owner" in b.get("roles", []) and any(m["shop_id"] == shop_id and m["role"] == "owner" for m in b.get("memberships", [])),
    )

    status, shops = request("GET", f"{API}/api/admin/shops", token=admin)
    expect(
        "AC6 shop row",
        status,
        shops,
        200,
        lambda b: any(
            s["id"] == shop_id and s["platform_status"] == "enabled" and s["open_status"] == "closed" and set(s["enabled_modules"]) == {"ecommerce", "shop_store"}
            for s in b.get("data", [])
        ),
    )

    status, mod = request(
        "PUT",
        f"{API}/api/admin/shops/{shop_id}/modules",
        token=admin,
        body={"enabled_modules": ["location", "beer_keg"]},
    )
    expect("AC7 modules", status, mod, 200, lambda b: set(b.get("enabled_modules") or []) == {"location", "beer_keg"})

    status, edited = request(
        "PUT",
        f"{API}/api/admin/shops/{shop_id}",
        token=admin,
        body={"name": f"Edited House {stamp}", "open_status": "open", "description": "Updated by admin"},
    )
    expect(
        "AC8 edit keeps platform enabled",
        status,
        edited,
        200,
        lambda b: b.get("open_status") == "open" and b.get("platform_status") == "enabled" and str(b.get("name", "")).startswith("Edited House"),
    )
    status, mixed = request(
        "PUT",
        f"{API}/api/admin/shops/{shop_id}",
        token=admin,
        body={"platform_status": "disabled"},
    )
    expect("AC8 status not on shop update", status, mixed, 422)

    status, disabled = request(
        "PUT",
        f"{API}/api/admin/shops/{shop_id}/status",
        token=admin,
        body={"platform_status": "disabled"},
    )
    expect(
        "AC8 disable keeps open",
        status,
        disabled,
        200,
        lambda b: b.get("platform_status") == "disabled" and b.get("open_status") == "open",
    )
    status, enabled = request(
        "PUT",
        f"{API}/api/admin/shops/{shop_id}/status",
        token=admin,
        body={"platform_status": "enabled"},
    )
    expect(
        "AC8 enable restores",
        status,
        enabled,
        200,
        lambda b: b.get("platform_status") == "enabled" and b.get("open_status") == "open",
    )

    # Expired access token: shorten the public client lifespan, then restore it.
    status, clients = request("GET", f"{KC}/admin/realms/{REALM}/clients?clientId={CLIENT}", token=kc_admin)
    if status != 200 or not isinstance(clients, list) or not clients:
        raise SystemExit(f"could not load client: {status} {clients}")
    client = clients[0]
    original = dict(client.get("attributes") or {})
    attributes = dict(original)
    attributes["access.token.lifespan"] = "4"
    client["attributes"] = attributes
    status, _ = request("PUT", f"{KC}/admin/realms/{REALM}/clients/{client['id']}", token=kc_admin, body=client)
    if status not in (200, 204):
        raise SystemExit(f"could not set token lifespan: {status}")
    try:
        short = token_for("buyer", "buyer123")
        # Lifespan is 4s and the API allows 10s of clock skew, so wait past both.
        time.sleep(16)
        status, expired = request("GET", f"{API}/api/me", token=short)
        expect("AC1 expired token", status, expired, 401, lambda b: isinstance(b, dict) and "expired" in (b.get("message") or "").lower())
    finally:
        restored = dict(original)
        restored["access.token.lifespan"] = original.get("access.token.lifespan", "300")
        client["attributes"] = restored
        request("PUT", f"{KC}/admin/realms/{REALM}/clients/{client['id']}", token=kc_admin, body=client)

    schema_check()
    print("ALL CHECKS PASSED")


def schema_check() -> None:
    password = os.environ.get("MYSQL_PASSWORD", "")
    user = os.environ.get("MYSQL_USER", "brewsbook")
    database = os.environ.get("MYSQL_DATABASE", "brewsbook")
    sql = (
        "SELECT table_name, column_name FROM information_schema.columns "
        f"WHERE table_schema='{database}' AND table_name IN "
        "('users','shop_requests','shops','shop_modules','shop_members')"
    )
    raw = subprocess.check_output(
        [
            "docker",
            "exec",
            "-e",
            f"MYSQL_PWD={password}",
            "brewsbook-mysql",
            "mysql",
            "-N",
            f"-u{user}",
            database,
            "-e",
            sql,
        ],
        text=True,
    )
    needed = {
        ("users", "keycloak_sub"),
        ("shop_requests", "status"),
        ("shop_requests", "rejection_reason"),
        ("shop_requests", "payload_json"),
        ("shops", "platform_status"),
        ("shops", "open_status"),
        ("shop_modules", "module_key"),
        ("shop_members", "role"),
    }
    found = set()
    for line in raw.splitlines():
        table, column = line.split("\t")
        found.add((table, column))
    missing = needed - found
    if missing:
        raise SystemExit(f"FAIL AC10 missing columns: {sorted(missing)}")
    print("ok  AC10 schema")


if __name__ == "__main__":
    try:
        main()
    except urllib.error.URLError as exc:
        raise SystemExit(f"network error: {exc}") from exc
