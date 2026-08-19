#!/bin/bash
# StatusLine script for Claude Code
# Shows: robbyrussell prompt + model + cost + context % + 5h rate limit + 7d rate limit

input=$(cat)

# Parse all values in one jq call for performance and predictable quoting.
{
  IFS= read -r dir
  IFS= read -r cumulative_input
  IFS= read -r cumulative_output
  IFS= read -r context_pct
  IFS= read -r model
  IFS= read -r cost
  IFS= read -r five_hour_pct
  IFS= read -r seven_day_pct
} < <(
  printf '%s' "$input" | jq -r '[
    .workspace.current_dir // .cwd // "unknown",
    .context_window.total_input_tokens // 0,
    .context_window.total_output_tokens // 0,
    .context_window.used_percentage // 0,
    .model.display_name // "Claude",
    .cost.total_cost_usd // 0,
    .rate_limits.five_hour.used_percentage // "",
    .rate_limits.seven_day.used_percentage // ""
  ][]'
)
session_total=$((cumulative_input + cumulative_output))

# Format numbers with K/M suffix
format_tokens() {
  local n=$1
  local scaled
  if [ "$n" -ge 1000000 ]; then
    scaled=$(printf 'scale=1; %s/1000000\n' "$n" | bc)
    printf "%.1fM" "$scaled"
  elif [ "$n" -ge 1000 ]; then
    scaled=$(printf 'scale=1; %s/1000\n' "$n" | bc)
    printf "%.0fK" "$scaled"
  else
    printf "%d" "$n"
  fi
}

# Robbyrussell-style prompt
printf '\033[1;32m➜\033[0m  \033[36m%s\033[0m' "$(basename "$dir")"

# Git info
if git -C "$dir" --no-optional-locks rev-parse --git-dir > /dev/null 2>&1; then
  branch=$(git -C "$dir" --no-optional-locks branch --show-current 2>/dev/null)
  [ -z "$branch" ] && branch=$(git -C "$dir" --no-optional-locks rev-parse --short HEAD 2>/dev/null)
  if [ -n "$branch" ]; then
    printf ' \033[1;34mgit:(\033[31m%s\033[34m)\033[0m' "$branch"
    if ! git -C "$dir" --no-optional-locks diff --quiet 2>/dev/null || \
       ! git -C "$dir" --no-optional-locks diff --cached --quiet 2>/dev/null; then
      printf ' \033[33m✗\033[0m'
    fi
  fi
fi

# Model name (dimmed)
printf '  \033[90m%s\033[0m' "$model"

# Cost (green)
printf '  \033[32m$%.2f\033[0m' "$cost"

# Tokens
printf '  \033[35m%s\033[0m' "$(format_tokens "$session_total")"

# Context % - color based on usage (cyan < 70%, yellow 70-85%, red > 85%)
context_int=${context_pct%.*}
if [ "$context_int" -gt 85 ]; then
  printf '  \033[31m%s%%\033[0m' "$context_pct"
elif [ "$context_int" -gt 70 ]; then
  printf '  \033[33m%s%%\033[0m' "$context_pct"
else
  printf '  \033[36m%s%%\033[0m' "$context_pct"
fi

# 5-hour session rate limit (only shown when available)
if [ -n "$five_hour_pct" ]; then
  five_int=$(printf "%.0f" "$five_hour_pct")
  if [ "$five_int" -gt 85 ]; then
    printf '  \033[31m5h:%s%%\033[0m' "$five_int"
  elif [ "$five_int" -gt 70 ]; then
    printf '  \033[33m5h:%s%%\033[0m' "$five_int"
  else
    printf '  \033[32m5h:%s%%\033[0m' "$five_int"
  fi
fi

# 7-day weekly rate limit (only shown when available)
if [ -n "$seven_day_pct" ]; then
  week_int=$(printf "%.0f" "$seven_day_pct")
  if [ "$week_int" -gt 85 ]; then
    printf '  \033[31m7d:%s%%\033[0m' "$week_int"
  elif [ "$week_int" -gt 70 ]; then
    printf '  \033[33m7d:%s%%\033[0m' "$week_int"
  else
    printf '  \033[32m7d:%s%%\033[0m' "$week_int"
  fi
fi
