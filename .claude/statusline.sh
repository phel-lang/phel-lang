#!/bin/bash
# shellcheck disable=SC2046
# shellcheck disable=SC2154

input=$(cat)

# Parse all values in single jq call for performance
eval $(echo "$input" | jq -r '
  @sh "dir=\(.workspace.current_dir // .cwd // "unknown")",
  @sh "cumulative_input=\(.context_window.total_input_tokens // 0)",
  @sh "cumulative_output=\(.context_window.total_output_tokens // 0)",
  @sh "context_pct=\(.context_window.used_percentage // 0)",
  @sh "model=\(.model.display_name // "Claude")",
  @sh "cost=\(.cost.total_cost_usd // 0)",
  @sh "five_hour_pct=\(.rate_limits.five_hour.used_percentage // "")",
  @sh "seven_day_pct=\(.rate_limits.seven_day.used_percentage // "")"
')
session_total=$((cumulative_input + cumulative_output))

# Format numbers with K/M suffix
format_tokens() {
  local n=$1
  if [ "$n" -ge 1000000 ]; then
    printf "%.1fM" $(echo "scale=1; $n/1000000" | bc)
  elif [ "$n" -ge 1000 ]; then
    printf "%.0fK" $(echo "scale=1; $n/1000" | bc)
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
printf '  \033[35m%s\033[0m' "$(format_tokens $session_total)"

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
