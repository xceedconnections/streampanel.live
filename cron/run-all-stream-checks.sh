#!/bin/bash
# Run both M3U8 and DASH stream source checkers (dead sources only, never deletes channels)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

bash "$SCRIPT_DIR/run-m3u8-check.sh"
M3U8_EXIT=$?

bash "$SCRIPT_DIR/run-dash-check.sh"
DASH_EXIT=$?

if [ "$M3U8_EXIT" -ne 0 ] || [ "$DASH_EXIT" -ne 0 ]; then
    exit 1
fi

exit 0
