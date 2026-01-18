#!/usr/bin/env bash
# Pure functions for release.sh - designed to be testable in isolation

# =============================================================================
# Configuration
# =============================================================================
REPO_NAME="phel-lang/phel-lang"

# Flags (set via arguments)
DRY_RUN=0
FORCE=0
SKIP_PHAR=0
NEW_VERSION=""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

# =============================================================================
# Logging (3 functions only)
# =============================================================================
log() {
    echo -e "$*"
}

log_ok() {
    echo -e "${GREEN}[OK]${NC} $*"
}

log_err() {
    echo -e "${RED}[ERROR]${NC} $*" >&2
}

# =============================================================================
# Version Functions
# =============================================================================
validate_semver() {
    local version="$1"
    [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]
}

# Compare two semver strings. Returns 0 if $1 > $2
version_gt() {
    local v1="$1"
    local v2="$2"
    local IFS='.'
    read -ra V1 <<< "$v1"
    read -ra V2 <<< "$v2"

    for i in 0 1 2; do
        local n1="${V1[$i]:-0}"
        local n2="${V2[$i]:-0}"
        if (( n1 > n2 )); then
            return 0
        elif (( n1 < n2 )); then
            return 1
        fi
    done
    return 1
}

get_current_version() {
    local version_file="$1"
    if [[ ! -f "$version_file" ]]; then
        log_err "VersionFinder.php not found: $version_file"
        return 1
    fi

    local version
    version=$(sed -nE "s/.*LATEST_VERSION = 'v([0-9]+\.[0-9]+\.[0-9]+)'.*/\1/p" "$version_file" 2>/dev/null || true)

    if [[ -z "$version" ]]; then
        log_err "Could not extract version from VersionFinder.php"
        return 1
    fi
    echo "$version"
}

# =============================================================================
# Argument Parsing
# =============================================================================
parse_args() {
    DRY_RUN=0
    FORCE=0
    SKIP_PHAR=0
    NEW_VERSION=""

    while [[ $# -gt 0 ]]; do
        case "$1" in
            --dry-run) DRY_RUN=1; shift ;;
            --force) FORCE=1; shift ;;
            --skip-phar) SKIP_PHAR=1; shift ;;
            -h|--help) return 2 ;;
            -*)
                log_err "Unknown option: $1"
                return 1
                ;;
            *)
                if [[ -z "$NEW_VERSION" ]]; then
                    NEW_VERSION="$1"
                else
                    log_err "Unexpected argument: $1"
                    return 1
                fi
                shift
                ;;
        esac
    done

    if [[ -z "$NEW_VERSION" ]]; then
        log_err "Version argument required"
        return 1
    fi
    return 0
}

# =============================================================================
# File Update Functions (pure - operate on files passed as arguments)
# =============================================================================
update_version_finder() {
    local version="$1"
    local version_file="$2"
    # Use -E for extended regex, compatible with both macOS and Linux
    sed -i.bak -E "s/LATEST_VERSION = 'v[0-9]+\.[0-9]+\.[0-9]+'/LATEST_VERSION = 'v$version'/" "$version_file"
    rm -f "$version_file.bak"
}

update_changelog() {
    local version="$1"
    local changelog_file="$2"
    local current_version="$3"
    local current_date
    current_date=$(date +%Y-%m-%d)

    local new_heading="## [$version](https://github.com/$REPO_NAME/compare/v$current_version...v$version) - $current_date"
    # Use awk for cross-platform newline insertion
    awk -v new="$new_heading" '/^## Unreleased$/{print; print ""; print new; next}1' "$changelog_file" > "$changelog_file.tmp"
    mv "$changelog_file.tmp" "$changelog_file"
}

extract_release_notes() {
    local version="$1"
    local changelog_file="$2"
    # Extract content between version heading and next heading, skip first and last line
    local content
    content=$(sed -n "/^## \[$version\]/,/^## \[/p" "$changelog_file" | tail -n +2)
    # Remove last line (next version heading) and empty lines - compatible with macOS
    echo "$content" | sed '$d' | sed '/^$/d'
}

# =============================================================================
# Pre-flight Check Functions
# =============================================================================
check_gh_cli() {
    if ! command -v gh &>/dev/null; then
        log_err "GitHub CLI (gh) not installed - https://cli.github.com/"
        return 1
    fi
    if ! gh auth status &>/dev/null; then
        log_err "GitHub CLI not authenticated - run: gh auth login"
        return 1
    fi
    log_ok "GitHub CLI ready"
}

check_git_state() {
    local repo_root="$1"
    if [[ ! -d "$repo_root/.git" ]]; then
        log_err "Not a git repository"
        return 1
    fi
    if ! git -C "$repo_root" diff --quiet || ! git -C "$repo_root" diff --cached --quiet; then
        log_err "Working directory has uncommitted changes"
        return 1
    fi
    local untracked
    untracked=$(git -C "$repo_root" ls-files --others --exclude-standard src/ 2>/dev/null || true)
    if [[ -n "$untracked" ]]; then
        log_err "Untracked files in src/: $untracked"
        return 1
    fi
    log_ok "Git working directory clean"
}

