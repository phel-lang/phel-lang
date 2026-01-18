#!/usr/bin/env bash
set -euo pipefail

# Phel Release Script - sources release-lib.sh for testable functions

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"

# Source the library
source "$SCRIPT_DIR/release-lib.sh"

# File paths
VERSION_FILE="$REPO_ROOT/src/php/Console/Application/VersionFinder.php"
CHANGELOG_FILE="$REPO_ROOT/CHANGELOG.md"
PHAR_SCRIPT="$SCRIPT_DIR/phar.sh"
PHAR_OUTPUT="$SCRIPT_DIR/out/phel.phar"

# Backup directory
BACKUP_DIR=""

# =============================================================================
# Cleanup & Rollback
# =============================================================================
cleanup_backup() {
    [[ -n "$BACKUP_DIR" ]] && [[ -d "$BACKUP_DIR" ]] && rm -rf "$BACKUP_DIR"
    return 0
}

rollback() {
    if [[ -n "$BACKUP_DIR" ]] && [[ -d "$BACKUP_DIR" ]]; then
        log "[WARN] Rolling back..."
        restore_backup "$BACKUP_DIR" "$VERSION_FILE" "$CHANGELOG_FILE"
        cleanup_backup
    fi
}

trap_handler() {
    local exit_code=$?
    if [[ $exit_code -ne 0 ]]; then
        rollback
    fi
    cleanup_backup
    exit $exit_code
}

trap trap_handler EXIT

# =============================================================================
# Pre-flight Checks
# =============================================================================
run_preflight_checks() {
    log "\n${BOLD}Pre-flight checks${NC}"
    check_gh_cli
    check_git_state "$REPO_ROOT"
    check_branch "$REPO_ROOT"
    check_required_files "$VERSION_FILE" "$CHANGELOG_FILE" "$PHAR_SCRIPT"
    check_changelog_unreleased "$CHANGELOG_FILE"
    check_network
    check_tag_exists "$NEW_VERSION" "$REPO_ROOT"
    log_ok "All pre-flight checks passed"
}

# =============================================================================
# Confirmation
# =============================================================================
confirm_release() {
    local version="$1"
    local current_version="$2"

    [[ $FORCE -eq 1 ]] && return 0

    echo ""
    log "${BOLD}Release: v$current_version → v$version${NC}"
    [[ -n "$RELEASE_NAME" ]] && log "Name: $RELEASE_NAME"
    log "Files: VersionFinder.php, CHANGELOG.md"
    log "Actions: update files, commit, build PHAR, tag, push, create release"
    echo ""

    read -rp "Proceed? [y/N] " response
    case "$response" in
        [yY][eE][sS]|[yY]) return 0 ;;
        *) log "[WARN] Cancelled"; exit 0 ;;
    esac
}

# =============================================================================
# Build PHAR
# =============================================================================
build_phar() {
    log "\n${BOLD}Building PHAR${NC}"

    if [[ $SKIP_PHAR -eq 1 ]]; then
        log "[WARN] Skipping PHAR build (--skip-phar)"
        return
    fi

    if [[ $DRY_RUN -eq 1 ]]; then
        log "[DRY-RUN] Would: OFFICIAL_RELEASE=true $PHAR_SCRIPT"
        return
    fi

    if ! OFFICIAL_RELEASE=true "$PHAR_SCRIPT"; then
        log_err "PHAR build failed"
        return 1
    fi

    if [[ ! -f "$PHAR_OUTPUT" ]]; then
        log_err "PHAR file not found: $PHAR_OUTPUT"
        return 1
    fi

    log_ok "Built PHAR: $PHAR_OUTPUT"
}

