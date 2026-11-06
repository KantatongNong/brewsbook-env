#!/bin/sh
set -eu
# Docker sets HOSTNAME to the container id, which makes the Next standalone
# server bind to an unreachable address. Force the public bind.
export HOSTNAME=0.0.0.0
export PORT="${PORT:-3000}"
exec node server.js