check_branch() {
    local repo_root="$1"
    local current_branch
    current_branch=$(git -C "$repo_root" branch --show-current)
    if [[ "$current_branch" != "main" ]]; then
        log_err "Not on main branch (current: $current_branch)"
        return 1
    fi
    git -C "$repo_root" fetch origin main --quiet 2>/dev/null || true
    local local_commit remote_commit
    local_commit=$(git -C "$repo_root" rev-parse HEAD)
    remote_commit=$(git -C "$repo_root" rev-parse origin/main 2>/dev/null || echo "")
    if [[ -n "$remote_commit" ]] && [[ "$local_commit" != "$remote_commit" ]]; then
        log "[WARN] Local differs from origin/main"
    fi
    log_ok "On main branch"
}

check_required_files() {
    local version_file="$1"
    local changelog_file="$2"
    local phar_script="$3"
    local missing=0

    [[ ! -f "$version_file" ]] && { log_err "Missing: $version_file"; missing=1; }
    [[ ! -f "$changelog_file" ]] && { log_err "Missing: $changelog_file"; missing=1; }
    [[ ! -f "$phar_script" ]] && { log_err "Missing: $phar_script"; missing=1; }

    [[ $missing -eq 1 ]] && return 1
    log_ok "All required files present"
}

check_changelog_unreleased() {
    local changelog_file="$1"
    local unreleased_content
    # Use sed '$d' instead of head -n -1 for macOS compatibility
    unreleased_content=$(sed -n '/^## Unreleased/,/^## \[/p' "$changelog_file" | tail -n +2 | sed '$d' | grep -v '^$' || true)
    if [[ -z "$unreleased_content" ]]; then
        log_err "No content in Unreleased section of CHANGELOG.md"
        return 1
    fi
    log_ok "CHANGELOG.md has unreleased content"
}

check_network() {
    if ! curl -s --max-time 5 "https://github.com" >/dev/null 2>&1; then
        log_err "Cannot reach github.com"
        return 1
    fi
    log_ok "Network OK"
}

check_tag_exists() {
    local version="$1"
    local repo_root="$2"
    if git -C "$repo_root" tag -l "v$version" | grep -q "v$version"; then
        log_err "Tag v$version already exists"
        return 1
    fi
    log_ok "Tag v$version available"
}

# =============================================================================
# Backup & Rollback
# =============================================================================
create_backup() {
    local backup_dir="$1"
    local version_file="$2"
    local changelog_file="$3"
    cp "$version_file" "$backup_dir/VersionFinder.php"
    cp "$changelog_file" "$backup_dir/CHANGELOG.md"
}

restore_backup() {
    local backup_dir="$1"
    local version_file="$2"
    local changelog_file="$3"
    [[ -f "$backup_dir/VersionFinder.php" ]] && cp "$backup_dir/VersionFinder.php" "$version_file"
    [[ -f "$backup_dir/CHANGELOG.md" ]] && cp "$backup_dir/CHANGELOG.md" "$changelog_file"
}

# =============================================================================
# Git Operations
# =============================================================================
git_commit_release() {
    local version="$1"
    local repo_root="$2"
    local version_file="$3"
    local changelog_file="$4"
    git -C "$repo_root" add "$version_file" "$changelog_file"
    git -C "$repo_root" commit -m "chore(release): v$version"
}

git_create_tag() {
    local version="$1"
    local repo_root="$2"
    git -C "$repo_root" tag -a "v$version" -m "Release v$version"
}

git_push() {
    local version="$1"
    local repo_root="$2"
    git -C "$repo_root" push origin main
    git -C "$repo_root" push origin "v$version"
}

# =============================================================================
# GitHub Release
# =============================================================================
create_github_release() {
    local version="$1"
    local changelog_file="$2"
    local phar_output="$3"
    local skip_phar="$4"

    local release_notes
    release_notes=$(extract_release_notes "$version" "$changelog_file")
    [[ -z "$release_notes" ]] && release_notes="Release v$version"

    local gh_cmd="gh release create v$version --repo $REPO_NAME --title \"v$version\" --notes-file -"
    if [[ "$skip_phar" -eq 0 ]] && [[ -f "$phar_output" ]]; then
        gh_cmd="$gh_cmd \"$phar_output\""
    fi

    if echo "$release_notes" | eval "$gh_cmd"; then
        log_ok "Created GitHub release v$version"
        log "Release URL: https://github.com/$REPO_NAME/releases/tag/v$version"
    else
        log_err "Failed to create GitHub release"
        return 1
    fi
}

# =============================================================================
# Help
# =============================================================================
show_help() {
    cat << 'EOF'
Phel Release Automation Script

USAGE:
    release.sh [OPTIONS] VERSION

ARGUMENTS:
    VERSION         Semantic version number (e.g., 0.28.0)

OPTIONS:
    --dry-run       Preview all changes without modifying anything
    --force         Skip confirmation prompts (for CI automation)
    --skip-phar     Skip PHAR build step
    -h, --help      Show this help message

EXAMPLES:
    release.sh 0.28.0              # Standard release
    release.sh --dry-run 0.28.0    # Preview changes
    release.sh --force 0.28.0      # Skip prompts (CI)

WORKFLOW:
    1. Validate version format and increment
    2. Run pre-flight checks
    3. Update VersionFinder.php and CHANGELOG.md
    4. Commit, build PHAR, tag, push
    5. Create GitHub release with PHAR attachment
EOF
}
