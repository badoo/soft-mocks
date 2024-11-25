#!/bin/sh
# Script to clean soft mocks cache.
# It deletes files that were not used in 14 days (by default).
# You probably need to put this script into cron or somewhere similar.

CACHE_DIR=/tmp/mocks

find "$CACHE_DIR" -mtime +14 -type f -delete
find "$CACHE_DIR" -mindepth 1 -type d -empty -delete
