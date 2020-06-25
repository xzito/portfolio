#!/bin/bash

root="$(git rev-parse --show-toplevel)"

# shellcheck disable=SC1090,SC1091
source "$root/.env"

rsync -a "$XZ_PORTFOLIOS_JSON_DIR" "$XZ_PORTFOLIOS_TRACKED_DIR"
rsync -a --delete "$XZ_PORTFOLIOS_TRACKED_DIR" "$XZ_PORTFOLIOS_MU_PLUGIN_DIR"