# =============================================================================
# Main
# =============================================================================
main() {
    # Parse arguments
    local parse_result=0
    parse_args "$@" || parse_result=$?

    if [[ $parse_result -eq 2 ]]; then
        show_help
        exit 0
    elif [[ $parse_result -ne 0 ]]; then
        echo ""
        show_help
        exit 1
    fi

    log "\n${BOLD}Phel Release Script${NC}\n"

    if [[ $DRY_RUN -eq 1 ]]; then
        log "${YELLOW}DRY-RUN mode - no changes will be made${NC}\n"
    fi

    # Validate version
    log "${BOLD}Validating version${NC}"
    if ! validate_semver "$NEW_VERSION"; then
        log_err "Invalid version format: $NEW_VERSION (expected X.Y.Z)"
        exit 1
    fi
    log_ok "Version format valid: $NEW_VERSION"

    # Get current version
    local current_version
    current_version=$(get_current_version "$VERSION_FILE") || exit 1

    log "Current: v$current_version → New: v$NEW_VERSION"

    if ! version_gt "$NEW_VERSION" "$current_version"; then
        log_err "New version must be greater than current ($current_version)"
        exit 1
    fi
    log_ok "Version increment valid"

    # Pre-flight checks
    run_preflight_checks

    # Prompt for release name if not provided
    if [[ -z "$RELEASE_NAME" ]]; then
        prompt_release_name "$CHANGELOG_FILE"
    fi

    # Confirm (unless --force or --dry-run)
    [[ $DRY_RUN -eq 0 ]] && confirm_release "$NEW_VERSION" "$current_version"

    # Backup (always create, even in dry-run, so we can restore after showing changes)
    log "\n${BOLD}Creating backup${NC}"
    BACKUP_DIR=$(mktemp -d)
    create_backup "$BACKUP_DIR" "$VERSION_FILE" "$CHANGELOG_FILE"
    if [[ $DRY_RUN -eq 1 ]]; then
        log "[DRY-RUN] Backup created: $BACKUP_DIR"
    else
        log_ok "Backup created: $BACKUP_DIR"
    fi

    # Update files (always update, even in dry-run, to show accurate release notes)
    log "\n${BOLD}Updating files${NC}"
    update_version_finder "$NEW_VERSION" "$VERSION_FILE"
    update_changelog "$NEW_VERSION" "$CHANGELOG_FILE" "$current_version"
    if [[ $DRY_RUN -eq 1 ]]; then
        log "[DRY-RUN] Updated VersionFinder.php to v$NEW_VERSION"
        log "[DRY-RUN] Updated CHANGELOG.md"
    else
        log_ok "Updated VersionFinder.php"
        log_ok "Updated CHANGELOG.md"
    fi

    # Git commit
    log "\n${BOLD}Committing changes${NC}"
    if [[ $DRY_RUN -eq 1 ]]; then
        log "[DRY-RUN] Would: git commit -m 'chore(release): v$NEW_VERSION'"
    else
        git_commit_release "$NEW_VERSION" "$REPO_ROOT" "$VERSION_FILE" "$CHANGELOG_FILE"
        log_ok "Created release commit"
    fi

    # Build PHAR
    build_phar

    # Create tag
    log "\n${BOLD}Creating tag${NC}"
    if [[ $DRY_RUN -eq 1 ]]; then
        log "[DRY-RUN] Would: git tag -a v$NEW_VERSION"
    else
        git_create_tag "$NEW_VERSION" "$REPO_ROOT"
        log_ok "Created tag v$NEW_VERSION"
    fi

    # Push
    log "\n${BOLD}Pushing to remote${NC}"
    if [[ $DRY_RUN -eq 1 ]]; then
        log "[DRY-RUN] Would: git push origin main"
        log "[DRY-RUN] Would: git push origin v$NEW_VERSION"
    else
        git_push "$NEW_VERSION" "$REPO_ROOT"
        log_ok "Pushed commit and tag"
    fi

    # GitHub release
    log "\n${BOLD}Creating GitHub release${NC}"
    if [[ $DRY_RUN -eq 1 ]]; then
        local title="$NEW_VERSION"
        [[ -n "$RELEASE_NAME" ]] && title="$NEW_VERSION - $RELEASE_NAME"
        log "[DRY-RUN] Would: create GitHub release \"$title\" (tag: v$NEW_VERSION)"
        local notes
        notes=$(extract_release_notes "$NEW_VERSION" "$CHANGELOG_FILE" 2>/dev/null || echo "Release v$NEW_VERSION")
        local contributors
        contributors=$(get_contributors "$current_version" "$REPO_ROOT")
        log "[DRY-RUN] Release notes:\n$notes\n\n## Contributors\n$contributors\n\n**Full Changelog**: https://github.com/$REPO_NAME/compare/v$current_version...v$NEW_VERSION"
        [[ $SKIP_PHAR -eq 0 ]] && log "[DRY-RUN] Would: attach PHAR"
    else
        create_github_release "$NEW_VERSION" "$CHANGELOG_FILE" "$PHAR_OUTPUT" "$SKIP_PHAR" "$RELEASE_NAME" "$current_version" "$REPO_ROOT"
    fi

    # Done
    echo ""
    if [[ $DRY_RUN -eq 1 ]]; then
        # Restore files in dry-run mode
        restore_backup "$BACKUP_DIR" "$VERSION_FILE" "$CHANGELOG_FILE"
        cleanup_backup
        log_ok "Dry-run complete - files restored, no changes made"
    else
        cleanup_backup
        log_ok "Release v$NEW_VERSION complete!"
    fi
}

main "$@"
