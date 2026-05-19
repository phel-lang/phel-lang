#!/usr/bin/env bash
# Pure helper functions for build/upgrade-ecosystem.sh, separated so bashunit
# can source them without triggering the orchestration side effects.
#
# Anything that touches network, git state, or other repos belongs in the
# main script, not here. Functions here must be deterministic and side-effect
# free (or scoped to caller-supplied paths/strings).

# Strip a leading "v" from a tag-like version string. Returns nothing if input
# is empty.
#   v0.40.0    -> 0.40.0
#   0.40.0     -> 0.40.0
#   version-x  -> version-x
normalize_version() {
  local v="$1"
  printf '%s' "${v#v}"
}

# Convert a semver-ish version to a composer caret constraint pinned to the
# MAJOR.MINOR pair (correct for 0.x lines too: composer's caret on 0.x pins
# the minor).
#   0.39.0    -> ^0.39
#   0.40.1    -> ^0.40
#   1.2.3     -> ^1.2
#   weird     -> ^weird     (passthrough fallback)
derive_caret() {
  local v="$1"
  if [[ "$v" =~ ^([0-9]+)\.([0-9]+)\. ]]; then
    printf '^%s.%s' "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
  else
    printf '^%s' "$v"
  fi
}

# CSV membership check. Empty haystack always returns false.
#   in_csv foo "foo,bar"  -> 0
#   in_csv foo "bar,baz"  -> 1
#   in_csv foo ""         -> 1
in_csv() {
  local needle="$1" haystack="$2"
  [[ -z "$haystack" ]] && return 1
  [[ ",$haystack," == *",$needle,"* ]]
}

# Return 0 if composer.json at $1 has phel-lang/phel-lang under "require".
# Uses php so we don't depend on jq.
composer_requires_phel() {
  local path="$1"
  [[ -f "$path" ]] || return 1
  php -r '$j=json_decode(file_get_contents($argv[1]),true);
          exit(isset($j["require"]["phel-lang/phel-lang"]) ? 0 : 1);' \
    "$path" 2>/dev/null
}

# Print the current phel-lang/phel-lang constraint from a composer.json, or
# "?" if absent. Used for preflight display.
composer_phel_constraint() {
  local path="$1"
  [[ -f "$path" ]] || { printf '?'; return; }
  php -r '$j=json_decode(file_get_contents($argv[1]),true);
          echo $j["require"]["phel-lang/phel-lang"] ?? "?";' \
    "$path" 2>/dev/null
}

# Portable timeout wrapper. macOS lacks coreutils `timeout` by default; fall
# back to brew's `gtimeout`, and finally to perl alarm (perl ships with macOS).
# Exit code 124 is reserved for "command timed out", matching coreutils.
run_with_timeout() {
  local secs="$1"; shift
  if command -v timeout  >/dev/null 2>&1; then timeout  "$secs" "$@"
  elif command -v gtimeout >/dev/null 2>&1; then gtimeout "$secs" "$@"
  else perl -e 'my $s=shift; $SIG{ALRM}=sub{exit 124}; alarm $s; exec @ARGV' \
        "$secs" "$@"
  fi
}
